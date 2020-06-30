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
namespace Wallee\Payment\Model\Service\Order;

use Magento\Customer\Model\CustomerRegistry;
use Magento\Framework\DataObject;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Invoice;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use Wallee\Payment\Api\PaymentMethodConfigurationManagementInterface;
use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Wallee\Payment\Helper\Data as Helper;
use Wallee\Payment\Helper\LineItem as LineItemHelper;
use Wallee\Payment\Model\ApiClient;
use Wallee\Payment\Model\CustomerIdManipulationException;
use Wallee\Payment\Model\Service\AbstractTransactionService;
use Wallee\Sdk\VersioningException;
use Wallee\Sdk\Model\AbstractTransactionPending;
use Wallee\Sdk\Model\AddressCreate;
use Wallee\Sdk\Model\CriteriaOperator;
use Wallee\Sdk\Model\EntityQuery;
use Wallee\Sdk\Model\EntityQueryFilter;
use Wallee\Sdk\Model\EntityQueryFilterType;
use Wallee\Sdk\Model\Token;
use Wallee\Sdk\Model\Transaction;
use Wallee\Sdk\Model\TransactionCreate;
use Wallee\Sdk\Model\TransactionInvoiceState;
use Wallee\Sdk\Model\TransactionPending;
use Wallee\Sdk\Model\TransactionState;
use Wallee\Sdk\Service\DeliveryIndicationService;
use Wallee\Sdk\Service\TransactionCompletionService;
use Wallee\Sdk\Service\TransactionInvoiceService;
use Wallee\Sdk\Service\TransactionService as TransactionApiService;
use Wallee\Sdk\Service\TransactionVoidService;

/**
 * Service to handle transactions in order context.
 */
class TransactionService extends AbstractTransactionService
{

    /**
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     *
     * @var Helper
     */
    private $helper;

    /**
     *
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     *
     * @var ManagerInterface
     */
    private $eventManager;

    /**
     *
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     *
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     *
     * @var LineItemService
     */
    private $lineItemService;

    /**
     *
     * @var LineItemHelper
     */
    private $lineItemHelper;

    /**
     *
     * @var TransactionInfoRepositoryInterface
     */
    private $transactionInfoRepository;

    /**
     *
     * @var ApiClient
     */
    private $apiClient;

    /**
     *
     * @param ResourceConnection $resource
     * @param Helper $helper
     * @param ScopeConfigInterface $scopeConfig
     * @param ManagerInterface $eventManager
     * @param CustomerRegistry $customerRegistry
     * @param CartRepositoryInterface $quoteRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param PaymentMethodConfigurationManagementInterface $paymentMethodConfigurationManagement
     * @param ApiClient $apiClient
     * @param CookieManagerInterface $cookieManager
     * @param LoggerInterface $logger
     * @param LineItemService $lineItemService
     * @param LineItemHelper $lineItemHelper
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     */
    public function __construct(ResourceConnection $resource, Helper $helper, ScopeConfigInterface $scopeConfig,
        ManagerInterface $eventManager, CustomerRegistry $customerRegistry, CartRepositoryInterface $quoteRepository,
        OrderRepositoryInterface $orderRepository, TimezoneInterface $timezone,
        PaymentMethodConfigurationManagementInterface $paymentMethodConfigurationManagement, ApiClient $apiClient,
        CookieManagerInterface $cookieManager, LoggerInterface $logger, LineItemService $lineItemService,
        LineItemHelper $lineItemHelper, TransactionInfoRepositoryInterface $transactionInfoRepository)
    {
        parent::__construct($resource, $helper, $scopeConfig, $customerRegistry, $quoteRepository, $timezone,
            $paymentMethodConfigurationManagement, $apiClient, $cookieManager);
        $this->helper = $helper;
        $this->scopeConfig = $scopeConfig;
        $this->eventManager = $eventManager;
        $this->quoteRepository = $quoteRepository;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
        $this->lineItemService = $lineItemService;
        $this->lineItemHelper = $lineItemHelper;
        $this->transactionInfoRepository = $transactionInfoRepository;
        $this->apiClient = $apiClient;
    }

    /**
     * Updates the transaction with the given order's data and confirms it.
     *
     * @param Transaction $transaction
     * @param Order $order
     * @param Invoice $invoice
     * @param boolean $chargeFlow
     * @param Token $token
     * @throws VersioningException
     * @return Transaction
     */
    public function confirmTransaction(Transaction $transaction, Order $order, Invoice $invoice, $chargeFlow = false,
        Token $token = null)
    {
        if ($transaction->getState() == TransactionState::CONFIRMED) {
            return $transaction;
        } elseif ($transaction->getState() != TransactionState::PENDING) {
            $this->cancelOrder($order, $invoice);
            throw new LocalizedException(\__('wallee_checkout_failure'));
        }

        $spaceId = $order->getWalleeSpaceId();
        $transactionId = $order->getWalleeTransactionId();
        for ($i = 0; $i < 5; $i ++) {
            try {
                if ($i > 0) {
                    $transaction = $this->getTransaction($spaceId, $transactionId);
                    if ($transaction instanceof Transaction && $transaction->getState() == TransactionState::CONFIRMED) {
                        return $transaction;
                    } elseif (! ($transaction instanceof Transaction) ||
                        $transaction->getState() != TransactionState::PENDING) {
                        $this->cancelOrder($order, $invoice);
                        throw new LocalizedException(\__('wallee_checkout_failure'));
                    }
                }

                if (! empty($transaction->getCustomerId()) && $transaction->getCustomerId() != $order->getCustomerId()) {
                    throw new CustomerIdManipulationException();
                }

                $pendingTransaction = new TransactionPending();
                $pendingTransaction->setId($transaction->getId());
                $pendingTransaction->setVersion($transaction->getVersion());
                $this->assembleTransactionDataFromOrder($pendingTransaction, $order, $invoice, $chargeFlow, $token);
                return $this->apiClient->getService(TransactionApiService::class)->confirm($spaceId, $pendingTransaction);
            } catch (VersioningException $e) {
                // Try to update the transaction again, if a versioning exception occurred.
            }
        }
        throw new VersioningException();
    }

    /**
     * Cancels the given order and invoice linked to the transaction.
     *
     * @param Order $order
     * @param Invoice $invoice
     */
    private function cancelOrder(Order $order, Invoice $invoice)
    {
        if ($invoice) {
            $order->setWalleeInvoiceAllowManipulation(true);
            $invoice->cancel();
            $order->addRelatedObject($invoice);
        }
        $order->registerCancellation(null, false);
        $this->orderRepository->save($order);
    }

    /**
     * Assembles the transaction data from the given order and invoice.
     *
     * @param AbstractTransactionPending $transaction
     * @param Order $order
     * @param Invoice $invoice
     * @param boolean $chargeFlow
     * @param Token $token
     */
    protected function assembleTransactionDataFromOrder(AbstractTransactionPending $transaction, Order $order,
        Invoice $invoice, $chargeFlow = false, Token $token = null)
    {
        $transaction->setCurrency($order->getOrderCurrencyCode());
        $transaction->setBillingAddress($this->convertOrderBillingAddress($order));
        $transaction->setShippingAddress($this->convertOrderShippingAddress($order));
        $transaction->setCustomerEmailAddress(
            $this->getCustomerEmailAddress($order->getCustomerEmail(), $order->getCustomerId()));
        $transaction->setLanguage(
            $this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE, $order->getStoreId()));
        $transaction->setLineItems($this->lineItemService->convertOrderLineItems($order));
        $this->logAdjustmentLineItemInfo($order, $transaction);
        $transaction->setMerchantReference($order->getIncrementId());
        $transaction->setInvoiceMerchantReference($invoice->getIncrementId());
        if (! empty($order->getCustomerId())) {
            $transaction->setCustomerId($order->getCustomerId());
        }
        if ($order->getShippingAddress()) {
            $transaction->setShippingMethod(
                $this->helper->fixLength(
                    $this->helper->getFirstLine($order->getShippingAddress()
                        ->getShippingDescription()), 200));
        }
        if ($transaction instanceof TransactionCreate) {
            $transaction->setSpaceViewId(
                $this->scopeConfig->getValue('wallee_payment/general/space_view_id',
                    ScopeInterface::SCOPE_STORE, $order->getStoreId()));
            $transaction->setDeviceSessionIdentifier($this->getDeviceSessionIdentifier());
        }
        if ($chargeFlow) {
            $transaction->setAllowedPaymentMethodConfigurations(
                [
                    $order->getPayment()
                        ->getMethodInstance()
                        ->getPaymentMethodConfiguration()
                        ->getConfigurationId()
                ]);
        } else {
            $transaction->setSuccessUrl(
                $this->buildUrl('wallee_payment/transaction/success', $order) . '?utm_nooverride=1');
            $transaction->setFailedUrl(
                $this->buildUrl('wallee_payment/transaction/failure', $order) . '?utm_nooverride=1');
        }
        if ($token != null) {
            $transaction->setToken($token->getId());
        }
        $metaData = $this->collectMetaData($order);
        if (! empty($metaData) && is_array($metaData)) {
            $transaction->setMetaData($metaData);
        }
    }

    /**
     * Checks whether an adjustment line item has been added to the transaction and adds a log message if so.
     *
     * @param Order $order
     * @param TransactionPending $transaction
     */
    protected function logAdjustmentLineItemInfo(Order $order, TransactionPending $transaction)
    {
        foreach ($transaction->getLineItems() as $lineItem) {
            if ($lineItem->getUniqueId() == 'adjustment') {
                $expectedSum = $this->lineItemHelper->getTotalAmountIncludingTax($transaction->getLineItems()) -
                    $lineItem->getAmountIncludingTax();
                $this->logger->warning(
                    'An adjustment line item has been added to the transaction ' . $transaction->getId() .
                    ', because the line item total amount of ' .
                    $this->helper->roundAmount($order->getGrandTotal(), $order->getOrderCurrencyCode()) .
                    ' did not match the invoice amount of ' . $expectedSum . ' of the order ' . $order->getId() . '.');
                return;
            }
        }
    }

    protected function collectMetaData(Order $order)
    {
        $transport = new DataObject([
            'metaData' => []
        ]);
        $this->eventManager->dispatch('wallee_payment_collect_meta_data',
            [
                'transport' => $transport,
                'order' => $order
            ]);
        return $transport->getData('metaData');
    }

    /**
     * Builds the URL to an endpoint that is aware of the given order.
     *
     * @param string $route
     * @param Order $order
     * @throws \Exception
     * @return string
     */
    protected function buildUrl($route, Order $order)
    {
        $token = $order->getWalleeSecurityToken();
        if (empty($token)) {
            throw new LocalizedException(
                \__('The wallee security token needs to be set on the order to build the URL.'));
        }

        return $order->getStore()->getUrl($route,
            [
                '_secure' => true,
                'order_id' => $order->getId(),
                'token' => $token
            ]);
    }

    /**
     * Converts the billing address of the given order.
     *
     * @param Order $order
     * @return \Wallee\Sdk\Model\AddressCreate
     */
    protected function convertOrderBillingAddress(Order $order)
    {
        if (! $order->getBillingAddress()) {
            return null;
        }

        $address = $this->convertAddress($order->getBillingAddress());
        $address->setDateOfBirth($this->getDateOfBirth($order->getCustomerDob(), $order->getCustomerId()));
        $address->setEmailAddress($this->getCustomerEmailAddress($order->getCustomerEmail(), $order->getCustomerId()));
        $address->setGender($this->getGender($order->getCustomerGender(), $order->getCustomerId()));
        return $address;
    }

    /**
     * Converts the shipping address of the given order.
     *
     * @param Order $order
     * @return \Wallee\Sdk\Model\AddressCreate
     */
    protected function convertOrderShippingAddress(Order $order)
    {
        if (! $order->getShippingAddress()) {
            return null;
        }

        $address = $this->convertAddress($order->getShippingAddress());
        $address->setEmailAddress($this->getCustomerEmailAddress($order->getCustomerEmail(), $order->getCustomerId()));
        return $address;
    }

    /**
     * Converts the given address.
     *
     * @param Address $customerAddress
     * @return AddressCreate
     */
    protected function convertAddress(Address $customerAddress)
    {
        $address = new AddressCreate();
        $address->setSalutation(
            $this->helper->fixLength($this->helper->removeLinebreaks($customerAddress->getPrefix()), 20));
        $address->setCity($this->helper->fixLength($this->helper->removeLinebreaks($customerAddress->getCity()), 100));
        $address->setCountry($customerAddress->getCountryId());
        $address->setFamilyName(
            $this->helper->fixLength($this->helper->removeLinebreaks($customerAddress->getLastname()), 100));
        $address->setGivenName(
            $this->helper->fixLength($this->helper->removeLinebreaks($customerAddress->getFirstname()), 100));
        $address->setOrganizationName(
            $this->helper->fixLength($this->helper->removeLinebreaks($customerAddress->getCompany()), 100));
        $address->setPhoneNumber($customerAddress->getTelephone());
        if (! empty($customerAddress->getCountryId()) && ! empty($customerAddress->getRegionCode())) {
            $address->setPostalState($customerAddress->getCountryId() . '-' . $customerAddress->getRegionCode());
        }
        $address->setPostCode(
            $this->helper->fixLength($this->helper->removeLinebreaks($customerAddress->getPostcode()), 40));
        $street = $customerAddress->getStreet();
        $address->setStreet($this->helper->fixLength(\is_array($street) ? \implode("\n", $street) : $street, 300));
        return $address;
    }

    /**
     * Completes the transaction linked to the given order.
     *
     * @param Order $order
     * @return \Wallee\Sdk\Model\TransactionCompletion
     */
    public function complete(Order $order)
    {
        return $this->apiClient->getService(TransactionCompletionService::class)->completeOnline(
            $order->getWalleeSpaceId(), $order->getWalleeTransactionId());
    }

    /**
     * Voids the transaction linked to the given order.
     *
     * @param Order $order
     * @return \Wallee\Sdk\Model\TransactionVoid
     */
    public function void(Order $order)
    {
        return $this->apiClient->getService(TransactionVoidService::class)->voidOnline(
            $order->getWalleeSpaceId(), $order->getWalleeTransactionId());
    }

    /**
     * Marks the delivery indication belonging to the given payment as suitable.
     *
     * @param Order $order
     * @return \Wallee\Sdk\Model\DeliveryIndication
     */
    public function accept(Order $order)
    {
        return $this->apiClient->getService(DeliveryIndicationService::class)->markAsSuitable(
            $order->getWalleeSpaceId(), $this->getDeliveryIndication($order)
                ->getId());
    }

    /**
     * Marks the delivery indication belonging to the given payment as not suitable.
     *
     * @param Order $order
     * @return \Wallee\Sdk\Model\DeliveryIndication
     */
    public function deny(Order $order)
    {
        return $this->apiClient->getService(DeliveryIndicationService::class)->markAsNotSuitable(
            $order->getWalleeSpaceId(), $this->getDeliveryIndication($order)
                ->getId());
    }

    /**
     *
     * @param Order $order
     * @return \Wallee\Sdk\Model\DeliveryIndication
     */
    protected function getDeliveryIndication(Order $order)
    {
        $query = new EntityQuery();
        $query->setFilter(
            $this->helper->createEntityFilter('transaction.id', $order->getWalleeTransactionId()));
        $query->setNumberOfEntities(1);
        return \current(
            $this->apiClient->getService(DeliveryIndicationService::class)->search(
                $order->getWalleeSpaceId(), $query));
    }

    /**
     * Gets the transaction invoice linked to the given order.
     *
     * @param Order $order
     * @throws \Exception
     * @return \Wallee\Sdk\Model\TransactionInvoice
     */
    public function getTransactionInvoice(Order $order)
    {
        $query = new EntityQuery();
        $filter = new EntityQueryFilter();
        $filter->setType(EntityQueryFilterType::_AND);
        $filter->setChildren(
            [
                $this->helper->createEntityFilter('state', TransactionInvoiceState::CANCELED,
                    CriteriaOperator::NOT_EQUALS),
                $this->helper->createEntityFilter('completion.lineItemVersion.transaction.id',
                    $order->getWalleeTransactionId())
            ]);
        $query->setFilter($filter);
        $query->setNumberOfEntities(1);
        $result = $this->apiClient->getService(TransactionInvoiceService::class)->search(
            $order->getWalleeSpaceId(), $query);
        if (! empty($result)) {
            return $result[0];
        } else {
            throw new NoSuchEntityException();
        }
    }

    /**
     * Waits for the transaction to be in one of the given states.
     *
     * @param Order $order
     * @param array $states
     * @param int $maxWaitTime
     * @return boolean
     */
    public function waitForTransactionState(Order $order, array $states, $maxWaitTime = 10)
    {
        $startTime = \microtime(true);
        while (true) {
            if (\microtime(true) - $startTime >= $maxWaitTime) {
                return false;
            }

            $transactionInfo = $this->transactionInfoRepository->getByOrderId($order->getId());
            if (\in_array($transactionInfo->getState(), $states)) {
                return true;
            }

            \sleep(2);
        }
    }
}