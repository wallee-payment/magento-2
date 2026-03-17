<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\CoreWebhook\Transaction;

use Wallee\Payment\Model\CoreWebhook\BaseOrderLifecycleHandler;
use Wallee\PluginCore\Webhook\Enum\WebhookListener;
use Wallee\PluginCore\Webhook\WebhookContext;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\Sdk\Model\Transaction;
use Wallee\Sdk\Model\TransactionState;
use Wallee\Sdk\Service\TransactionService;
use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Wallee\Payment\Api\TransactionInfoManagementInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Lock\LockManagerInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender as OrderEmailSender;

class TransactionWebhookLifecycleHandler extends BaseOrderLifecycleHandler
{

    /**
     *
     * @param TransactionInfoManagementInterface $transactionInfoManagement
     * @param OrderEmailSender $orderEmailSender
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param LockManagerInterface $lockManager
     * @param ResourceConnection $resource
     * @param SdkProvider $sdkProvider
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly TransactionInfoManagementInterface $transactionInfoManagement,
        private readonly OrderEmailSender $orderEmailSender,
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
     * Implements abstract method from BaseOrderLifecycleHandler.
     *
     * @param WebhookContext $context
     * @return int|null
     */
    protected function getOrderId(WebhookContext $context): ?int
    {
        $info = $this->findTransactionInfoByTransactionId($context->entityId);
        return $info ? (int)$info->getOrderId() : null;
    }

    /**
     * Changed from private to protected to match parent.
     *
     * @param WebhookListener $listener
     * @param WebhookContext $context
     * @return object|null
     */
    protected function loadSdkEntity(WebhookListener $listener, WebhookContext $context): ?object
    {
        try {
            /** @var TransactionService $txService */
            $txService = $this->sdkProvider->getService(TransactionService::class);

            $transactionInfo = $this->findTransactionInfoByTransactionId($context->entityId);

            if ($transactionInfo) {
                return $txService->read($transactionInfo->getSpaceId(), $context->entityId);
            }
        } catch (\Exception $e) {
            $this->logger->error("Failed to load SDK Transaction {$context->entityId}: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Changed from private to protected to match parent.
     *
     * @param object $entity
     * @return Order|null
     */
    protected function findOrder(object $entity): ?Order
    {
        if (!$entity instanceof Transaction) {
            return null;
        }

        $transactionInfo = $this->findTransactionInfoByTransactionId($entity->getId());
        if ($transactionInfo) {
            return $this->orderRepository->get($transactionInfo->getOrderId());
        }
        return null;
    }

    /**
     * Changed from private to protected to match parent.
     *
     * @param object|null $entity
     * @param Order|null $order
     * @param mixed $commandResult
     * @return void
     */
    protected function doPostProcess(?object $entity, ?Order $order, mixed $commandResult): void
    {
        if ($entity instanceof Transaction && $order instanceof Order) {
            $this->transactionInfoManagement->update($entity, $order);
        }
    }

    /**
     * Changed from private to protected to match parent.
     *
     * @param Order $order
     * @return void
     */
    protected function doSendEmail(Order $order): void
    {
        // Only trigger the order email when the transaction is explicitly AUTHORIZED
        if ($this->context && $this->context->remoteState !== TransactionState::AUTHORIZED) {
            return;
        }

        // Allowed & Duplicate Check
        if (
            !$order->getStore()->getConfig('wallee_payment/email/order') || $order->getEmailSent()
        ) {
            return;
        }

        // We block email sending for states where the payment is not yet confirmed
        // or the order is effectively dead.
        $blockedStates = [
            Order::STATE_CANCELED,
            Order::STATE_CLOSED,
            Order::STATE_NEW,
            Order::STATE_PENDING_PAYMENT,
            Order::STATE_PAYMENT_REVIEW,
            Order::STATE_HOLDED,
        ];


        if (in_array($order->getState(), $blockedStates, true)) {
            $this->logger->debug(
                "Skipping email: Order {$order->getIncrementId()} is in state {$order->getState()}"
            );
            return;
        }

        try {
            $this->orderEmailSender->send($order);
        } catch (\Exception $e) {
            // Catch email failures so we don't crash the webhook transaction
            $this->logger->error(
                "Failed to send email for order {$order->getIncrementId()}: " . $e->getMessage()
            );
        }
    }
}
