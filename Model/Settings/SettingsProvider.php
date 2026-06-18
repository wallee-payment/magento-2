<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\Settings;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
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
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor,
        private readonly StoreManagerInterface $storeManager,
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
}
