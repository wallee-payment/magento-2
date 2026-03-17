<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\CoreWebhook\TransactionInvoice;

use Wallee\Payment\Model\CoreWebhook\BaseOrderLifecycleHandler;
use Wallee\PluginCore\Webhook\Enum\WebhookListener;
use Wallee\PluginCore\Webhook\WebhookContext;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\Sdk\Model\TransactionInvoice;
use Wallee\Sdk\Service\TransactionInvoiceService;
use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Lock\LockManagerInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender as OrderEmailSender;

class TransactionInvoiceWebhookLifecycleHandler extends BaseOrderLifecycleHandler
{

    /**
     *
     * @param OrderEmailSender $orderEmailSender
     * @param ResourceConnection $resource
     * @param LoggerInterface $logger
     * @param LockManagerInterface $lockManager
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SdkProvider $sdkProvider
     */
    public function __construct(
        private readonly OrderEmailSender $orderEmailSender,
        // Parent dependencies
        ResourceConnection $resource,
        LoggerInterface $logger,
        LockManagerInterface $lockManager,
        TransactionInfoRepositoryInterface $transactionInfoRepository,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SdkProvider $sdkProvider
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
            /** @var TransactionInvoiceService $service */
            $service = $this->sdkProvider->getService(TransactionInvoiceService::class);
            return $service->read($context->spaceId, $context->entityId);
        } catch (\Exception $e) {
            $this->logger->error("Failed to load SDK TransactionInvoice {$context->entityId}: " . $e->getMessage());
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
        if (!$entity instanceof TransactionInvoice) {
            return null;
        }

        $transactionId = $entity->getLinkedTransaction();
        if (!$transactionId) {
            return null;
        }

        // Use inherited helper
        $transactionInfo = $this->findTransactionInfoByTransactionId($transactionId);

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

        if ($this->sdkEntity instanceof TransactionInvoice) {
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
    protected function doSendEmail(Order $order): void
    {
        // Intentionally left blank. Email has been sent already when the order was authorized.
    }
}
