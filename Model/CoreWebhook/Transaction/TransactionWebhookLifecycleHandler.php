<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\CoreWebhook\Transaction;

use Wallee\Payment\Model\CoreWebhook\BaseOrderLifecycleHandler;
use Wallee\PluginCore\Webhook\Enum\WebhookListener;
use Wallee\PluginCore\Webhook\WebhookContext;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Transaction\Transaction as CoreTransaction;
use Wallee\PluginCore\Transaction\TransactionGatewayInterface;
use Wallee\PluginCore\Transaction\Exception\TransactionException;
use Wallee\PluginCore\Transaction\State as CoreTransactionState;
use Wallee\PluginCore\Webhook\Enum\LifecycleAction;
use Wallee\PluginCore\Webhook\TransactionActionResolver;
use Wallee\Sdk\Model\Transaction;
use Wallee\Sdk\Service\TransactionService;
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

        // TransactionInfoManagementInterface::update() is a public API contract still
        // typed to (and dependent on) the legacy SDK Transaction model — it reads
        // payment-connector/payment-method configuration data that plugin-core's domain
        // Transaction does not carry. Re-read via the SDK here, scoped to this one call,
        // rather than widen that interface as a side effect of this webhook migration.
        try {
            /** @var TransactionService $txService */
            $txService = $this->sdkProvider->getService(TransactionService::class);
            $sdkTransaction = $txService->read($entity->spaceId, $entity->id);
            $this->transactionInfoManagement->update($sdkTransaction, $order);
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
