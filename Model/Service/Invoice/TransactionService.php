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
namespace Wallee\Payment\Model\Service\Invoice;

use Magento\Customer\Model\CustomerRegistry;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Wallee\Payment\Api\PaymentMethodConfigurationManagementInterface;
use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Wallee\Payment\Helper\Data as Helper;
use Wallee\Payment\Helper\Locale as LocaleHelper;
use Wallee\Payment\Model\ApiClient;
use Wallee\Payment\Model\Service\AbstractTransactionService;
use Wallee\Payment\Model\Service\Order\TransactionService as OrderTransactionService;
use Wallee\Sdk\Model\TransactionCompletion;
use Wallee\Sdk\Model\TransactionCompletionState;
use Wallee\Sdk\Model\TransactionInvoice;
use Wallee\Sdk\Model\TransactionInvoiceState;
use Wallee\Sdk\Model\TransactionLineItemUpdateRequest;
use Wallee\Sdk\Model\TransactionState;
use Wallee\Sdk\Service\TransactionService as TransactionApiService;

/**
 * Service to handle transactions in invoice context.
 */
class TransactionService extends AbstractTransactionService
{

    /**
     *
     * @var LocaleHelper
     */
    protected $_localeHelper;

    /**
     *
     * @var LineItemService
     */
    protected $_lineItemService;

    /**
     *
     * @var TransactionInfoRepositoryInterface
     */
    protected $_transactionInfoRepository;

    /**
     *
     * @var OrderTransactionService
     */
    protected $_orderTransactionService;

    /**
     *
     * @param Helper $helper
     * @param ScopeConfigInterface $scopeConfig
     * @param CustomerRegistry $customerRegistry
     * @param CartRepositoryInterface $quoteRepository
     * @param PaymentMethodConfigurationManagementInterface $paymentMethodConfigurationManagement
     * @param ApiClient $apiClient
     * @param LocaleHelper $localeHelper
     * @param LineItemService $lineItemService
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param OrderTransactionService $orderTransactionService
     */
    public function __construct(Helper $helper, ScopeConfigInterface $scopeConfig, CustomerRegistry $customerRegistry,
        CartRepositoryInterface $quoteRepository,
        PaymentMethodConfigurationManagementInterface $paymentMethodConfigurationManagement, ApiClient $apiClient,
        LocaleHelper $localeHelper, LineItemService $lineItemService,
        TransactionInfoRepositoryInterface $transactionInfoRepository, OrderTransactionService $orderTransactionService)
    {
        parent::__construct($helper, $scopeConfig, $customerRegistry, $quoteRepository,
            $paymentMethodConfigurationManagement, $apiClient);
        $this->_localeHelper = $localeHelper;
        $this->_lineItemService = $lineItemService;
        $this->_transactionInfoRepository = $transactionInfoRepository;
        $this->_orderTransactionService = $orderTransactionService;
    }

    /**
     * Updates the transaction's line items from the given invoice.
     *
     * @param Invoice $invoice
     * @param float $expectedAmount
     */
    public function updateLineItems(Invoice $invoice, $expectedAmount)
    {
        $transactionInfo = $this->_transactionInfoRepository->getByOrderId($invoice->getOrderId());
        if ($transactionInfo->getState() == TransactionState::AUTHORIZED) {
            $lineItems = $this->_lineItemService->convertInvoiceLineItems($invoice, $expectedAmount);

            $updateRequest = new TransactionLineItemUpdateRequest();
            $updateRequest->setTransactionId($transactionInfo->getTransactionId());
            $updateRequest->setNewLineItems($lineItems);
            $this->_apiClient->getService(TransactionApiService::class)->updateTransactionLineItems(
                $transactionInfo->getSpaceId(), $updateRequest);
        }
    }

    /**
     * Completes the transaction linked to the given payment's and invoice's order.
     *
     * @param Payment $payment
     * @param Invoice $invoice
     * @param float $amount
     * @throws \Exception
     */
    public function complete(Payment $payment, Invoice $invoice, $amount)
    {
        $this->updateLineItems($invoice, $amount);

        $completion = $this->_orderTransactionService->complete($invoice->getOrder());
        if (! ($completion instanceof TransactionCompletion) ||
            $completion->getState() == TransactionCompletionState::FAILED) {
            throw new \Magento\Framework\Exception\LocalizedException(
                \__('The capture of the invoice failed on the gateway: %1.',
                    $this->_localeHelper->translate(
                        $completion->getFailureReason()
                            ->getDescription())));
        }

        try {
            $transactionInvoice = $this->_orderTransactionService->getTransactionInvoice($invoice->getOrder());
            if ($transactionInvoice instanceof TransactionInvoice &&
                $transactionInvoice->getState() != TransactionInvoiceState::PAID &&
                $transactionInvoice->getState() != TransactionInvoiceState::NOT_APPLICABLE) {
                $invoice->setWalleeCapturePending(true);
            }
        } catch (NoSuchEntityException $e) {}

        $authorizationTransaction = $payment->getAuthorizationTransaction();
        $authorizationTransaction->close(false);
        $invoice->getOrder()
            ->addRelatedObject($invoice)
            ->addRelatedObject($authorizationTransaction);
    }
}