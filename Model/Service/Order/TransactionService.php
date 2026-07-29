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
namespace Wallee\Payment\Model\Service\Order;

use Magento\Customer\Model\CustomerRegistry;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Invoice;
use Magento\Store\Model\ScopeInterface;
use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Wallee\Payment\Helper\Data as Helper;
use Wallee\Payment\Helper\LineItem as LineItemHelper;
use Wallee\Payment\Model\ApiClient;
use Wallee\Payment\Model\Config\Source\IntegrationMethod;
use Wallee\Payment\Model\CustomerIdManipulationException;
use Wallee\Payment\Model\Payment\Method\Adapter as PaymentMethodAdapter;
use Wallee\Payment\Model\Service\AbstractTransactionService;
use Wallee\PluginCore\Address\Address as CoreAddress;
use Wallee\PluginCore\Customer\CompanyDetails;
use Wallee\PluginCore\Customer\Gender;
use Wallee\PluginCore\Customer\PersonalDetails;
use Wallee\PluginCore\LineItem\LineItemCollection;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Transaction\Exception\TransactionException;
use Wallee\PluginCore\Transaction\Invoice\Invoice as CoreInvoice;
use Wallee\PluginCore\Transaction\Invoice\InvoiceGatewayInterface;
use Wallee\PluginCore\Transaction\Invoice\InvoiceSearchCriteria;
use Wallee\PluginCore\Transaction\Invoice\State as CoreTransactionInvoiceState;
use Wallee\PluginCore\SharedKernel\Url;
use Wallee\PluginCore\Transaction\State as CoreTransactionState;
use Wallee\PluginCore\Transaction\TransactionContext;
use Wallee\PluginCore\Transaction\TransactionGatewayInterface;
use Wallee\PluginCore\Token\Token as CoreToken;
use Wallee\Sdk\Model\EntityQuery;
use Wallee\Sdk\Model\Transaction;
use Wallee\Sdk\Service\DeliveryIndicationService;

/**
 * Service to handle transactions in order context.
 */
class TransactionService extends AbstractTransactionService
{
    /**
     * Number of attempts to call the portal API
     */
    public const NUMBER_OF_ATTEMPTS = 3;

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
     * Gateway for the two-step update/confirm flow that replaced the legacy
     * SDK confirm() call.
     *
     * @var TransactionGatewayInterface
     */
    private TransactionGatewayInterface $transactionGateway;

    /**
     *
     * @var InvoiceGatewayInterface
     */
    private $invoiceGateway;

    /**
     *
     * @var EventManagerInterface
     */
    private $eventManager;

    /**
     *
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     *
     * @param Helper $helper
     * @param ScopeConfigInterface $scopeConfig
     * @param CustomerRegistry $customerRegistry
     * @param OrderRepositoryInterface $orderRepository
     * @param ApiClient $apiClient
     * @param CookieManagerInterface $cookieManager
     * @param LoggerInterface $logger
     * @param LineItemService $lineItemService
     * @param LineItemHelper $lineItemHelper
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param InvoiceGatewayInterface $invoiceGateway
     * @param TransactionGatewayInterface $transactionGateway
     * @param EventManagerInterface $eventManager
     * @param CartRepositoryInterface $quoteRepository
     */
    public function __construct(
        Helper $helper,
        ScopeConfigInterface $scopeConfig,
        CustomerRegistry $customerRegistry,
        OrderRepositoryInterface $orderRepository,
        ApiClient $apiClient,
        CookieManagerInterface $cookieManager,
        LoggerInterface $logger,
        LineItemService $lineItemService,
        LineItemHelper $lineItemHelper,
        TransactionInfoRepositoryInterface $transactionInfoRepository,
        InvoiceGatewayInterface $invoiceGateway,
        TransactionGatewayInterface $transactionGateway,
        EventManagerInterface $eventManager,
        CartRepositoryInterface $quoteRepository,
    ) {
        parent::__construct(
            $customerRegistry,
            $apiClient,
            $cookieManager
        );
        $this->helper = $helper;
        $this->scopeConfig = $scopeConfig;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
        $this->lineItemService = $lineItemService;
        $this->eventManager = $eventManager;
        $this->lineItemHelper = $lineItemHelper;
        $this->transactionInfoRepository = $transactionInfoRepository;
        $this->apiClient = $apiClient;
        $this->invoiceGateway = $invoiceGateway;
        $this->transactionGateway = $transactionGateway;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Updates the transaction with the given order's data and confirms it
     * through the PluginCore two-step gateway flow.
     *
     * The method builds a TransactionContext from the order, pushes it via
     * TransactionGatewayInterface::update(), then locks the transaction via
     * TransactionGatewayInterface::confirm(). The retry loop handles
     * versioning conflicts surfacing as TransactionException.
     *
     * The SDK Transaction return type is preserved because the caller
     * (SubmitQuote) forwards it to TransactionInfoManagement::update(),
     * which still requires the SDK model.
     *
     * @param Transaction $transaction
     * @param Order $order
     * @param Invoice $invoice
     * @param bool $chargeFlow
     * @param \Wallee\Sdk\Model\Token|null $token
     * @return Transaction
     * @throws LocalizedException
     * @throws CustomerIdManipulationException
     */
    public function confirmTransaction(
        Transaction $transaction,
        Order $order,
        Invoice $invoice,
        bool $chargeFlow = false,
        ?\Wallee\Sdk\Model\Token $token = null,
    ) {
        if ($transaction->getState() == CoreTransactionState::CONFIRMED->value) {
            return $transaction;
        } elseif ($transaction->getState() != CoreTransactionState::PENDING->value) {
            $this->cancelOrder($order, $invoice);
            throw new LocalizedException(\__('wallee_checkout_failure'));
        }

        $spaceId = (int) $order->getWalleeSpaceId();
        $transactionId = (int) $order->getWalleeTransactionId();

        for ($i = 0; $i < self::NUMBER_OF_ATTEMPTS; $i++) {
            try {
                // On retries, re-read the transaction to get the current version and state.
                if ($i > 0) {
                    $transaction = $this->getTransaction($spaceId, $transactionId);
                    if ($transaction instanceof Transaction
                        && $transaction->getState() == CoreTransactionState::CONFIRMED->value
                    ) {
                        return $transaction;
                    } elseif (!($transaction instanceof Transaction)
                        || $transaction->getState() != CoreTransactionState::PENDING->value
                    ) {
                        $this->cancelOrder($order, $invoice);
                        throw new LocalizedException(\__('wallee_checkout_failure'));
                    }
                }

                if (!empty($transaction->getCustomerId())
                    && $transaction->getCustomerId() != $order->getCustomerId()
                ) {
                    throw new CustomerIdManipulationException();
                }

                // Build the PluginCore context from the order data.
                $context = $this->buildConfirmationContext(
                    $order,
                    $spaceId,
                    $chargeFlow,
                    $token,
                );

                // Step 1: push the order data onto the existing transaction.
                $this->transactionGateway->update(
                    $transactionId,
                    (int) $transaction->getVersion(),
                    $context,
                );

                // Step 2: lock the transaction to finalize checkout.
                $this->transactionGateway->confirm($spaceId, $transactionId);

                // Re-read via the SDK API to return the full SDK Transaction
                // the caller (SubmitQuote → TransactionInfoManagement) expects.
                return $this->getTransaction($spaceId, $transactionId);
            } catch (TransactionException $e) {
                // isRetryable() is true for an optimistic-locking version conflict or a
                // transient connection error; any other failure is permanent, so surface
                // it immediately with its root cause preserved instead of burning the
                // remaining attempts on it.
                if (!$e->isRetryable()) {
                    throw new LocalizedException(\__('wallee_checkout_failure'), $e);
                }
                $this->logger->debug(
                    'Transient failure during transaction confirmation; retrying.',
                    ['exception' => $e, 'attempt' => $i + 1],
                );
            }
        }

        throw new LocalizedException(
            \__('wallee_checkout_failure'),
        );
    }

    /**
     * Cancels the given order and invoice linked to the transaction.
     *
     * Also clears the transaction id from the order's quote — the transaction
     * being cancelled is no longer PENDING (e.g. it expired while the customer
     * was idle on checkout), so leaving the stale id on the quote would make
     * the very next retry reuse the same dead transaction, mirroring
     * {@see \Wallee\Payment\Observer\UpdateDeclinedOrderTransaction}'s
     * handling of the same situation on the `restore_quote` path.
     *
     * @param Order $order
     * @param Invoice $invoice
     * @return void
     */
    private function cancelOrder(Order $order, Invoice $invoice): void
    {
        if ($invoice) {
            $order->setWalleeInvoiceAllowManipulation(true);
            $invoice->cancel();
            $order->addRelatedObject($invoice);
        }
        $order->registerCancellation(null, false);
        $this->orderRepository->save($order);
        $this->clearQuoteTransactionReference($order);
    }

    /**
     * Clears the stale transaction id from the order's quote, if it still exists.
     *
     * @param Order $order
     * @return void
     */
    private function clearQuoteTransactionReference(Order $order): void
    {
        try {
            $quote = $this->quoteRepository->get($order->getQuoteId());
            $quote->setWalleeTransactionId(null);
            $this->quoteRepository->save($quote);
        } catch (\Exception $e) {
            $this->logger->debug("Failed to clear the transaction id from the order's quote.", [
                'orderId' => $order->getIncrementId(),
                'exception' => $e,
            ]);
        }
    }

    /**
     * Builds a PluginCore TransactionContext from the Magento order data for
     * the confirmation step.
     *
     * Replaces the legacy assembleTransactionDataFromOrder() that populated
     * an SDK TransactionPending object.
     *
     * @param Order $order
     * @param int $spaceId
     * @param bool $chargeFlow
     * @param \Wallee\Sdk\Model\Token|null $token
     * @return TransactionContext
     */
    private function buildConfirmationContext(
        Order $order,
        int $spaceId,
        bool $chargeFlow,
        ?\Wallee\Sdk\Model\Token $token,
    ): TransactionContext {
        $context = new TransactionContext();
        $context->spaceId = $spaceId;
        $context->currencyCode = (string) $order->getOrderCurrencyCode();
        $context->language = (string) $this->scopeConfig->getValue(
            'general/locale/code',
            ScopeInterface::SCOPE_STORE,
            $order->getStoreId(),
        );
        $context->merchantReference = (string) $order->getIncrementId();
        $context->invoiceMerchantReference = (string) $order->getIncrementId();
        $context->customerId = (string) ($order->getCustomerId() ?? '');

        $context->metaData = $this->collectMetaData($order);

        $methodInstance = $order->getPayment()?->getMethodInstance();
        if ($methodInstance instanceof PaymentMethodAdapter) {
            $context->allowedPaymentMethodConfigurations = [$methodInstance->getPaymentMethodConfigurationId()];
        }

        // Map billing and shipping addresses using the PluginCore address DTO.
        // TransactionContext::$billingAddress is non-nullable and $shippingAddress
        // defaults to null, so only assign when the conversion yields an address.
        $billingAddress = $this->convertOrderBillingAddress($order);
        if ($billingAddress !== null) {
            $context->billingAddress = $billingAddress;
        }
        $shippingAddress = $this->convertOrderShippingAddress($order);
        if ($shippingAddress !== null) {
            $context->shippingAddress = $shippingAddress;
        }

        // Personal and company identity live on the context alongside the
        // address for the gateway's mapAddress() to merge into the SDK payload.
        $context->personalDetails = $this->buildPersonalDetails($order);
        $context->companyDetails = $this->buildCompanyDetails($order);

        $lineItems = $this->lineItemService->convertOrderLineItems($order);
        $context->lineItems = new LineItemCollection(...$lineItems);
        $this->logAdjustmentLineItemInfo($order, $lineItems);

        $context->expectedGrandTotal = (float) $order->getGrandTotal();

        if ($order->getShippingAddress()) {
            $context->shippingMethod = $this->helper->fixLength(
                $this->helper->getFirstLine(
                    $order->getShippingAddress()->getShippingDescription(),
                ),
                200,
            );
        }

        $context->autoConfirmationEnabled = false;
        $context->chargeRetryEnabled = false;

        $spaceViewId = $this->scopeConfig->getValue(
            'wallee_payment/general/space_view_id',
            ScopeInterface::SCOPE_STORE,
            $order->getStoreId(),
        );
        if ($spaceViewId !== null && $spaceViewId !== '') {
            $context->spaceViewId = (int) $spaceViewId;
        }

        $context->deviceSessionIdentifier = $this->getDeviceSessionIdentifier();

        // Resolve the success/failure return URLs.
        if (!$chargeFlow) {
            $this->applyReturnUrls($context, $order);
        }

        // Map the SDK token to a PluginCore token if provided.
        if ($token !== null) {
            $coreToken = new CoreToken();
            $coreToken->id = (int) $token->getId();
            $context->token = $coreToken;
        }

        return $context;
    }

    /**
     * Collects the arbitrary shop-defined key/value data to attach to the transaction
     * via TransactionContext::$metaData, by dispatching an event that observers
     * (e.g. Amasty order attributes, customer attributes) populate.
     *
     * @param Order $order
     * @return array<string, mixed>
     */
    private function collectMetaData(Order $order): array
    {
        $transport = new DataObject(['metaData' => []]);
        $this->eventManager->dispatch(
            'wallee_payment_collect_meta_data',
            [
                'order' => $order,
                'transport' => $transport,
            ]
        );
        return $transport->getData('metaData');
    }

    /**
     * Resolves success/failure return URLs from the shop or external PWA
     * configuration and sets them on the transaction context.
     *
     * @param TransactionContext $context
     * @param Order $order
     * @return void
     */
    private function applyReturnUrls(TransactionContext $context, Order $order): void
    {
        $successUrl = $this->buildUrl(
            'wallee_payment/transaction/success',
            $order,
        );
        $failureUrl = $this->buildUrl(
            'wallee_payment/transaction/failure',
            $order,
        );

        try {
            $transactionInfo = $this->transactionInfoRepository->getByTransactionId(
                $order->getWalleeSpaceId(),
                $order->getWalleeTransactionId(),
            );

            // External return URL to the shop, such as PWA storefronts.
            if ($transactionInfo !== null && $transactionInfo->isExternalPaymentUrl()) {
                $successUrl = $this->buildUrl(
                    $transactionInfo->getSuccessUrl(),
                    $order,
                    true,
                );
                $failureUrl = $this->buildUrl(
                    $transactionInfo->getFailureUrl(),
                    $order,
                    true,
                );
            }
        } catch (\Exception $e) {
            $this->logger->debug(
                "Could not resolve external payment return URLs; falling back to the shop's own. " .
                $e->getMessage(),
            );
        }

        $this->logger->debug('Success return URL: ' . $successUrl . '?utm_nooverride=1');
        $this->logger->debug('Failure return URL: ' . $failureUrl . '?utm_nooverride=1');

        $context->successUrl = new Url(sprintf('%s?utm_nooverride=1', $successUrl));
        $context->failedUrl = new Url(sprintf('%s?utm_nooverride=1', $failureUrl));
    }

    /**
     * Checks whether an adjustment line item is present and logs a warning
     * about the total mismatch that caused it.
     *
     * @param Order $order
     * @param \Wallee\PluginCore\LineItem\LineItem[] $lineItems
     * @return void
     */
    protected function logAdjustmentLineItemInfo(Order $order, array $lineItems): void
    {
        foreach ($lineItems as $lineItem) {
            if ($lineItem->uniqueId === 'adjustment') {
                $totalAmount = 0.0;
                foreach ($lineItems as $item) {
                    $totalAmount += $item->amountIncludingTax;
                }
                $expectedSum = $totalAmount - $lineItem->amountIncludingTax;
                $this->logger->warning(
                    'An adjustment line item has been added to the transaction, ' .
                    'because the line item total amount of ' .
                    $this->helper->roundAmount($order->getGrandTotal(), $order->getOrderCurrencyCode()) .
                    ' did not match the invoice amount of ' . $expectedSum .
                    ' of the order ' . $order->getId() . '.',
                );
                return;
            }
        }
    }

    /**
     * Builds the URL to an endpoint that is aware of the given order.
     *
     * @param string $route
     * @param Order $order
     * @param bool $extarnalUrl
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function buildUrl($route, Order $order, $extarnalUrl = false)
    {
        $token = $order->getWalleeSecurityToken();
        if (empty($token)) {
            throw new LocalizedException(
                \__('The wallee security token needs to be set on the order to build the URL.')
            );
        }

        if ($extarnalUrl) {
            return sprintf('%s/order/%d/token/%s/', $route, $order->getId(), $token);
        }

        return $order->getStore()->getUrl(
            $route,
            [
                '_secure' => true,
                'order_id' => $order->getId(),
                'token' => $token
            ]
        );
    }

    /**
     * Gets the payment url of the transaction according to the type of integration.
     *
     * @param Order $order
     * @param string $integrationType
     * @return string
     */
    public function getTransactionPaymentUrl(Order $order, string $integrationType)
    {
        $spaceId = (int) $order->getWalleeSpaceId();
        $transactionId = (int) $order->getWalleeTransactionId();

        // The unified PluginCore gateway resolves the correct payment URL format
        // (iframe / lightbox / payment page) internally from the settings,
        // so the explicit integration type is no longer needed but accepted
        // for signature compatibility.
        $url = (string) $this->transactionGateway->getPaymentUrl($spaceId, $transactionId);

        $this->logger->debug('Generated payment page URL: ' . $url);
        return $url;
    }

    /**
     * Converts the billing address of the given order into a geography-only
     * PluginCore address. Person/company identity is now mapped separately
     * onto PersonalDetails/CompanyDetails on the TransactionContext.
     *
     * @param Order $order
     * @return CoreAddress|null
     */
    private function convertOrderBillingAddress(Order $order): ?CoreAddress
    {
        if (!$order->getBillingAddress()) {
            return null;
        }
        return $this->convertAddress($order->getBillingAddress());
    }

    /**
     * Converts the shipping address of the given order into a geography-only
     * PluginCore address.
     *
     * @param Order $order
     * @return CoreAddress|null
     */
    private function convertOrderShippingAddress(Order $order): ?CoreAddress
    {
        if (!$order->getShippingAddress()) {
            return null;
        }
        return $this->convertAddress($order->getShippingAddress());
    }

    /**
     * Converts a Magento order address into a geography-only PluginCore address
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
        $street = $customerAddress->getStreet();
        $address->street = \is_array($street) ? \implode("\n", $street) : $street;
        return $address;
    }

    /**
     * Builds the customer's personal identity details from the order.
     *
     * These are kept separate from the address; the gateway merges them into
     * the SDK AddressCreate payload.
     *
     * @param Order $order
     * @return PersonalDetails|null
     */
    private function buildPersonalDetails(Order $order): ?PersonalDetails
    {
        $billingAddress = $order->getBillingAddress();

        return new PersonalDetails(
            dateOfBirth: $this->parseDateOfBirth(
                $this->getDateOfBirth($order->getCustomerDob(), $order->getCustomerId()),
            ),
            emailAddress: $this->getCustomerEmailAddress(
                $order->getCustomerEmail(),
                $order->getCustomerId(),
            ),
            familyName: $billingAddress
                ? $this->helper->removeLinebreaks($billingAddress->getLastname())
                : null,
            gender: $this->resolveGender($order->getCustomerGender(), $order->getCustomerId()),
            givenName: $billingAddress
                ? $this->helper->removeLinebreaks($billingAddress->getFirstname())
                : null,
            salutation: $billingAddress
                ? $this->helper->removeLinebreaks($billingAddress->getPrefix())
                : null,
        );
    }

    /**
     * Builds the customer's company identity details from the order.
     *
     * @param Order $order
     * @return CompanyDetails|null
     */
    private function buildCompanyDetails(Order $order): ?CompanyDetails
    {
        $salesTaxNumber = $this->getTaxNumber(
            $order->getCustomerTaxvat(),
            $order->getCustomerId(),
        );
        $billingAddress = $order->getBillingAddress();
        $organizationName = $billingAddress
            ? $this->helper->removeLinebreaks($billingAddress->getCompany())
            : null;

        return new CompanyDetails(
            organizationName: $organizationName,
            salesTaxNumber: $salesTaxNumber,
        );
    }

    /**
     * Resolves the customer's gender as a PluginCore enum value, reading from
     * the customer registry when the order does not carry a value.
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
     * Marks the delivery indication belonging to the given payment as suitable.
     *
     * Note: there are no delivery indication for Authorized transactions
     *
     * @param Order $order
     * @return \Wallee\Sdk\Model\DeliveryIndication
     */
    public function accept(Order $order)
    {
        return $this->apiClient->getService(DeliveryIndicationService::class)->markAsSuitable(
            $order->getWalleeSpaceId(),
            $this->getDeliveryIndication($order)
            ->getId()
        );
    }

    /**
     * Marks the delivery indication belonging to the given payment as not suitable.
     *
     * Note: there are no delivery indication for Authorized transactions
     *
     * @param Order $order
     * @return \Wallee\Sdk\Model\DeliveryIndication
     */
    public function deny(Order $order)
    {
        return $this->apiClient->getService(DeliveryIndicationService::class)->markAsNotSuitable(
            $order->getWalleeSpaceId(),
            $this->getDeliveryIndication($order)
            ->getId()
        );
    }

    /**
     * Gets the delivery indication linked to the given order.
     *
     * @param Order $order
     * @return \Wallee\Sdk\Model\DeliveryIndication
     */
    protected function getDeliveryIndication(Order $order)
    {
        $query = new EntityQuery();
        $query->setFilter(
            $this->helper->createEntityFilter('transaction.id', $order->getWalleeTransactionId())
        );
        $query->setNumberOfEntities(1);
        return \current(
            $this->apiClient->getService(DeliveryIndicationService::class)->search(
                $order->getWalleeSpaceId(),
                $query
            )
        );
    }

    /**
     * Gets the transaction invoice linked to the given order.
     *
     * @param Order $order
     * @throws NoSuchEntityException
     * @return CoreInvoice
     */
    public function getTransactionInvoice(Order $order): CoreInvoice
    {
        $criteria = new InvoiceSearchCriteria(
            filters: [
                'completion.lineItemVersion.transaction.id' => $order->getWalleeTransactionId(),
            ]
        );
        $invoices = $this->invoiceGateway->search(
            (int) $order->getWalleeSpaceId(),
            $criteria
        );

        // InvoiceSearchCriteria only supports EQUALS filters, so canceled invoices are excluded here.
        $active = array_filter(
            iterator_to_array($invoices),
            static fn (CoreInvoice $invoice): bool => $invoice->state !== CoreTransactionInvoiceState::CANCELED
        );

        $invoice = array_shift($active);
        if ($invoice === null) {
            throw new NoSuchEntityException();
        }
        return $invoice;
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

            usleep(2000000);
        }
    }
}
