<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\CoreWebhook\PaymentMethodConfiguration;

use Wallee\Payment\Api\PaymentMethodConfigurationManagementInterface;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Webhook\Command\WebhookCommandInterface;
use Wallee\PluginCore\Webhook\Listener\WebhookListenerInterface;
use Wallee\PluginCore\Webhook\WebhookContext;

class SynchronizeListener implements WebhookListenerInterface
{

    /**
     *
     * @param LoggerInterface $logger
     * @param PaymentMethodConfigurationManagementInterface $management
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly PaymentMethodConfigurationManagementInterface $management
    ) {
    }

    /**
     * Create webhook command for the given context.
     *
     * @param WebhookContext $context
     * @return WebhookCommandInterface
     */
    public function getCommand(WebhookContext $context): WebhookCommandInterface
    {
        return new SynchronizeCommand($context, $this->logger, $this->management);
    }
}
