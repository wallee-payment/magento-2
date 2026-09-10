<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\Settings;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Wallee\PluginCore\Sdk\ClientMetadata;
use Wallee\PluginCore\Sdk\ClientMetadataProviderInterface;

/**
 * Identifies this Magento installation and plugin version to the portal API.
 */
class ClientMetadataProvider implements ClientMetadataProviderInterface
{
    /**
     * @var string
     */
    private const XML_PATH_PLUGIN_VERSION = 'wallee_payment/information/version';

    /**
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param ProductMetadataInterface $productMetadata
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ProductMetadataInterface $productMetadata,
    ) {
    }

    /**
     * Returns the metadata identifying this Magento installation and plugin version.
     *
     * @return ClientMetadata|null
     */
    public function getClientMetadata(): ?ClientMetadata
    {
        return new ClientMetadata(
            shopSystem: 'magento',
            shopSystemVersion: $this->productMetadata->getVersion(),
            pluginVersion: '3.5.0',
        );
    }
}
