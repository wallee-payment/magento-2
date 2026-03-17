<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\CoreWebhook;

use Wallee\PluginCore\Webhook\DefaultStateFetcher;
use Wallee\Sdk\Service\WebhookEncryptionService;
use Wallee\Sdk\Service\TransactionService;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Magento-specific wrapper for the DefaultStateFetcher.
 * Its only job is to get the spaceId from Magento's configuration.
 */
class StateFetcher extends DefaultStateFetcher
{

}
