<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\CoreWebhook\DeliveryIndication;

use Wallee\Payment\Model\CoreWebhook\BaseOrderLifecycleHandler;
use Wallee\PluginCore\Webhook\Enum\WebhookListener;
use Wallee\PluginCore\Webhook\WebhookContext;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\Sdk\Model\DeliveryIndication;
use Wallee\Sdk\Service\DeliveryIndicationService;
use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Lock\LockManagerInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class DeliveryIndicationWebhookLifecycleHandler extends BaseOrderLifecycleHandler
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
     */
    public function __construct(
        TransactionInfoRepositoryInterface $transactionInfoRepository,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        LockManagerInterface $lockManager,
        ResourceConnection $resource,
        SdkProvider $sdkProvider,
        LoggerInterface $logger
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
     * @param WebhookListener $listener
     * @param WebhookContext $context
     * @return object|null
     */
    protected function loadSdkEntity(WebhookListener $listener, WebhookContext $context): ?object
    {
        try {
            /** @param @var DeliveryIndicationService $service */
            $service = $this->sdkProvider->getService(DeliveryIndicationService::class);
            return $service->read($context->spaceId, $context->entityId);
        } catch (\Exception $e) {
            $this->logger->error("Failed to load SDK DeliveryIndication {$context->entityId}: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Find order linked to the given entity.
     *
     * @param object $entity
     * @return Order|null
     */
    protected function findOrder(object $entity): ?Order
    {
        if (!$entity instanceof DeliveryIndication) {
            return null;
        }

        $transaction = $entity->getTransaction();
        if (!$transaction) {
            return null;
        }

        // Use inherited helper
        $transactionInfo = $this->findTransactionInfoByTransactionId($transaction->getId());

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

        if ($this->sdkEntity instanceof DeliveryIndication) {
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
