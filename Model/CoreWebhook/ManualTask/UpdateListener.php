<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\CoreWebhook\ManualTask;

use Wallee\Payment\Model\Service\ManualTaskService;
use Wallee\PluginCore\Webhook\Command\WebhookCommandInterface;
use Wallee\PluginCore\Webhook\Listener\WebhookListenerInterface;
use Wallee\PluginCore\Webhook\WebhookContext;
use Wallee\PluginCore\Log\LoggerInterface;

class UpdateListener implements WebhookListenerInterface
{

    /**
     *
     * @param LoggerInterface $logger
     * @param ManualTaskService $manualTaskService
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ManualTaskService $manualTaskService,
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
        return new UpdateCommand($this->logger, $context, $this->manualTaskService);
    }
}
