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
namespace Wallee\Payment\Gateway\Command;

use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Psr\Log\LoggerInterface;
use Wallee\Payment\Api\RefundJobRepositoryInterface;
use Wallee\Payment\Helper\Locale as LocaleHelper;
use Wallee\Payment\Model\ApiClient;
use Wallee\Payment\Model\RefundJobFactory;
use Wallee\Payment\Model\Service\LineItemReductionService;
use Wallee\Sdk\Model\RefundState;
use Wallee\Sdk\Service\RefundService;

/**
 * Payment gateway command to refund a payment.
 */
class RefundCommand implements CommandInterface
{

    /**
     *
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     *
     * @var LocaleHelper
     */
    protected $_localeHelper;

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
     * @param LocaleHelper $localeHelper
     * @param LineItemReductionService $lineItemReductionService
     * @param RefundJobFactory $refundJobFactory
     * @param RefundJobRepositoryInterface $refundJobRepository
     * @param ApiClient $apiClient
     */
    public function __construct(LoggerInterface $logger, LocaleHelper $localeHelper,
        LineItemReductionService $lineItemReductionService, RefundJobFactory $refundJobFactory,
        RefundJobRepositoryInterface $refundJobRepository, ApiClient $apiClient)
    {
        $this->_logger = $logger;
        $this->_localeHelper = $localeHelper;
        $this->_lineItemReductionService = $lineItemReductionService;
        $this->_refundJobFactory = $refundJobFactory;
        $this->_refundJobRepository = $refundJobRepository;
        $this->_apiClient = $apiClient;
    }

    public function execute(array $commandSubject)
    {
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = SubjectReader::readPayment($commandSubject)->getPayment();
        $creditmemo = $payment->getCreditmemo();

        if ($creditmemo->getWalleeExternalId() == null) {
            $refundJob = $this->_refundJobRepository->getByOrderId($payment->getOrder()
                ->getId());
            try {
                $refund = $this->_apiClient->getService(RefundService::class)->refund(
                    $creditmemo->getOrder()
                        ->getWalleeSpaceId(), $refundJob->getRefund());
            } catch (\Wallee\Sdk\ApiException $e) {
                if ($e->getResponseObject() instanceof \Wallee\Sdk\Model\ClientError) {
                    $this->_refundJobRepository->delete($refundJob);
                    throw new \Magento\Framework\Exception\LocalizedException(
                        \__($e->getResponseObject()->getMessage()));
                } else {
                    $creditmemo->setWalleeKeepRefundJob(true);
                    $this->_logger->critical($e);
                    throw new \Magento\Framework\Exception\LocalizedException(
                        \__('There has been an error while sending the refund to the gateway.'));
                }
            } catch (\Exception $e) {
                $creditmemo->setWalleeKeepRefundJob(true);
                $this->_logger->critical($e);
                throw new \Magento\Framework\Exception\LocalizedException(
                    \__('There has been an error while sending the refund to the gateway.'));
            }

            if ($refund->getState() == RefundState::FAILED) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    $this->_localeHelper->translate($refund->getFailureReason()
                        ->getDescription()));
            } elseif ($refund->getState() == RefundState::PENDING || $refund->getState() == RefundState::MANUAL_CHECK) {
                $creditmemo->setWalleeKeepRefundJob(true);
                throw new \Magento\Framework\Exception\LocalizedException(
                    \__('The refund was requested successfully, but is still pending on the gateway.'));
            }

            $creditmemo->setWalleeExternalId($refund->getExternalId());
            $this->_refundJobRepository->delete($refundJob);
        }
    }
}