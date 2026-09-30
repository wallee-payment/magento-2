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

namespace Wallee\Payment\Model\PluginCache;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;

/**
 * Magento cache type that stores plugin-core caches.
 *
 * As a real cache type, it appears as a standard cache in the shop. This
 * lets admin configure it in the backend and lets Magento cache functions
 * (for example, `bin/magento cache:clean`) operate on it.
 */
class CacheType extends TagScope
{
    public const TYPE_IDENTIFIER = 'wallee_plugin_core';
    public const CACHE_TAG = 'WALLEE_PLUGIN_CORE';

    /**
     * @param FrontendPool $cacheFrontendPool
     */
    public function __construct(FrontendPool $cacheFrontendPool)
    {
        parent::__construct($cacheFrontendPool->get(self::TYPE_IDENTIFIER), self::CACHE_TAG);
    }
}
