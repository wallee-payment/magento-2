<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\CoreWebhook\ManualTask;

use Wallee\Payment\Model\Service\ManualTaskService;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Webhook\Command\WebhookCommand;
use Wallee\PluginCore\Webhook\WebhookContext;

class UpdateCommand extends WebhookCommand
{

    /**
     *
     * @param LoggerInterface $logger
     * @param WebhookContext $context
     * @param ManualTaskService $manualTaskService
     */
    public function __construct(
        LoggerInterface $logger,
        WebhookContext $context,
        private readonly ManualTaskService $manualTaskService
    ) {
        parent::__construct($context, $logger);
    }

    /**
     * Execute update command for the current webhook context.
     *
     * @return mixed
     */
    public function execute(): mixed
    {
        $this->logger->info('Running UpdateCommand');

        $this->manualTaskService->update();

        $this->logger->debug('Command Update for ManualTask completed.');

        return null;
    }
}
