<?php
/**
 * wallee Magento 2
 *
 * This Magento 2 extension enables to process payments with wallee (https://www.wallee.com/).
 *
 * @package Wallee_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
namespace Wallee\Payment\Plugin\Sales\Model\Service;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Psr\Log\LoggerInterface;
use Wallee\Payment\Api\RefundJobRepositoryInterface;
use Wallee\Payment\Api\Data\RefundJobInterface;
use Wallee\Payment\Model\ApiClient;
use Wallee\Payment\Model\RefundJobFactory;
use Wallee\Payment\Model\Payment\Method\Adapter as PaymentMethodAdapter;
use Wallee\Payment\Model\Service\LineItemReductionService;
use Wallee\Sdk\Model\RefundCreate;
use Wallee\Sdk\Model\RefundType;
use Wallee\Sdk\Service\RefundService;

/**
 * Interceptor to handle refund jobs when a refund is triggered.
 */
class CreditmemoService
{

    /**
     *
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     *
     * @var LineItemReductionService
     */
    protected $_lineItemReductionService;

    /**
     *
     * @var RefundJobFactory
     */
    protected $_refundJobFactory;

    /**
     *
     * @var RefundJobRepositoryInterface
     */
    protected $_refundJobRepository;

    /**
     *
     * @var ApiClient
     */
    protected $_apiClient;

    /**
     *
     * @param LoggerInterface $logger
     * @param LineItemReductionService $lineItemReductionService
     * @param RefundJobFactory $refundJobFactory
     * @param RefundJobRepositoryInterface $refundJobRepository
     * @param ApiClient $apiClient
     */
    public function __construct(LoggerInterface $logger, LineItemReductionService $lineItemReductionService,
        RefundJobFactory $refundJobFactory, RefundJobRepositoryInterface $refundJobRepository, ApiClient $apiClient)
    {
        $this->_logger = $logger;
        $this->_lineItemReductionService = $lineItemReductionService;
        $this->_refundJobFactory = $refundJobFactory;
        $this->_refundJobRepository = $refundJobRepository;
        $this->_apiClient = $apiClient;
    }

    public function aroundRefund(\Magento\Sales\Model\Service\CreditmemoService $subject, callable $proceed,
        \Magento\Sales\Api\Data\CreditmemoInterface $creditmemo, $offlineRequested = false)
    {
        try {
            return $proceed($creditmemo, $offlineRequested);
        } catch (\Exception $e) {
            if ($creditmemo->getWalleeKeepRefundJob() !== true) {
                try {
                    $this->_refundJobRepository->delete(
                        $this->_refundJobRepository->getByOrderId($creditmemo->getOrderId()));
                } catch (NoSuchEntityException $e) {}
            }
            throw $e;
        }
    }

    public function beforeRefund(\Magento\Sales\Model\Service\CreditmemoService $subject,
        \Magento\Sales\Api\Data\CreditmemoInterface $creditmemo, $offlineRequested = false)
    {
        if ($offlineRequested || ! $creditmemo->getInvoice()) {
            return null;
        }

        if ($creditmemo->getOrder()
            ->getPayment()
            ->getMethodInstance() instanceof PaymentMethodAdapter &&
            $creditmemo->getWalleeExternalId() == null) {
            try {
                $this->handleExistingRefundJob($creditmemo->getOrder());

                $refundCreate = $this->createRefund($creditmemo);
                $refundJob = $this->createRefundJob($creditmemo->getInvoice(), $refundCreate);
            } catch (\Exception $e) {
                throw new \Magento\Framework\Exception\LocalizedException(\__($e->getMessage()));
            }
        }
    }

    /**
     * Checks if there is an existing refund job for the given order and trys to send to refund to the gateway again.
     *
     * @param Order $order
     * @throws \Exception
     */
    protected function handleExistingRefundJob(Order $order)
    {
        try {
            $existingRefundJob = $this->_refundJobRepository->getByOrderId($order->getId());
            try {
                $refund = $this->_apiClient->getService(RefundService::class)->refund(
                    $order->getWalleeSpaceId(), $existingRefundJob->getRefund());
            } catch (\Exception $e) {
                $this->_logger->critical($e);
            }

            throw new \Magento\Framework\Exception\LocalizedException(
                \__('As long as there is an open creditmemo for the order, no new creditmemo can be created.'));
        } catch (NoSuchEntityException $e) {}
    }

    /**
     * Creates a new refund job for the given invoice and refund.
     *
     * @param Invoice $invoice
     * @param RefundCreate $refund
     * @return \Wallee\Payment\Model\RefundJob
     */
    protected function createRefundJob(Invoice $invoice, RefundCreate $refund)
    {
        $entity = $this->_refundJobFactory->create();
        $entity->setData(RefundJobInterface::ORDER_ID, $invoice->getOrderId());
        $entity->setData(RefundJobInterface::INVOICE_ID, $invoice->getId());
        $entity->setData(RefundJobInterface::SPACE_ID,
            $invoice->getOrder()
                ->getWalleeSpaceId());
        $entity->setData(RefundJobInterface::EXTERNAL_ID, $refund->getExternalId());
        $entity->setData(RefundJobInterface::REFUND, $refund);
        return $this->_refundJobRepository->save($entity);
    }

    /**
     * Creates a refund creation model for the given creditmemo.
     *
     * @param Creditmemo $creditmemo
     * @return RefundCreate
     */
    protected function createRefund(Creditmemo $creditmemo)
    {
        $refund = new RefundCreate();
        $refund->setExternalId(\uniqid($creditmemo->getOrderId() . '-'));
        $refund->setReductions($this->_lineItemReductionService->convertCreditmemo($creditmemo));
        $refund->setTransaction($creditmemo->getOrder()
            ->getWalleeTransactionId());
        $refund->setType(RefundType::MERCHANT_INITIATED_ONLINE);
        return $refund;
    }
}