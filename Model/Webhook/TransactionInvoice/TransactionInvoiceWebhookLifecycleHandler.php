<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\Webhook\TransactionInvoice;

use Wallee\Payment\Model\Webhook\BaseOrderLifecycleHandler;
use Wallee\PluginCore\Webhook\Enum\WebhookListener;
use Wallee\PluginCore\Webhook\WebhookContext;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Transaction\Invoice\Exception\InvoiceException;
use Wallee\PluginCore\Transaction\Invoice\Invoice;
use Wallee\PluginCore\Transaction\Invoice\InvoiceGatewayInterface;
use Wallee\PluginCore\Webhook\Exception\RetryableWebhookException;
use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Lock\LockManagerInterface;
use Wallee\PluginCore\Log\LoggerInterface;
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
     * @param InvoiceGatewayInterface $invoiceGateway
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
        SdkProvider $sdkProvider,
        private readonly InvoiceGatewayInterface $invoiceGateway
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
            return $this->invoiceGateway->find($context->spaceId, $context->entityId);
        } catch (InvoiceException $e) {
            if ($e->isRetryable()) {
                throw new RetryableWebhookException(
                    "Failed to load TransactionInvoice {$context->entityId}: " . $e->getMessage(),
                    null,
                    $e
                );
            }

            $this->logger->error(
                "Failed to load TransactionInvoice {$context->entityId}: " . $e->getMessage(),
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
        if (!$entity instanceof Invoice) {
            return null;
        }

        $transactionId = $entity->linkedTransactionId;
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

        if ($this->sdkEntity instanceof Invoice) {
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
        // Intentionally left blank. Email has been sent already when the order was authorized.
    }
}
