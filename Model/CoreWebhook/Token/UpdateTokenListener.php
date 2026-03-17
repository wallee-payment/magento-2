<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\CoreWebhook\Token;

use Wallee\Payment\Api\TokenInfoManagementInterface;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Webhook\WebhookContext;
use Wallee\PluginCore\Webhook\Command\WebhookCommandInterface;
use Wallee\PluginCore\Webhook\Listener\WebhookListenerInterface;

class UpdateTokenListener implements WebhookListenerInterface
{
    /**
     *
     * @param LoggerInterface $logger
     * @param TokenInfoManagementInterface $tokenInfoManagement
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly TokenInfoManagementInterface $tokenInfoManagement,
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
        return new UpdateTokenCommand($context, $this->logger, $this->tokenInfoManagement);
    }
}
