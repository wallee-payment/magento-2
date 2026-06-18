<?php

namespace Wallee\Payment\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Wallee\Payment\Api\PaymentMetadataManagementInterface;
use Wallee\Payment\Api\PaymentMethodConfigurationRepositoryInterface;
use Wallee\Payment\Model\Service\Quote\TransactionService;
use Wallee\PluginCore\Render\IntegratedPaymentRenderService;
use Wallee\PluginCore\Settings\IntegrationMode;
use Psr\Log\LoggerInterface;

/**
 * Service to manage and retrieve payment metadata for the frontend.
 * It handles the creation of SDK URLs and prepares integration data based on the chosen mode.
 */
class PaymentMetadataManagement implements PaymentMetadataManagementInterface
{
    /**
     * Repository to manage quote/cart persistence.
     *
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $cartRepository;

    /**
     * Repository to access payment method configuration details.
     *
     * @var PaymentMethodConfigurationRepositoryInterface
     */
    private PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository;

    /**
     * Service to generate standardized payment rendering metadata.
     *
     * @var IntegratedPaymentRenderService
     */
    private IntegratedPaymentRenderService $renderService;

    /**
     * Magento configuration service for store-specific settings.
     *
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * Service to retrieve SDK URLs from the transaction data.
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
     * Initialize the metadata management service with required dependencies.
     *
     * @param CartRepositoryInterface $cartRepository
     * @param TransactionService $transactionService
     * @param IntegratedPaymentRenderService $renderService
     * @param PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        TransactionService $transactionService,
        IntegratedPaymentRenderService $renderService,
        PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
    ) {
        $this->cartRepository = $cartRepository;
        $this->transactionService = $transactionService;
        $this->renderService = $renderService;
        $this->paymentMethodConfigurationRepository = $paymentMethodConfigurationRepository;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    /**
     * Retrieve the payment metadata for a specific cart and payment method.
     *
     * This metadata includes the SDK URL and integration parameters required by the frontend.
     *
     * @param string $cartId The quote mask or ID.
     * @param string $methodCode The payment method code.
     * @return string JSON encoded metadata object or error message.
     */
    public function getMetadata(string $cartId, string $methodCode): string
    {
        $quote = $this->cartRepository->get($cartId);

        /** @var string $integrationMode */
        $integrationMode = $this->scopeConfig->getValue(
            'wallee_payment/checkout/integration_method',
            ScopeInterface::SCOPE_STORE,
            $quote->getStoreId(),
        );

        $configurationId = null;
        try {
            $entityId = (int) str_replace('wallee_payment_', '', $methodCode);
            if ($entityId) {
                $configurationMethod = $this->paymentMethodConfigurationRepository->get($entityId);
                $configurationId = (int)$configurationMethod->getConfigurationId();
            }
        } catch (\Exception $e) {
            // Silence repository exceptions if the configuration cannot be resolved.
            $this->logger->debug(
                "Gateway configuration ID extraction from the Magento payment method failed:  " . $e->getMessage()
            );
        }

        if (!$configurationId) {
            return json_encode([
                'error' => 'Invalid configuration',
            ]);
        }

        try {
            // Determine the SDK URL based on whether we are using an Iframe or Lightbox.
            $sdkUrl = ($integrationMode === IntegrationMode::IFRAME->value)
                ? $this->transactionService->getJavaScriptUrl($quote)
                : $this->transactionService->getLightboxUrl($quote);

            $data = $this->renderService->getMetadata($sdkUrl, $configurationId, $integrationMode);

            return json_encode([
                'javascriptUrl' => $data->javascriptUrl,
                'configurationId' => $data->paymentMethodConfigurationId,
                'integrationMode' => $data->integrationMode,
            ]);
        } catch (\Exception $e) {
            return json_encode([
                'error' => $e->getMessage(),
            ]);
        }
    }
}
