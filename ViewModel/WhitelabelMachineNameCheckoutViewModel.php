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

declare(strict_types=1);

namespace Wallee\Payment\ViewModel;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Csp\Helper\CspNonceProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;
use Wallee\Payment\Api\PaymentMethodConfigurationRepositoryInterface;
use Wallee\Payment\Model\Service\Quote\TransactionService;
use Wallee\PluginCore\Render\IntegratedPaymentRenderService;
use Wallee\PluginCore\Settings\IntegrationMode;
use Psr\Log\LoggerInterface;

/**
 * ViewModel to provide Wallee payment configuration to the checkout.
 * This class facilitates the interaction between the Magento session/config and
 * the plugin-core rendering logic for frontend themes like Hyvä.
 */
class WhitelabelMachineNameCheckoutViewModel implements ArgumentInterface
{
    /**
     * Magento checkout session to access the current quote.
     *
     * @var CheckoutSession
     */
    private CheckoutSession $checkoutSession;

    /**
     * Provider to facilitate CSP nonce generation for script integrity.
     *
     * @var CspNonceProvider
     */
    private CspNonceProvider $cspNonceProvider;

    /**
     * Repository to resolve internal configuration IDs from method codes.
     *
     * @var PaymentMethodConfigurationRepositoryInterface
     */
    private PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository;

    /**
     * Service to retrieve standardized rendering metadata for the gateway.
     *
     * @var IntegratedPaymentRenderService
     */
    private IntegratedPaymentRenderService $renderService;

    /**
     * Configuration service to retrieve plugin settings.
     *
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * Service to generate SDK URLs for transaction processing.
     *
     * @var TransactionService
     */
    private TransactionService $transactionService;

    /**
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Initialize the ViewModel with its required dependencies.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param CheckoutSession $checkoutSession
     * @param TransactionService $transactionService
     * @param PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository
     * @param IntegratedPaymentRenderService $renderService
     * @param CspNonceProvider $cspNonceProvider
     * @param LoggerInterface $logger
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        CheckoutSession $checkoutSession,
        TransactionService $transactionService,
        PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository,
        IntegratedPaymentRenderService $renderService,
        CspNonceProvider $cspNonceProvider,
        LoggerInterface $logger,
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->checkoutSession = $checkoutSession;
        $this->transactionService = $transactionService;
        $this->paymentMethodConfigurationRepository = $paymentMethodConfigurationRepository;
        $this->renderService = $renderService;
        $this->cspNonceProvider = $cspNonceProvider;
        $this->logger = $logger;
    }

    /**
     * Returns the payment integration metadata for reactive frontends (e.g., Hyvä).
     *
     * This provides the necessary URLs and IDs for the frontend SDK to initialize.
     *
     * @param string $methodCode The Magento payment method code (vendor prefix included).
     * @return array<string, mixed> The metadata for the frontend.
     */
    public function getPaymentIntegrationData(string $methodCode): array
    {
        $quote = $this->checkoutSession->getQuote();

        // The Magewire re-render after placeOrder() runs before the browser redirect,
        // so the payment template is rendered again against an already-deactivated quote.
        // Bail out to prevent createTransactionByQuote() from firing on a completed order.
        if (!$quote->getIsActive()) {
            return [];
        }

        /** @var string $integrationMode */
        $integrationMode = $this->scopeConfig->getValue(
            'wallee_payment/checkout/integration_method',
            ScopeInterface::SCOPE_STORE,
            $quote->getStoreId(),
        );

        $configurationId = $this->getConfigurationId($methodCode);
        if (!$configurationId) {
            return [];
        }

        // We choose the SDK URL based on whether we are performing an inline (iframe)
        // or overlay (lightbox) integration.
        $sdkUrl = ($integrationMode === IntegrationMode::IFRAME->value)
            ? $this->transactionService->getJavaScriptUrl($quote)
            : $this->transactionService->getLightboxUrl($quote);

        $data = $this->renderService->getMetadata($sdkUrl, (int)$configurationId, $integrationMode);

        return [
            'javascriptUrl' => $data->javascriptUrl,
            'configurationId' => $data->paymentMethodConfigurationId,
            'integrationMode' => $data->integrationMode,
            'containerId' => 'wallee-iframe-' . $methodCode,
            'cspNonce' => $this->cspNonceProvider->generateNonce(),
        ];
    }

    /**
     * Extracts the gateway configuration ID from the Magento payment method code.
     *
     * This maps the internal Magento entity back to the provider's configuration.
     *
     * @param string $methodCode
     * @return int|null
     */
    private function getConfigurationId(string $methodCode): ?int
    {
        try {
            $entityId = (int) str_replace('wallee_payment_', '', $methodCode);
            if ($entityId) {
                $configurationMethod = $this->paymentMethodConfigurationRepository->get($entityId);
                return (int)$configurationMethod->getConfigurationId();
            }
        } catch (\Exception $e) {
            // Silently fail if the configuration cannot be resolved.
            $this->logger->debug(
                "Gateway configuration ID extraction from the Magento payment method failed:  " . $e->getMessage()
            );
        }

        return null;
    }
}
