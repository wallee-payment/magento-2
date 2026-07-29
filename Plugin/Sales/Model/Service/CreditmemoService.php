<?php
/**
 * wallee Magento 2
 *
 * This Magento 2 extension enables to process payments with wallee (https://www.wallee.com).
 *
 * @package Wallee_Payment
 * @author wallee AG (https://www.wallee.com)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)

 */
namespace Wallee\Payment\Plugin\Sales\Model\Service;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Sales\Model\Order;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\Payment\Api\RefundJobRepositoryInterface;
use Wallee\Payment\Model\RefundJobFactory;
use Wallee\Payment\Model\Payment\Method\Adapter as PaymentMethodAdapter;
use Wallee\Payment\Model\Service\LineItemReductionService;
use Wallee\Payment\Model\Service\RefundService;
use Wallee\PluginCore\Refund\RefundService as CoreRefundService;

/**
 * Interceptor to handle refund jobs when a refund is triggered.
 */
class CreditmemoService
{
    /**
     * Seconds to wait for the per-order lock before giving up.
     */
    private const LOCK_TIMEOUT_SECONDS = 10;

    /**
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     *
     * @var LockManagerInterface
     */
    private $lockManager;

    /**
     *
     * @var LineItemReductionService
     */
    private $lineItemReductionService;

    /**
     *
     * @var RefundJobFactory
     */
    private $refundJobFactory;

    /**
     *
     * @var RefundJobRepositoryInterface
     */
    private $refundJobRepository;

    /**
     *
     * @var RefundService
     */
    private $refundService;

    /**
     *
     * @var CoreRefundService
     */
    private $pluginCoreRefundService;

    /**
     *
     * @param LoggerInterface $logger
     * @param LineItemReductionService $lineItemReductionService
     * @param RefundJobFactory $refundJobFactory
     * @param RefundJobRepositoryInterface $refundJobRepository
     * @param RefundService $refundService
     * @param CoreRefundService $pluginCoreRefundService
     * @param LockManagerInterface $lockManager
     */
    public function __construct(
        LoggerInterface $logger,
        LineItemReductionService $lineItemReductionService,
        RefundJobFactory $refundJobFactory,
        RefundJobRepositoryInterface $refundJobRepository,
        RefundService $refundService,
        CoreRefundService $pluginCoreRefundService,
        LockManagerInterface $lockManager
    ) {
        $this->logger = $logger;
        $this->lineItemReductionService = $lineItemReductionService;
        $this->refundJobFactory = $refundJobFactory;
        $this->refundJobRepository = $refundJobRepository;
        $this->refundService = $refundService;
        $this->pluginCoreRefundService = $pluginCoreRefundService;
        $this->lockManager = $lockManager;
    }

    /**
     * Wrap refund execution to clean up refund job on failure.
     *
     * Also holds the same per-order lock the inbound refund webhook uses, for the entire
     * gateway call and creditmemo/order persistence. Without it, the webhook confirming this
     * very refund can arrive and run concurrently with this method, find no creditmemo yet
     * committed under the refund's external id, and create a duplicate one.
     *
     * @param \Magento\Sales\Model\Service\CreditmemoService $subject
     * @param callable $proceed
     * @param \Magento\Sales\Api\Data\CreditmemoInterface $creditmemo
     * @param bool $offlineRequested
     * @return mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\StateException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function aroundRefund(
        \Magento\Sales\Model\Service\CreditmemoService $subject,
        callable $proceed,
        \Magento\Sales\Api\Data\CreditmemoInterface $creditmemo,
        $offlineRequested = false
    ) {
        $lockName = 'wallee_order_update_' . $creditmemo->getOrderId();
        $this->logger->debug("Locking: Requesting lock for ID: {$lockName}");
        if (!$this->lockManager->lock($lockName, self::LOCK_TIMEOUT_SECONDS)) {
            throw new \Magento\Framework\Exception\LocalizedException(
                \__('Could not acquire the order lock to process this refund. Please try again.')
            );
        }
        $this->logger->debug("Locking: Lock acquired for ID: {$lockName}");

        try {
            return $proceed($creditmemo, $offlineRequested);
        } catch (\Exception $e) {
            if ($creditmemo->getData('wallee_keep_refund_job') !== true) {
                try {
                    $this->refundJobRepository->delete(
                        $this->refundJobRepository->getByOrderId($creditmemo->getOrderId())
                    );
                } catch (NoSuchEntityException $exc) {
                    $this->logger->debug('No refund job found for deletion.');
                }
            }
            throw $e;
        } finally {
            $this->logger->debug("Locking: Releasing lock for ID: {$lockName}");
            $this->lockManager->unlock($lockName);
        }
    }

    /**
     * Create external refund job before refund execution.
     *
     * @param \Magento\Sales\Model\Service\CreditmemoService $subject
     * @param \Magento\Sales\Api\Data\CreditmemoInterface $creditmemo
     * @param bool $offlineRequested
     * @return void|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function beforeRefund(
        \Magento\Sales\Model\Service\CreditmemoService $subject,
        \Magento\Sales\Api\Data\CreditmemoInterface $creditmemo,
        $offlineRequested = false
    ) {
        if ($offlineRequested || ! $creditmemo->getInvoice()) {
            return null;
        }

        if ($creditmemo->getOrder()
            ->getPayment()
            ->getMethodInstance() instanceof PaymentMethodAdapter &&
            $creditmemo->getData('wallee_external_id') == null) {
            try {
                $this->handleExistingRefundJob($creditmemo->getOrder());

                $refundCreate = $this->refundService->createRefund($creditmemo);
                $this->refundService->createRefundJob($creditmemo->getInvoice(), $refundCreate);
            } catch (\Exception $e) {
                throw new \Magento\Framework\Exception\LocalizedException(\__($e->getMessage()));
            }
        }
    }

    /**
     * Checks if there is an existing refund job for the given order and trys to send to refund to the gateway again.
     *
     * @param Order $order
     * @return void
     * @throws \Exception
     */
    private function handleExistingRefundJob(Order $order)
    {
        try {
            $existingRefundJob = $this->refundJobRepository->getByOrderId($order->getId());
            try {
                $this->pluginCoreRefundService->createRefund(
                    (int) $order->getWalleeSpaceId(),
                    $existingRefundJob->getRefund()
                );
            } catch (\Exception $e) {
                $this->logger->critical(
                    "Retry of existing refund job {$existingRefundJob->getId()} failed: " . $e->getMessage()
                );
            }

            throw new \Magento\Framework\Exception\LocalizedException(
                \__('As long as there is an open creditmemo for the order, no new creditmemo can be created.')
            );
        } catch (NoSuchEntityException $e) {
            $this->logger->debug('No existing refund job found for order ' . $order->getId());
        }
    }
}
