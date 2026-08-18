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
namespace Wallee\Payment\Model\Service;

use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use Wallee\Payment\Model\Webhook\RegistryConfigurer;
use Wallee\Payment\Model\Settings\SettingsProvider;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Webhook\WebhookProcessor;
use Wallee\PluginCore\Webhook\WebhookService as CoreWebhookService;

/**
 * Service to handle webhooks.
 */
class WebhookService
{

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var CoreWebhookService
     */
    private $pluginCoreWebhookService;

    /**
     * @var RegistryConfigurer
     */
    private $registryConfigurer;

    /**
     * @var WebhookProcessor
     */
    private $webhookProcessor;

    /**
     * @var SettingsProvider
     */
    private $settingsProvider;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param StoreManagerInterface $storeManager
     * @param CoreWebhookService $pluginCoreWebhookService
     * @param RegistryConfigurer $registryConfigurer
     * @param WebhookProcessor $webhookProcessor
     * @param SettingsProvider $settingsProvider
     * @param LoggerInterface $logger
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        CoreWebhookService $pluginCoreWebhookService,
        RegistryConfigurer $registryConfigurer,
        WebhookProcessor $webhookProcessor,
        SettingsProvider $settingsProvider,
        LoggerInterface $logger
    ) {
        $this->storeManager = $storeManager;
        $this->pluginCoreWebhookService = $pluginCoreWebhookService;
        $this->registryConfigurer = $registryConfigurer;
        $this->webhookProcessor = $webhookProcessor;
        $this->settingsProvider = $settingsProvider;
        $this->logger = $logger;
    }

    /**
     * Installs webhooks.
     *
     * Installs the necessary webhooks in wallee for every configured space,
     * using the base URL of the website where each space ID is configured.
     *
     * @return void
     */
    public function install()
    {
        $this->registryConfigurer->configure();
        $registry = $this->webhookProcessor->getListenerRegistry();

        $targets = $this->getWebhookTargets();
        $this->logger->debug('Installing webhooks.', ['targetCount' => count($targets)]);

        foreach ($targets as $target) {
            try {
                $this->pluginCoreWebhookService->synchronizeWebhooks(
                    $target['spaceId'],
                    $target['url'],
                    'Magento 2',
                    $registry
                );
            } catch (\Exception $e) {
                $this->logger->error('Webhook sync failed.', [
                    'spaceId' => $target['spaceId'],
                    'url' => $target['url'],
                    'exception' => $e,
                ]);
                throw $e;
            }

            $this->logger->info('Webhook sync completed.', [
                'spaceId' => $target['spaceId'],
                'url' => $target['url'],
            ]);
        }
    }

    /**
     * Retrieves an array of spaceId and url pairs.
     *
     * Collects distinct (spaceId, url) pairs across all websites, so webhooks are
     * registered against the domain where the space ID is actually configured.
     *
     * @return array<int, array{spaceId:int, url:string}>
     */
    private function getWebhookTargets(): array
    {
        $targets = [];
        $seen = [];
        foreach ($this->storeManager->getWebsites() as $website) {
            $spaceId = $this->settingsProvider->getSpaceIdForWebsite((int)$website->getId());
            if ($spaceId === null) {
                continue;
            }
            $url = $this->getUrlForWebsite($website);
            $key = $spaceId . '|' . $url;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $targets[] = ['spaceId' => $spaceId, 'url' => $url];
        }
        return $targets;
    }

    /**
     * Gets the webhook endpoint URL for a specific website.
     *
     * @param WebsiteInterface $website
     * @return string
     */
    private function getUrlForWebsite(WebsiteInterface $website): string
    {
        $route = 'index.php/wallee_payment/webhook/index/';
        return $website->getDefaultStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB) . $route;
    }
}
