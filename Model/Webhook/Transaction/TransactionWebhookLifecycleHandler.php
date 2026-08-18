<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\Webhook\Transaction;

use Wallee\Payment\Model\Webhook\BaseOrderLifecycleHandler;
use Wallee\PluginCore\Webhook\Enum\WebhookListener;
use Wallee\PluginCore\Webhook\WebhookContext;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Transaction\Transaction as CoreTransaction;
use Wallee\PluginCore\Transaction\TransactionGatewayInterface;
use Wallee\PluginCore\Transaction\Exception\TransactionException;
use Wallee\PluginCore\Transaction\State as CoreTransactionState;
use Wallee\PluginCore\Webhook\Enum\LifecycleAction;
use Wallee\PluginCore\Webhook\TransactionActionResolver;
use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Wallee\Payment\Api\TransactionInfoManagementInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Lock\LockManagerInterface;
use Wallee\PluginCore\Log\LoggerInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender as OrderEmailSender;

class TransactionWebhookLifecycleHandler extends BaseOrderLifecycleHandler
{

    /**
     * The live transaction state last synced to local storage during the current
     * transition-path walk, or null before the first step has synced.
     *
     * @var string|null
     */
    private ?string $lastSyncedState = null;

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
     * @param TransactionGatewayInterface $transactionGateway
     * @param TransactionActionResolver $transactionActionResolver
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
        LoggerInterface $logger,
        private readonly TransactionGatewayInterface $transactionGateway,
        private readonly TransactionActionResolver $transactionActionResolver
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
            $transactionInfo = $this->findTransactionInfoByTransactionId($context->entityId);

            if ($transactionInfo) {
                return $this->transactionGateway->find((int) $transactionInfo->getSpaceId(), (int) $context->entityId);
            }
        } catch (TransactionException $e) {
            $this->logger->error('Failed to load Transaction.', [
                'entityId' => $context->entityId,
                'exception' => $e,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load Transaction.', [
                'entityId' => $context->entityId,
                'exception' => $e,
            ]);
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
        if (!$entity instanceof CoreTransaction) {
            return null;
        }

        $transactionInfo = $this->findTransactionInfoByTransactionId($entity->id);
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
        if (!$entity instanceof CoreTransaction || !$order instanceof Order) {
            return;
        }

        // WebhookProcessor calls postProcess() once per step of the transition path, not
        // once per executed command — loadSdkEntity() always fetches the current live state,
        // so a multi-step path (e.g. PENDING→CONFIRMED→PROCESSING→AUTHORIZED) can otherwise
        // trigger several identical syncs in a row when the remote transaction has already
        // moved past every intermediate step by the time we catch up. Skip the redundant ones.
        if ($this->lastSyncedState === $entity->state->value) {
            return;
        }

        try {
            $this->transactionInfoManagement->update($entity, $order);
            // Only mark as synced once the write actually succeeds, so a transient failure
            // on this step doesn't suppress a retry on a later step with the same state.
            $this->lastSyncedState = $entity->state->value;
        } catch (\Exception $e) {
            $this->logger->error('Failed to sync TransactionInfo.', [
                'orderId' => $order->getIncrementId(),
                'transactionId' => $entity->id,
                'exception' => $e,
            ]);
            return;
        }

        $this->logger->debug('Synced TransactionInfo.', [
            'orderId' => $order->getIncrementId(),
            'transactionId' => $entity->id,
        ]);
    }

    /**
     * Changed from private to protected to match parent.
     *
     * @param Order $order
     * @return void
     */
    protected function doSendEmail(Order $order): void
    {
        // Only trigger the order email when the resolved lifecycle action is AUTHORIZE
        $state = $this->context ? CoreTransactionState::tryFrom($this->context->remoteState) : null;
        if ($state === null || $this->transactionActionResolver->resolve($state) !== LifecycleAction::AUTHORIZE) {
            return;
        }

        // Allowed & Duplicate Check
        if (!$order->getStore()->getConfig('wallee_payment/email/order') || $order->getEmailSent()) {
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
            $this->logger->error('Failed to send order confirmation email.', [
                'orderId' => $order->getIncrementId(),
                'exception' => $e,
            ]);
            return;
        }

        $this->logger->info('Sent order confirmation email.', ['orderId' => $order->getIncrementId()]);
    }
}
