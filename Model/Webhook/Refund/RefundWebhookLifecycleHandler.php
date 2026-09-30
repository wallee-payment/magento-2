<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\Webhook\Refund;

use Wallee\Payment\Model\Webhook\BaseOrderLifecycleHandler;
use Wallee\PluginCore\Webhook\Enum\WebhookListener;
use Wallee\PluginCore\Webhook\WebhookContext;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Refund\Refund as CoreRefund;
use Wallee\PluginCore\Refund\RefundGatewayInterface;
use Wallee\PluginCore\Refund\Exception\RefundException;
use Wallee\PluginCore\Webhook\Exception\RetryableWebhookException;
use Wallee\Payment\Api\RefundJobRepositoryInterface;
use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Lock\LockManagerInterface;
use Wallee\PluginCore\Log\LoggerInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\Exception\NoSuchEntityException;

class RefundWebhookLifecycleHandler extends BaseOrderLifecycleHandler
{

    /**
     *
     * @param RefundJobRepositoryInterface $refundJobRepository
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param LockManagerInterface $lockManager
     * @param ResourceConnection $resource
     * @param SdkProvider $sdkProvider
     * @param LoggerInterface $logger
     * @param RefundGatewayInterface $pluginCoreRefundGateway
     */
    public function __construct(
        private readonly RefundJobRepositoryInterface $refundJobRepository,
        // Parent dependencies
        TransactionInfoRepositoryInterface $transactionInfoRepository,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        LockManagerInterface $lockManager,
        ResourceConnection $resource,
        SdkProvider $sdkProvider,
        LoggerInterface $logger,
        private readonly RefundGatewayInterface $pluginCoreRefundGateway
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
     * `findById()` never returns null — even a genuine "not found" surfaces as a
     * RefundException — so `isRetryable()` is the only thing telling a retryable
     * failure apart from a terminal one here. A retryable failure must not be
     * swallowed into a null: doing so would make preProcess() ack the webhook as
     * "nothing to do" and the portal would never retry it.
     *
     * @param WebhookListener $listener
     * @param WebhookContext $context
     * @return object|null
     * @throws RetryableWebhookException If the failure is retryable.
     */
    protected function loadSdkEntity(WebhookListener $listener, WebhookContext $context): ?object
    {
        try {
            return $this->pluginCoreRefundGateway->findById($context->spaceId, $context->entityId);
        } catch (RefundException $e) {
            if ($e->isRetryable()) {
                throw new RetryableWebhookException(
                    "Failed to load Refund {$context->entityId}: " . $e->getMessage(),
                    null,
                    $e
                );
            }

            $this->logger->error(
                "Failed to load Refund {$context->entityId}: " . $e->getMessage(),
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
        if (!$entity instanceof CoreRefund) {
            return null;
        }

        // Use inherited helper
        $transactionInfo = $this->findTransactionInfoByTransactionId($entity->transactionId);

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

        if ($this->sdkEntity instanceof CoreRefund) {
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
    protected function doPostProcess(?object $entity, ?Order $order, mixed $commandResult): void
    {
        if ($entity instanceof CoreRefund) {
            try {
                $refundJob = $this->refundJobRepository->getByExternalId($entity->externalId);
                $this->refundJobRepository->delete($refundJob);
                $this->logger->debug('Deleted local refund job after gateway confirmation.', [
                    'refundJobId' => $refundJob->getId(),
                    'externalId' => $entity->externalId,
                ]);
            } catch (NoSuchEntityException $e) {
                // Expected for refunds not created from Magento (e.g. initiated in the portal):
                // there was never a local job to clean up.
                $this->logger->debug('No local refund job to clean up.', [
                    'externalId' => $entity->externalId,
                ]);
            }
        }
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
        // Do nothing
    }
}
