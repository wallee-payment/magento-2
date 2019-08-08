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
namespace Wallee\Payment\Model\Service\Quote;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Store\Model\ScopeInterface;
use Wallee\Payment\Api\PaymentMethodConfigurationManagementInterface;
use Wallee\Payment\Helper\Data as Helper;
use Wallee\Payment\Model\ApiClient;
use Wallee\Payment\Model\Service\AbstractTransactionService;
use Wallee\Sdk\ApiException;
use Wallee\Sdk\VersioningException;
use Wallee\Sdk\Model\AbstractTransactionPending;
use Wallee\Sdk\Model\AddressCreate;
use Wallee\Sdk\Model\CustomersPresence;
use Wallee\Sdk\Model\Transaction;
use Wallee\Sdk\Model\TransactionCreate;
use Wallee\Sdk\Model\TransactionPending;
use Wallee\Sdk\Model\TransactionState;
use Wallee\Sdk\Service\TransactionService as TransactionApiService;

/**
 * Service to handle transactions in quote context.
 */
class TransactionService extends AbstractTransactionService
{

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
     * @var CustomerRegistry
     */
    private $customerRegistry;

    /**
     *
     * @var ApiClient
     */
    private $apiClient;

    /**
     *
     * @var LineItemService
     */
    private $lineItemService;

    /**
     *
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     *
     * @var \Wallee\Sdk\Model\Transaction[]
     */
    private $transactionCache = [];

    /**
     *
     * @var \Wallee\Sdk\Model\PaymentMethodConfiguration[]
     */
    private $possiblePaymentMethodCache = [];

    /**
     *
     * @param ResourceConnection $resource
     * @param Helper $helper
     * @param ScopeConfigInterface $scopeConfig
     * @param CustomerRegistry $customerRegistry
     * @param CartRepositoryInterface $quoteRepository
     * @param PaymentMethodConfigurationManagementInterface $paymentMethodConfigurationManagement
     * @param ApiClient $apiClient
     * @param CookieManagerInterface $cookieManager
     * @param CookieManagerInterface $cookieManager
     * @param LineItemService $lineItemService
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(ResourceConnection $resource, Helper $helper, ScopeConfigInterface $scopeConfig,
        CustomerRegistry $customerRegistry, CartRepositoryInterface $quoteRepository, TimezoneInterface $timezone,
        PaymentMethodConfigurationManagementInterface $paymentMethodConfigurationManagement, ApiClient $apiClient,
        CookieManagerInterface $cookieManager, LineItemService $lineItemService, CheckoutSession $checkoutSession)
    {
        parent::__construct($resource, $helper, $scopeConfig, $customerRegistry, $quoteRepository, $timezone,
            $paymentMethodConfigurationManagement, $apiClient, $cookieManager);
        $this->helper = $helper;
        $this->scopeConfig = $scopeConfig;
        $this->customerRegistry = $customerRegistry;
        $this->apiClient = $apiClient;
        $this->lineItemService = $lineItemService;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Gets the URL to the JavaScript library that is required to display the payment form.
     *
     * @param Quote $quote
     * @return string
     */
    public function getJavaScriptUrl(Quote $quote)
    {
        $transaction = $this->getTransactionByQuote($quote);
        return $this->apiClient->getService(TransactionApiService::class)->buildJavaScriptUrl(
            $transaction->getLinkedSpaceId(), $transaction->getId());
    }

    /**
     * Gets the URL to the payment page.
     *
     * @param Quote $quote
     * @return string
     */
    public function getPaymentPageUrl(Quote $quote)
    {
        $transaction = $this->getTransactionByQuote($quote);
        return $this->apiClient->getService(TransactionApiService::class)->buildPaymentPageUrl(
            $transaction->getLinkedSpaceId(), $transaction->getId());
    }

    /**
     * Gets the payment methods that can be used with the given quote.
     *
     * @param Quote $quote
     * @return \Wallee\Sdk\Model\PaymentMethodConfiguration[]
     */
    public function getPossiblePaymentMethods(Quote $quote)
    {
        if (! \array_key_exists($quote->getId(), $this->possiblePaymentMethodCache) ||
            $this->possiblePaymentMethodCache[$quote->getId()] == null) {
            $transaction = $this->getTransactionByQuote($quote);
            try {
                $paymentMethods = $this->apiClient->getService(TransactionApiService::class)->fetchPossiblePaymentMethods(
                    $transaction->getLinkedSpaceId(), $transaction->getId());
            } catch (ApiException $e) {
                $this->possiblePaymentMethodCache[$quote->getId()] = [];
                throw $e;
            }
            $this->updatePaymentMethodConfigurations($paymentMethods);
            $this->possiblePaymentMethodCache[$quote->getId()] = $paymentMethods;
        }
        return $this->possiblePaymentMethodCache[$quote->getId()];
    }

    /**
     * Gets the transaction for the given quote.
     *
     * If there is not transaction for the quote, a new one is created.
     *
     * @param Quote $quote
     * @return Transaction
     */
    public function getTransactionByQuote(Quote $quote)
    {
        if (! \array_key_exists($quote->getId(), $this->transactionCache) ||
            $this->transactionCache[$quote->getId()] == null) {
            $transactionId = $quote->getWalleeTransactionId();
            if (empty($transactionId)) {
                $this->transactionCache[$quote->getId()] = $this->createTransactionByQuote($quote);
            } else {
                $this->transactionCache[$quote->getId()] = $this->updateTransactionByQuote($quote);
            }
        }
        return $this->transactionCache[$quote->getId()];
    }

    /**
     * Creates a transaction for the given quote.
     *
     * @param Quote $quote
     * @return Transaction
     */
    private function createTransactionByQuote(Quote $quote)
    {
        $spaceId = $this->scopeConfig->getValue('wallee_payment/general/space_id',
            ScopeInterface::SCOPE_STORE, $quote->getStoreId());

        $createTransaction = new TransactionCreate();
        $createTransaction->setCustomersPresence(CustomersPresence::VIRTUAL_PRESENT);
        $createTransaction->setAutoConfirmationEnabled(false);
        $this->assembleTransactionDataFromQuote($createTransaction, $quote);
        $transaction = $this->apiClient->getService(TransactionApiService::class)->create($spaceId, $createTransaction);
        $this->updateQuote($quote, $transaction);
        return $transaction;
    }

    /**
     * Updates the transaction with the given quote's data.
     *
     * @param Quote $quote
     * @throws VersioningException
     * @return Transaction
     */
    private function updateTransactionByQuote(Quote $quote)
    {
        for ($i = 0; $i < 5; $i ++) {
            try {
                $transaction = $this->apiClient->getService(TransactionApiService::class)->read(
                    $quote->getWalleeSpaceId(), $quote->getWalleeTransactionId());
                if (! ($transaction instanceof Transaction) || $transaction->getState() != TransactionState::PENDING) {
                    return $this->createTransactionByQuote($quote);
                }

                $pendingTransaction = new TransactionPending();
                $pendingTransaction->setId($transaction->getId());
                $pendingTransaction->setVersion($transaction->getVersion());
                $this->assembleTransactionDataFromQuote($pendingTransaction, $quote);
                return $this->apiClient->getService(TransactionApiService::class)->update(
                    $quote->getWalleeSpaceId(), $pendingTransaction);
            } catch (VersioningException $e) {
                // Try to update the transaction again, if a versioning exception occurred.
            }
        }
        throw new VersioningException();
    }

    /**
     * Assembles the transaction data from the given quote.
     *
     * @param AbstractTransactionPending $transaction
     * @param Quote $quote
     */
    private function assembleTransactionDataFromQuote(AbstractTransactionPending $transaction, Quote $quote)
    {
        $quote->collectTotals();

        $transaction->setAllowedPaymentMethodConfigurations([]);
        $transaction->setCurrency($quote->getQuoteCurrencyCode());
        $transaction->setBillingAddress($this->convertQuoteBillingAddress($quote));
        $transaction->setShippingAddress($this->convertQuoteShippingAddress($quote));
        $transaction->setCustomerEmailAddress(
            $this->getCustomerEmailAddress($quote->getCustomerEmail(), $quote->getCustomerId()));
        $transaction->setLanguage(
            $this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE, $quote->getStoreId()));
        $transaction->setLineItems($this->lineItemService->convertQuoteLineItems($quote));
        if (! empty($quote->getCustomerId())) {
            $transaction->setCustomerId($quote->getCustomerId());
        }
        if ($quote->getShippingAddress()) {
            $transaction->setShippingMethod(
                $this->helper->fixLength(
                    $this->helper->getFirstLine($quote->getShippingAddress()
                        ->getShippingDescription()), 200));
        }

        if ($transaction instanceof TransactionCreate) {
            $transaction->setSpaceViewId(
                $this->scopeConfig->getValue('wallee_payment/general/store_view_id',
                    ScopeInterface::SCOPE_STORE, $quote->getStoreId()));
            $transaction->setDeviceSessionIdentifier($this->getDeviceSessionIdentifier());
        }
    }

    protected function getCustomerEmailAddress($customerEmailAddress, $customerId)
    {
        $emailAddress = parent::getCustomerEmailAddress($customerEmailAddress, $customerId);
        if (! empty($emailAddress)) {
            return $emailAddress;
        } else {
            return $this->checkoutSession->getWalleeCheckoutEmailAddress();
        }
    }

    /**
     * Converts the billing address of the given quote.
     *
     * @param Quote $quote
     * @return \Wallee\Sdk\Model\AddressCreate
     */
    private function convertQuoteBillingAddress(Quote $quote)
    {
        if (! $quote->getBillingAddress()) {
            return null;
        }

        $address = $this->convertAddress($quote->getBillingAddress());
        $address->setDateOfBirth($this->getDateOfBirth($quote->getCustomerDob(), $quote->getCustomerId()));
        $address->setEmailAddress($this->getCustomerEmailAddress($quote->getCustomerEmail(), $quote->getCustomerId()));
        $address->setGender($this->getGender($quote->getCustomerGender(), $quote->getCustomerId()));
        $address->setSalesTaxNumber($this->getTaxNumber($quote->getCustomerTaxvat(), $quote->getCustomerId()));
        return $address;
    }

    /**
     * Converts the shipping address of the given quote.
     *
     * @param Quote $quote
     * @return \Wallee\Sdk\Model\AddressCreate
     */
    private function convertQuoteShippingAddress(Quote $quote)
    {
        if (! $quote->getShippingAddress()) {
            return null;
        }

        $address = $this->convertAddress($quote->getShippingAddress());
        $address->setEmailAddress($this->getCustomerEmailAddress($quote->getCustomerEmail(), $quote->getCustomerId()));
        return $address;
    }

    /**
     * Converts the given address.
     *
     * @param Address $customerAddress
     * @return AddressCreate
     */
    private function convertAddress(Address $customerAddress)
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
        $address->setPostalState($customerAddress->getRegionCode());
        $address->setPostCode(
            $this->helper->fixLength($this->helper->removeLinebreaks($customerAddress->getPostcode()), 40));
        $address->setStreet($this->helper->fixLength($customerAddress->getStreetFull(), 300));
        return $address;
    }
}