<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\CoreWebhook;

use Wallee\PluginCore\Webhook\WebhookLifecycleHandler;
use Wallee\PluginCore\Webhook\Enum\WebhookListener;
use Wallee\PluginCore\Webhook\WebhookContext;

/**
 * A composite handler that routes webhook lifecycle events to a specific
 * handler based on the entity type (e.g., Transaction, Refund).
 */
class CompositeWebhookLifecycleHandler implements WebhookLifecycleHandler
{
    /**
     * @param WebhookLifecycleHandler[] $handlers A map of specific handlers, injected via di.xml
     * @param WebhookLifecycleHandler $defaultHandler A fallback handler
     */
    public function __construct(
        private readonly array $handlers,
        private readonly WebhookLifecycleHandler $defaultHandler
    ) {
    }

    /**
     * Finds the correct handler for the listener and delegates the call.
     *
     * @param WebhookListener $listener
     * @return WebhookLifecycleHandler
     */
    private function getHandlerFor(WebhookListener $listener): WebhookLifecycleHandler
    {
        $entityType = $listener->getTechnicalName();
        return $this->handlers[$entityType] ?? $this->defaultHandler;
    }

    /**
     * Get lockable resources from the corresponding handler.
     *
     * @param WebhookListener $listener
     * @param WebhookContext $context
     * @return array
     */
    public function getLockableResources(WebhookListener $listener, WebhookContext $context): array
    {
        return $this->getHandlerFor($listener)->getLockableResources($listener, $context);
    }

    /**
     * Fetches the last processed state by delegating to the correct handler.
     *
     * @param WebhookListener $listener
     * @param int $entityId
     * @return string
     */
    public function getLastProcessedState(WebhookListener $listener, int $entityId): string
    {
        return $this->getHandlerFor($listener)->getLastProcessedState($listener, $entityId);
    }

    /**
     * Execute pre-processing via the corresponding handler.
     *
     * @param WebhookListener $listener
     * @param WebhookContext $context
     * @return bool
     */
    public function preProcess(WebhookListener $listener, WebhookContext $context): bool
    {
        return $this->getHandlerFor($listener)->preProcess($listener, $context);
    }

    /**
     * Execute post-processing via the corresponding handler.
     *
     * @param WebhookListener $listener
     * @param WebhookContext $context
     * @param mixed $commandResult
     * @return void
     */
    public function postProcess(WebhookListener $listener, WebhookContext $context, mixed $commandResult): void
    {
        $this->getHandlerFor($listener)->postProcess($listener, $context, $commandResult);
    }

    /**
     * Handle webhook processing failure via the corresponding handler.
     *
     * @param WebhookListener $listener
     * @param WebhookContext $context
     * @param \Throwable $exception
     * @return void
     */
    public function onFailure(WebhookListener $listener, WebhookContext $context, \Throwable $exception): void
    {
        $this->getHandlerFor($listener)->onFailure($listener, $context, $exception);
    }
}
