<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\Settings;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Wallee\PluginCore\Settings\IntegrationMode;
use Wallee\PluginCore\Settings\SettingsProviderInterface;
use Wallee\PluginCore\Settings\DefaultSettingsProvider;

/**
 * Magento implementation for providing settings.
 * It respects the specific scope definitions in system.xml.
 */
class SettingsProvider extends DefaultSettingsProvider implements SettingsProviderInterface
{
    /**
     * @var int
     */
    private const XML_PATH_SPACE_ID = 'wallee_payment/general/space_id';

    /**
     * @var int
     */
    private const XML_PATH_USER_ID = 'wallee_payment/general/api_user_id';

    /**
     * @var string
     */
    private const XML_PATH_API_SECRET = 'wallee_payment/general/api_user_secret';

    /**
     * @var string
     */
    private const XML_PATH_LOG_LEVEL = 'wallee_payment/logging/log_level';

    /**
     * @var string
     */
    private const XML_PATH_ENFORCE_LINE_ITEM_CONSISTENCY = 'wallee_payment/line_items/enforce_consistency';

    /**
     * @var string
     */
    private const XML_PATH_INTEGRATION_METHOD = 'wallee_payment/checkout/integration_method';

    /**
     * Deploy-time-only override (app/etc/env.php), deliberately not exposed via system.xml —
     * this is for plugin developers pointing at a non-production environment, not merchants.
     *
     * @var string
     */
    private const DEPLOYMENT_CONFIG_PATH_BASE_URL = 'wallee/base_url';

    /**
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     * @param StoreManagerInterface $storeManager
     * @param DeploymentConfig $deploymentConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor,
        private readonly StoreManagerInterface $storeManager,
        private readonly DeploymentConfig $deploymentConfig,
    ) {
    }

    /**
     * Returns the globally configured User ID.
     *
     * @return int|null
     */
    public function getUserId(): ?int
    {
        // User ID is GLOBAL (showInDefault="1", showInWebsite="0")
        // We force ScopeConfigInterface::SCOPE_TYPE_DEFAULT to ignore website scopes
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_USER_ID,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );
        return $value === null ? null : (int)$value;
    }

    /**
     * Returns the decrypted global API key.
     *
     * @return string|null
     */
    public function getApiKey(): ?string
    {
        // API Key is GLOBAL
        $encryptedValue = $this->scopeConfig->getValue(
            self::XML_PATH_API_SECRET,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );

        if (empty($encryptedValue)) {
            return null;
        }
        return $this->encryptor->decrypt($encryptedValue);
    }

    /**
     * Returns the globally configured Space ID.
     *
     * @return int|null
     */
    public function getSpaceId(): ?int
    {
        try {
            // Try to resolve the current Website context
            // This works for Frontend, Admin (Order View), and Store-specific operations.
            $website = $this->storeManager->getWebsite();

            $value = $this->scopeConfig->getValue(
                self::XML_PATH_SPACE_ID,
                ScopeInterface::SCOPE_WEBSITE,
                $website->getId()
            );
        } catch (\Exception $e) {
            // Fallback to Default Scope
            // If getWebsite() throws (e.g., in a global CLI command or generic CRON),
            // we attempt to read from the Default scope.
            $value = $this->scopeConfig->getValue(
                self::XML_PATH_SPACE_ID,
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT
            );
        }

        return $value === null ? null : (int)$value;
    }

    /**
     * Returns the Space ID configured for a specific website.
     *
     * Used by multi-website operations such as webhook installation, where each website
     * may be wired to a different space and the current store context is not available.
     *
     * @param int $websiteId
     * @return int|null
     */
    public function getSpaceIdForWebsite(int $websiteId): ?int
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_SPACE_ID,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        );
        return $value === null ? null : (int)$value;
    }

    /**
     * Returns the globally configured log level.
     *
     * @return string|null
     */
    public function getLogLevel(): ?string
    {
        // Log level respects inheritance (Store -> Website -> Default)
        $level = $this->scopeConfig->getValue(self::XML_PATH_LOG_LEVEL);
        return $level ? (string)$level : null;
    }

    /**
     * Whether plugin-core should auto-correct a line-item/total mismatch instead of aborting.
     *
     * This is the inverse of the "Enforce Consistency" admin setting (same field also used by
     * the legacy Order/Invoice path in Helper\LineItem::correctLineItems()): enforcing consistency
     * means being strict (throw on mismatch), so auto-correction must be off, and vice versa.
     *
     * @return bool|null
     */
    public function getLineItemConsistencyEnabled(): ?bool
    {
        try {
            $storeId = $this->storeManager->getStore()->getId();
            $value = $this->scopeConfig->getValue(
                self::XML_PATH_ENFORCE_LINE_ITEM_CONSISTENCY,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        } catch (\Exception $e) {
            // Fallback to Default Scope (e.g. CLI/cron contexts with no store in view).
            $value = $this->scopeConfig->getValue(
                self::XML_PATH_ENFORCE_LINE_ITEM_CONSISTENCY,
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT
            );
        }

        return $value === null ? null : !(bool) $value;
    }

    /**
     * Returns the configured checkout payment-form integration method, mapped to plugin-core's enum.
     *
     * Reuses the existing "Payment Form Integration Method" admin setting, read at store scope so
     * store-view overrides are honoured and the resolved mode matches the checkout call sites
     * (which read this same setting via the quote's store). Store scope still inherits the website
     * and default values through Magento's native config fallback. The string values
     * (iframe/lightbox/payment_page) are shared between Model\Config\Source\IntegrationMethod and
     * PluginCore's IntegrationMode.
     *
     * @return IntegrationMode
     */
    public function getIntegrationMode(): IntegrationMode
    {
        try {
            $store = $this->storeManager->getStore();
            $value = $this->scopeConfig->getValue(
                self::XML_PATH_INTEGRATION_METHOD,
                ScopeInterface::SCOPE_STORE,
                $store->getId()
            );
        } catch (\Exception $e) {
            $value = $this->scopeConfig->getValue(
                self::XML_PATH_INTEGRATION_METHOD,
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT
            );
        }

        return IntegrationMode::tryFrom((string) $value) ?? IntegrationMode::PAYMENT_PAGE;
    }

    /**
     * Returns a developer-only API base URL override for pointing at a non-production
     * environment, read from app/etc/env.php — deliberately NOT a system.xml/admin setting, since
     * this is meant for plugin developers testing locally, not merchants.
     *
     * @return string|null
     */
    public function getBaseUrl(): ?string
    {
        $value = $this->deploymentConfig->get(self::DEPLOYMENT_CONFIG_PATH_BASE_URL);
        return $value ? (string) $value : null;
    }
}
