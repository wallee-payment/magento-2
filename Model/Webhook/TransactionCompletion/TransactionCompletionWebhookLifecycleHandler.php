<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\Webhook\TransactionCompletion;

use Wallee\Payment\Model\Webhook\BaseOrderLifecycleHandler;
use Wallee\PluginCore\Webhook\Enum\WebhookListener;
use Wallee\PluginCore\Webhook\WebhookContext;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Transaction\Completion\Exception\CompletionException;
use Wallee\PluginCore\Transaction\Completion\TransactionCompletion;
use Wallee\PluginCore\Transaction\Completion\TransactionCompletionGatewayInterface;
use Wallee\PluginCore\Webhook\Exception\RetryableWebhookException;
use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Lock\LockManagerInterface;
use Wallee\PluginCore\Log\LoggerInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class TransactionCompletionWebhookLifecycleHandler extends BaseOrderLifecycleHandler
{

    /**
     *
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param LockManagerInterface $lockManager
     * @param ResourceConnection $resource
     * @param SdkProvider $sdkProvider
     * @param LoggerInterface $logger
     * @param TransactionCompletionGatewayInterface $completionGateway
     */
    public function __construct(
        TransactionInfoRepositoryInterface $transactionInfoRepository,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        LockManagerInterface $lockManager,
        ResourceConnection $resource,
        SdkProvider $sdkProvider,
        LoggerInterface $logger,
        private readonly TransactionCompletionGatewayInterface $completionGateway
    ) {
        parent::__construct(
            $resource,
            $logger,
            $lockManager,
            $transactionInfoRepository,
            $orderRepository,
            $searchCriteriaBuilder,
            $sdkProvider
        );
    }

    /**
     * Load SDK entity for the given webhook context.
     *
     * A retryable failure must not be swallowed into a null here — doing so would
     * make preProcess() ack the webhook as "nothing to do" and the portal would
     * never retry it. A non-retryable failure returns null, same as the gateway
     * confirming the entity does not exist.
     *
     * @param WebhookListener $listener
     * @param WebhookContext $context
     * @return object|null
     * @throws RetryableWebhookException If the failure is retryable.
     */
    protected function loadSdkEntity(WebhookListener $listener, WebhookContext $context): ?object
    {
        try {
            return $this->completionGateway->find($context->spaceId, $context->entityId);
        } catch (CompletionException $e) {
            if ($e->isRetryable()) {
                throw new RetryableWebhookException(
                    "Failed to load TransactionCompletion {$context->entityId}: " . $e->getMessage(),
                    null,
                    $e
                );
            }

            $this->logger->error(
                "Failed to load TransactionCompletion {$context->entityId}: " . $e->getMessage(),
                ['exception' => $e]
            );

            return null;
        }
    }

    /**
     * Find order linked to the given entity.
     *
     * @param object $entity
     * @return Order|null
     */
    protected function findOrder(object $entity): ?Order
    {
        if (!$entity instanceof TransactionCompletion) {
            return null;
        }

        if (!$entity->linkedTransactionId) {
            return null;
        }

        // Use inherited helper
        $transactionInfo = $this->findTransactionInfoByTransactionId($entity->linkedTransactionId);

        if ($transactionInfo) {
            return $this->orderRepository->get($transactionInfo->getOrderId());
        }
        return null;
    }

    /**
     * Get order ID for the current webhook context.
     *
     * @param WebhookContext $context
     * @return int|null
     */
    protected function getOrderId(WebhookContext $context): ?int
    {
        if ($this->order) {
            return (int) $this->order->getEntityId();
        }

        if ($this->sdkEntity instanceof TransactionCompletion) {
            $order = $this->findOrder($this->sdkEntity);
            return $order ? (int) $order->getEntityId() : null;
        }

        return null;
    }

    /**
     * Run post-processing after command execution.
     *
     * @param object|null $entity
     * @param Order|null $order
     * @param mixed $commandResult
     * @return void
     */
    // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedFunction
    protected function doPostProcess(?object $entity, ?Order $order, mixed $commandResult): void
    {
        // Intentionally left blank.
    }

    /**
     * Send order email if enabled and not already sent.
     *
     * @param Order $order
     * @return void
     */
    // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedFunction
    protected function doSendEmail(Order $order): void
    {
        // Intentionally left blank.
    }
}
