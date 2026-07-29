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
namespace Wallee\Payment\Model\Service\Quote;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Store\Model\ScopeInterface;
use Wallee\Payment\Helper\Data as Helper;
use Wallee\Payment\Model\ApiClient;
use Wallee\Payment\Model\CustomerIdManipulationException;
use Wallee\Payment\Model\PluginCore\QuoteTransactionPersistenceFactory;
use Wallee\Payment\Model\Service\AbstractTransactionService;
use Wallee\PluginCore\Address\Address as CoreAddress;
use Wallee\PluginCore\Customer\CompanyDetails;
use Wallee\PluginCore\Customer\Gender;
use Wallee\PluginCore\Customer\PersonalDetails;
use Wallee\PluginCore\LineItem\LineItemCollection;
use Wallee\PluginCore\Transaction\CustomersPresence;
use Wallee\PluginCore\Transaction\State as CoreState;
use Wallee\PluginCore\Transaction\Transaction as CoreTransaction;
use Wallee\PluginCore\Transaction\TransactionContext;
use Wallee\PluginCore\Transaction\TransactionService as CoreTransactionService;
use Wallee\PluginCore\SharedKernel\AbstractDomainException;
use Wallee\PluginCore\Log\LoggerInterface;

/**
 * Service to handle transactions in quote context.
 *
 * The transaction CRUD path is delegated to
 * {@see CoreTransactionService::upsert()} and integration-mode URL retrieval
 * to {@see CoreTransactionService::getPaymentUrl()}, which resolves the
 * configured integration mode (iframe/lightbox/payment page) internally.
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
     * @var bool
     */
    private $submittingOrder = false;

    /**
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Plugin-core transaction service used for create/update/read operations.
     *
     * @var CoreTransactionService
     */
    private $pluginCoreTransactionService;

    /**
     * Factory that builds a per-quote persistence strategy for plugin-core upserts.
     *
     * @var QuoteTransactionPersistenceFactory
     */
    private $persistenceFactory;

    /**
     *
     * @param Helper $helper
     * @param ScopeConfigInterface $scopeConfig
     * @param CustomerRegistry $customerRegistry
     * @param ApiClient $apiClient
     * @param CookieManagerInterface $cookieManager
     * @param LineItemService $lineItemService
     * @param CheckoutSession $checkoutSession
     * @param LoggerInterface $logger
     * @param CoreTransactionService $pluginCoreTransactionService
     * @param QuoteTransactionPersistenceFactory $persistenceFactory
     */
    public function __construct(
        Helper $helper,
        ScopeConfigInterface $scopeConfig,
        CustomerRegistry $customerRegistry,
        ApiClient $apiClient,
        CookieManagerInterface $cookieManager,
        LineItemService $lineItemService,
        CheckoutSession $checkoutSession,
        LoggerInterface $logger,
        CoreTransactionService $pluginCoreTransactionService,
        QuoteTransactionPersistenceFactory $persistenceFactory
    ) {
        parent::__construct(
            $customerRegistry,
            $apiClient,
            $cookieManager
        );
        $this->helper = $helper;
        $this->scopeConfig = $scopeConfig;
        $this->lineItemService = $lineItemService;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
        $this->pluginCoreTransactionService = $pluginCoreTransactionService;
        $this->persistenceFactory = $persistenceFactory;
    }

    /**
     * Returns the cached payment URL from the checkout session, if it still
     * matches the transaction id currently attached to the quote.
     *
     * @param Quote $quote
     * @return string|null
     */
    private function getPaymentUrlInSession(Quote $quote): ?string
    {
        $url = $this->checkoutSession->getPaymentUrl();
        if (!$url) {
            return null;
        }
        $transactionId = $quote->getWalleeTransactionId();
        if (!preg_match('/transactionId=(\d+)/', $url, $matches)
            || !isset($matches[1])
            || $matches[1] != $transactionId) {
            return null;
        }
        if ($this->isValidateTransactionOnCheckoutEnabled($quote->getStoreId()) && !$this->checkTransactionIsStillAvailable($quote)) {
            $this->checkoutSession->unsPaymentUrl();
            $this->checkoutSession->unsTransaction();
            return null;
        }
        if ($this->isTokenExpired($url)) {
            $this->checkoutSession->unsPaymentUrl();
            $this->checkoutSession->unsTransaction();
            return null;
        }
        return $url;
    }

    /**
     * Retrieves the security token from the payment URL and checks its expiration.
     *
     * @param string $url
     * @return bool
     */
    private function isTokenExpired(string $url)
    {
        if (!preg_match('/securityToken=(\d+)-[^&]+/', $url, $matches)) {
            return true;
        }
        $tokenExpiryTime = (int) $matches[1];
        $tokenExpiryTime = intdiv($tokenExpiryTime, 1000);
        return time() >= $tokenExpiryTime;
    }

    /**
     * Gets the URL to the JavaScript library required to display the iframe payment form.
     *
     * @param Quote $quote
     * @return string
     */
    public function getJavaScriptUrl(Quote $quote): string
    {
        return $this->resolvePaymentUrl($quote);
    }

    /**
     * Gets the URL to the JavaScript library required to display the lightbox payment form.
     *
     * @param Quote $quote
     * @return string
     */
    public function getLightboxUrl(Quote $quote): string
    {
        return $this->resolvePaymentUrl($quote);
    }

    /**
     * Gets the URL to the hosted payment page.
     *
     * @param Quote $quote
     * @return string
     */
    public function getPaymentPageUrl(Quote $quote): string
    {
        return $this->resolvePaymentUrl($quote);
    }

    /**
     * Resolves the payment URL for the quote through plugin-core, which selects
     * the endpoint (iframe / lightbox / payment page) based on the configured
     * integration mode. The result is cached in the checkout session.
     *
     * @param Quote $quote
     * @return string
     */
    private function resolvePaymentUrl(Quote $quote): string
    {
        $url = $this->getPaymentUrlInSession($quote);
        if ($url !== null) {
            $this->logger->debug("Payment URL already exists: " . $url);
            return $url;
        }

        $transaction = $this->getTransactionByQuote($quote);
        $url = (string) $this->pluginCoreTransactionService->getPaymentUrl(
            $transaction->spaceId,
            $transaction->id
        );
        $this->checkoutSession->setPaymentUrl($url);
        $this->logger->debug("Generated new payment URL: " . $url);
        return $url;
    }

    /**
     * Gets the payment methods that can be used with the given quote.
     *
     * @param Quote $quote
     * @return \Wallee\PluginCore\PaymentMethod\PaymentMethodCollection
     * @throws AbstractDomainException
     * @throws \Wallee\Payment\Model\ApiClientException
     */
    public function getPossiblePaymentMethods(Quote $quote)
    {
        if (!$this->isGdprEnabled($quote->getStoreId())) {
            // Unconditionally push the quote's current data before fetching payment
            // methods — otherwise a cached transaction (e.g. created before the
            // customer entered their address) would never get refreshed here.
            // The result is cached directly (rather than re-reading the possibly
            // still-stale session cache via getTransactionByQuote()) so that a
            // transaction which fell back to CREATE — e.g. because the previous
            // one was no longer PENDING — is what's actually used below.
            $transaction = $this->cacheTransaction($quote, $this->upsertByQuote($quote));
        } else {
            $transaction = $this->getTransactionByQuote($quote);
        }

        try {
            $paymentMethods = $this->pluginCoreTransactionService->getAvailablePaymentMethods(
                $transaction->spaceId,
                $transaction->id
            );
        } catch (AbstractDomainException $e) {
            $paymentMethodsArray[$quote->getId()] = null;
            try {
                $this->checkoutSession->setPaymentMethods($paymentMethodsArray);
            } catch (LocalizedException $ignored) {
                $this->logger->debug(
                    "An issue occurred while setting the payment methods to session.",
                    ['exception' => $e]
                );
            }
            throw $e;
        }
        return $paymentMethods;
    }

    /**
     * Gets the transaction for the given quote, creating or updating it as
     * appropriate via plugin-core's upsert flow.
     *
     * @param Quote $quote
     * @return CoreTransaction
     */
    public function getTransactionByQuote(Quote $quote): CoreTransaction
    {
        $transactionArray = $this->getTransactionArrayFromSession();
        if (!\array_key_exists($quote->getId(), $transactionArray)
            || $transactionArray[$quote->getId()] === null
            || !($transactionArray[$quote->getId()] instanceof CoreTransaction)
        ) {
            $this->logger->debug("No cached transaction for quote; upserting with current quote data.", [
                'quoteId' => $quote->getId(),
            ]);
            return $this->cacheTransaction($quote, $this->upsertByQuote($quote));
        }

        $this->logger->debug(
            "Reusing cached transaction for quote from session; portal is not contacted.",
            [
                'quoteId' => $quote->getId(),
                'transactionId' => $transactionArray[$quote->getId()]->id,
                'state' => $transactionArray[$quote->getId()]->state->value,
            ]
        );
        
        if (
            $this->isValidateTransactionOnCheckoutEnabled($quote->getStoreId()) && 
            !$this->checkTransactionIsStillAvailable($quote)
        ) {
            $transactionArray[$quote->getId()] = $this->upsertByQuote($quote);
            try {
                $this->checkoutSession->setTransaction($transactionArray);
            } catch (LocalizedException $ignored) {
                $this->logger->debug(
                    "An issue occurred while setting the transaction to session.",
                    ['exception' => $ignored]
                );
            }
        }
        return $transactionArray[$quote->getId()];
    }

    /**
     * Writes the given transaction into the per-quote session cache and returns it.
     *
     * @param Quote $quote
     * @param CoreTransaction $transaction
     * @return CoreTransaction
     */
    private function cacheTransaction(Quote $quote, CoreTransaction $transaction): CoreTransaction
    {
        $transactionArray = $this->getTransactionArrayFromSession();
        $transactionArray[$quote->getId()] = $transaction;
        try {
            $this->checkoutSession->setTransaction($transactionArray);
        } catch (LocalizedException $ignored) {
            $this->logger->debug("An issue occurred while setting the transaction to session.");
        }
        return $transaction;
    }

    /**
     * Checks whether the cached PENDING transaction is still usable on the
     * portal (i.e. has not been declined, failed, or replaced).
     *
     * @param Quote $quote
     * @return bool
     */
    public function checkTransactionIsStillAvailable(Quote $quote): bool
    {
        $transactionArray = $this->getTransactionArrayFromSession();
        if (!isset($transactionArray[$quote->getId()]) || $transactionArray[$quote->getId()] === null) {
            return true;
        }

        $cached = $transactionArray[$quote->getId()];
        if (!($cached instanceof CoreTransaction) || $cached->state !== CoreState::PENDING) {
            return true;
        }

        try {
            $current = $this->pluginCoreTransactionService->getTransaction(
                (int) $quote->getWalleeSpaceId(),
                (int) $quote->getWalleeTransactionId()
            );
        } catch (\Throwable $e) {
            $this->logger->debug("Failed to refresh cached transaction state.", ['exception' => $e]);
            return true;
        }

        if (in_array($current->state, [CoreState::DECLINE, CoreState::FAILED], true)) {
            return false;
        }

        return $current->id === $cached->id;
    }

    /**
     * Marks the service as being inside an order submission cycle, which
     * enables stricter customer-id checks when reusing existing transactions.
     *
     * @return void
     */
    public function setSubmittingOrder(): void
    {
        $this->submittingOrder = true;
    }

    /**
     * Builds the transaction context for the given quote and delegates the
     * create-or-update decision to plugin-core's transaction service.
     *
     * @param Quote $quote
     * @return CoreTransaction
     * @throws CustomerIdManipulationException
     */
    private function upsertByQuote(Quote $quote): CoreTransaction
    {
        $spaceId = (int) $this->scopeConfig->getValue(
            'wallee_payment/general/space_id',
            ScopeInterface::SCOPE_STORE,
            $quote->getStoreId()
        );

        $cachedSpaceId = (int) $quote->getWalleeSpaceId();
        $cachedTransactionId = (int) $quote->getWalleeTransactionId();

        $context = $this->buildContext($quote, $spaceId);

        if ($cachedSpaceId === $spaceId && $cachedTransactionId > 0) {
            $context->transactionId = $cachedTransactionId;

            if ($this->submittingOrder) {
                $this->guardAgainstCustomerIdManipulation($quote, $spaceId, $cachedTransactionId);
            }
        }

        $this->logger->debug("Upserting transaction for quote.", [
            'quoteId' => $quote->getId(),
            'spaceId' => $spaceId,
            'existingTransactionId' => $context->transactionId,
            'hasBillingAddress' => $context->billingAddress !== null,
            'hasShippingAddress' => $context->shippingAddress !== null,
        ]);

        // In case we create a new transaction, we want to clear the cached payment URL so that it is regenerated for the new transaction.
        if (empty($cachedTransactionId) || $cachedTransactionId == 0) {
            $this->logger->debug("Clearing checkout payment URL.");
            $this->checkoutSession->setPaymentUrl(null);
        }

        $persistence = $this->persistenceFactory->create($quote, $spaceId);
        return $this->pluginCoreTransactionService->upsert($context, $persistence);
    }

    /**
     * Refuses to recycle a transaction that belongs to a different customer.
     *
     * Only relevant during order submission; outside of submit, plugin-core's
     * upsert falls back to creating a fresh transaction.
     *
     * @param Quote $quote
     * @param int $spaceId
     * @param int $transactionId
     * @return void
     * @throws CustomerIdManipulationException
     */
    private function guardAgainstCustomerIdManipulation(Quote $quote, int $spaceId, int $transactionId): void
    {
        try {
            $existing = $this->pluginCoreTransactionService->getTransaction($spaceId, $transactionId);
        } catch (\Throwable $e) {
            $this->logger->debug("Could not load existing transaction during submit guard.", ['exception' => $e]);
            return;
        }

        if ($existing->customerId !== null
            && $existing->customerId !== ''
            && $existing->customerId !== (string) $quote->getCustomerId()
        ) {
            throw new CustomerIdManipulationException();
        }
    }

    /**
     * Builds the plugin-core transaction context (DTO) from the given quote.
     *
     * @param Quote $quote
     * @param int $spaceId
     * @return TransactionContext
     */
    private function buildContext(Quote $quote, int $spaceId): TransactionContext
    {
        $quote->collectTotals();

        $context = new TransactionContext();
        $context->spaceId = $spaceId;
        $context->merchantReference = (string) $quote->getId();
        $context->currencyCode = (string) $quote->getQuoteCurrencyCode();
        $context->language = (string) $this->scopeConfig->getValue(
            'general/locale/code',
            ScopeInterface::SCOPE_STORE,
            $quote->getStoreId()
        );
        $context->expectedGrandTotal = (float) $quote->getGrandTotal();
        $context->lineItems = new LineItemCollection(...$this->lineItemService->convertQuoteLineItems($quote));
        $gdprEnabled = $this->isGdprEnabled($quote->getStoreId());
        $context->billingAddress = $this->convertBillingAddress($quote, $gdprEnabled);
        $context->shippingAddress = $this->convertShippingAddress($quote, $gdprEnabled);
        $context->personalDetails = $this->buildPersonalDetails($quote, $gdprEnabled);
        $context->companyDetails = $this->buildCompanyDetails($quote, $gdprEnabled);
        $context->customerId = (string) ($quote->getCustomerId() ?? '');
        $context->customersPresence = CustomersPresence::VIRTUAL_PRESENT;
        $context->autoConfirmationEnabled = false;
        $context->chargeRetryEnabled = false;
        $context->deviceSessionIdentifier = $this->getDeviceSessionIdentifier();

        $spaceViewId = $this->scopeConfig->getValue(
            'wallee_payment/general/space_view_id',
            ScopeInterface::SCOPE_STORE,
            $quote->getStoreId()
        );
        if ($spaceViewId !== null && $spaceViewId !== '') {
            $context->spaceViewId = (int) $spaceViewId;
        }

        if ($quote->getShippingAddress()) {
            $context->shippingMethod = $this->helper->fixLength(
                $this->helper->getFirstLine($quote->getShippingAddress()->getShippingDescription()),
                200
            );
        }

        return $context;
    }

    /**
     * Whether GDPR mode is enabled for the given store (defaults to current scope).
     *
     * @param int|null $storeId
     * @return bool
     */
    private function isGdprEnabled($storeId = null): bool
    {
        return $this->scopeConfig->getValue(
            'wallee_payment/gdpr/gdpr_enabled',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) === 'enabled';
    }

    /**
     * Retrieve customer email address, with GDPR safety against re-using a stale
     * checkout-session email from an unrelated prior order.
     *
     * @param string|null $customerEmailAddress
     * @param int|null $customerId
     * @return string|null
     */
    protected function getCustomerEmailAddress($customerEmailAddress, $customerId)
    {
        $emailAddress = parent::getCustomerEmailAddress($customerEmailAddress, $customerId);
        if (!empty($emailAddress)) {
            return $emailAddress;
        }

        if ($this->isGdprEnabled()) {
            return null;
        }

        return $this->checkoutSession->getWalleeCheckoutEmailAddress();
    }

    /**
     * Converts the billing address of the given quote to a geography-only
     * plugin-core address, masking it when GDPR mode is enabled.
     *
     * Person and company identity now live on {@see PersonalDetails} /
     * {@see CompanyDetails} on the context, no longer on the address.
     *
     * @param Quote $quote
     * @param bool $gdprEnabled
     * @return CoreAddress|null
     */
    private function convertBillingAddress(Quote $quote, bool $gdprEnabled): ?CoreAddress
    {
        if (!$quote->getBillingAddress()) {
            $this->logger->debug("No billing address on quote.", ['quoteId' => $quote->getId()]);
            return null;
        }
        $address = $this->convertAddress($quote->getBillingAddress());
        $this->logger->debug("Converted billing address.", [
            'quoteId' => $quote->getId(),
            'street' => $address->street,
            'city' => $address->city,
            'country' => $address->country,
            'postcode' => $address->postcode,
        ]);

        if ($gdprEnabled) {
            // Wipe the address so that, if the customer ends up paying with a
            // non-Wallee method, the pending transaction left on
            // the Portal contains no PII. The confirmation path re-sends the
            // full data on Wallee checkouts.
            $this->maskAddress($address);
        }
        return $address;
    }

    /**
     * Converts the shipping address of the given quote to a geography-only
     * plugin-core address, masking it when GDPR mode is enabled.
     *
     * @param Quote $quote
     * @param bool $gdprEnabled
     * @return CoreAddress|null
     */
    private function convertShippingAddress(Quote $quote, bool $gdprEnabled): ?CoreAddress
    {
        if (!$quote->getShippingAddress()) {
            $this->logger->debug("No shipping address on quote.", ['quoteId' => $quote->getId()]);
            return null;
        }
        $address = $this->convertAddress($quote->getShippingAddress());
        $this->logger->debug("Converted shipping address.", [
            'quoteId' => $quote->getId(),
            'street' => $address->street,
            'city' => $address->city,
            'country' => $address->country,
            'postcode' => $address->postcode,
        ]);

        if ($gdprEnabled) {
            $this->maskAddress($address);
        }
        return $address;
    }

    /**
     * Builds the customer's personal identity details from the quote.
     *
     * Under GDPR mode only non-address-identifying data (gender) is transmitted
     * on the pending transaction; the confirmation path re-sends the full
     * details on Wallee checkouts.
     *
     * @param Quote $quote
     * @param bool $gdprEnabled
     * @return PersonalDetails|null
     */
    private function buildPersonalDetails(Quote $quote, bool $gdprEnabled): ?PersonalDetails
    {
        $gender = $this->resolveGender($quote->getCustomerGender(), $quote->getCustomerId());

        if ($gdprEnabled) {
            return $gender === null ? null : new PersonalDetails(gender: $gender);
        }

        $billingAddress = $quote->getBillingAddress();

        return new PersonalDetails(
            dateOfBirth: $this->parseDateOfBirth(
                $this->getDateOfBirth($quote->getCustomerDob(), $quote->getCustomerId())
            ),
            emailAddress: $this->getCustomerEmailAddress(
                $quote->getCustomerEmail(),
                $quote->getCustomerId()
            ),
            familyName: $billingAddress
                ? $this->helper->fixLength($this->helper->removeLinebreaks($billingAddress->getLastname()), 100)
                : null,
            gender: $gender,
            givenName: $billingAddress
                ? $this->helper->fixLength($this->helper->removeLinebreaks($billingAddress->getFirstname()), 100)
                : null,
            salutation: $billingAddress
                ? $this->helper->fixLength($this->helper->removeLinebreaks($billingAddress->getPrefix()), 20)
                : null,
        );
    }

    /**
     * Builds the customer's company identity details from the quote.
     *
     * Under GDPR mode only the sales-tax number is transmitted; the
     * organization name is withheld from the pending transaction.
     *
     * @param Quote $quote
     * @param bool $gdprEnabled
     * @return CompanyDetails|null
     */
    private function buildCompanyDetails(Quote $quote, bool $gdprEnabled): ?CompanyDetails
    {
        $salesTaxNumber = $this->getTaxNumber($quote->getCustomerTaxvat(), $quote->getCustomerId());

        if ($gdprEnabled) {
            return ($salesTaxNumber === null || $salesTaxNumber === '')
                ? null
                : new CompanyDetails(salesTaxNumber: $salesTaxNumber);
        }

        $billingAddress = $quote->getBillingAddress();
        $organizationName = $billingAddress
            ? $this->helper->fixLength($this->helper->removeLinebreaks($billingAddress->getCompany()), 100)
            : null;

        return new CompanyDetails(
            organizationName: $organizationName,
            salesTaxNumber: $salesTaxNumber,
        );
    }

    /**
     * Converts a Magento quote address into a geography-only plugin-core address
     * DTO, applying length and line-break sanitisation expected by the portal.
     *
     * @param Address $customerAddress
     * @return CoreAddress
     */
    private function convertAddress(Address $customerAddress): CoreAddress
    {
        $address = new CoreAddress();
        $address->city = $customerAddress->getCity();
        $address->country = (string) $customerAddress->getCountryId();
        $address->phoneNumber = $customerAddress->getTelephone();
        if (!empty($customerAddress->getCountryId()) && !empty($customerAddress->getRegionCode())) {
            $address->postalState = $customerAddress->getCountryId() . '-' . $customerAddress->getRegionCode();
        }
        $address->postcode = $customerAddress->getPostcode();
        $address->street = $customerAddress->getStreetFull();
        return $address;
    }

    /**
     * Clears every field on the given geography-only address so that no address
     * data is left on the portal if the customer ends up paying with a
     * non-Wallee method. Person/company identity is withheld
     * separately by not populating PersonalDetails/CompanyDetails.
     *
     * @param CoreAddress $address
     * @return void
     */
    private function maskAddress(CoreAddress $address): void
    {
        $address->city = '';
        $address->country = '';
        $address->dependentLocality = null;
        $address->phoneNumber = null;
        $address->postalState = null;
        $address->postcode = null;
        $address->sortingCode = null;
        $address->street = null;
    }

    /**
     * Resolves the customer's gender as a plugin-core enum value, reading from
     * the customer registry when the quote does not carry a value of its own.
     *
     * @param string|int|null $gender
     * @param int|null $customerId
     * @return Gender|null
     */
    private function resolveGender($gender, $customerId): ?Gender
    {
        $raw = $this->getRawGender($gender, $customerId);
        if ($raw === 1) {
            return Gender::MALE;
        }
        if ($raw === 2) {
            return Gender::FEMALE;
        }
        return null;
    }

    /**
     * Parses a date-of-birth string into a DateTimeImmutable, returning null
     * for empty input or unparsable values.
     *
     * @param string|null $dateOfBirth
     * @return \DateTimeImmutable|null
     */
    private function parseDateOfBirth(?string $dateOfBirth): ?\DateTimeImmutable
    {
        if ($dateOfBirth === null || $dateOfBirth === '') {
            return null;
        }
        try {
            return new \DateTimeImmutable($dateOfBirth);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Returns the per-quote transaction map from the checkout session, or an
     * empty array if it has not been initialised (or could not be read).
     *
     * @return array<int, CoreTransaction|null>
     */
    private function getTransactionArrayFromSession(): array
    {
        try {
            if ($this->checkoutSession->getTransaction()) {
                return $this->checkoutSession->getTransaction();
            }
        } catch (LocalizedException $ignored) {
            $this->logger->debug("Could not read transaction array from checkout session; treating as empty.");
        }
        return [];
    }

    /**
     * Whether transaction validation during checkout is enabled for the given store (defaults to current scope).
     *
     * @param int|null $storeId
     * @return bool
     */
    private function isValidateTransactionOnCheckoutEnabled($storeId = null)
    {
        return $this->scopeConfig->getValue(
            'wallee_payment/checkout/validate_transaction_on_checkout',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) === "1";
    }
}
