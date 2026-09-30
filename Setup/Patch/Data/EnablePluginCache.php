<?php
namespace Wallee\Payment\Setup\Patch\Data;

use Magento\Framework\App\Cache\Manager;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;
use Wallee\Payment\Model\PluginCache\CacheType;

/**
 * Class EnablePluginCache
 *
 * Enables the plugin-core cache type once, when this patch first runs.
 */
class EnablePluginCache implements DataPatchInterface
{
    /**
     * @param Manager $cacheManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Manager $cacheManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     *
     * @return $this
     */
    public function apply()
    {
        try {
            $this->cacheManager->setEnabled([CacheType::TYPE_IDENTIFIER], true);
        } catch (FileSystemException $e) {
            $this->logger->warning(
                'Could not enable the ' . CacheType::TYPE_IDENTIFIER . ' cache type: ' . $e->getMessage()
            );
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases()
    {
        return [];
    }
}
