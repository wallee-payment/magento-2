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
namespace Wallee\Payment\Model;

use Magento\Framework\App\Cache\Type\Config as ConfigCache;
use Magento\Framework\App\Cache\TypeListInterface as CacheTypeList;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wallee\Payment\Api\PaymentMethodConfigurationManagementInterface;
use Wallee\Payment\Model\Settings\SettingsProvider;
use Wallee\PluginCore\PaymentMethod\PaymentMethodService;

/**
 * Payment method configuration management service.
 */
class PaymentMethodConfigurationManagement implements PaymentMethodConfigurationManagementInterface
{
    /**
     *
     * @var PaymentMethodService
     */
    private $pluginCorePaymentMethodService;

    /**
     *
     * @var SettingsProvider
     */
    private $settingsProvider;

    /**
     *
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     *
     * @var CacheTypeList
     */
    private $cacheTypeList;

    /**
     *
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     *
     * @param PaymentMethodService $pluginCorePaymentMethodService
     * @param SettingsProvider $settingsProvider
     * @param StoreManagerInterface $storeManager
     * @param CacheTypeList $cacheTypeList
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        PaymentMethodService $pluginCorePaymentMethodService,
        SettingsProvider $settingsProvider,
        StoreManagerInterface $storeManager,
        CacheTypeList $cacheTypeList,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->pluginCorePaymentMethodService = $pluginCorePaymentMethodService;
        $this->settingsProvider = $settingsProvider;
        $this->storeManager = $storeManager;
        $this->cacheTypeList = $cacheTypeList;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Synchronize payment method configurations from the gateway.
     *
     * Delegates per-space synchronization to the plugin-core PaymentMethodService,
     * which fetches payment methods from the portal and persists them via the
     * Magento repository adapter.
     *
     * @param OutputInterface|null $output
     * @return void
     */
    public function synchronize(?OutputInterface $output = null)
    {
        if ($output) {
            $output->writeln('Synchronizing payment methods:');
        }

        $locales = $this->getShopLocales();

        foreach ($this->getConfiguredSpaceIds() as $spaceId) {
            if ($output) {
                $output->writeln('Space ' . $spaceId);
            }
            $this->pluginCorePaymentMethodService->synchronize($spaceId, $locales);
        }

        $this->cacheTypeList->cleanType(ConfigCache::TYPE_IDENTIFIER);

        if ($output) {
            $output->writeln('Synchronization completed.');
        }
    }

    /**
     * Returns the distinct locales configured at default, website and store scope.
     *
     * The API answers each request in a single language, and the repository writes
     * titles for every one of these scopes, so each of their locales has to be fetched.
     *
     * @return string[]
     */
    private function getShopLocales(): array
    {
        $locales = [(string)$this->scopeConfig->getValue('general/locale/code')];
        foreach ($this->storeManager->getWebsites() as $website) {
            $locales[] = (string)$this->scopeConfig->getValue(
                'general/locale/code',
                ScopeInterface::SCOPE_WEBSITES,
                $website->getId()
            );
        }
        foreach ($this->storeManager->getStores() as $store) {
            $locales[] = (string)$this->scopeConfig->getValue(
                'general/locale/code',
                ScopeInterface::SCOPE_STORES,
                $store->getId()
            );
        }
        return array_values(array_unique(array_filter($locales)));
    }

    /**
     * Returns the distinct space IDs configured across all websites.
     *
     * @return int[]
     */
    private function getConfiguredSpaceIds(): array
    {
        $spaceIds = [];
        foreach ($this->storeManager->getWebsites() as $website) {
            $spaceId = $this->settingsProvider->getSpaceIdForWebsite((int)$website->getId());
            if ($spaceId !== null && !in_array($spaceId, $spaceIds, true)) {
                $spaceIds[] = $spaceId;
            }
        }
        return $spaceIds;
    }
}
