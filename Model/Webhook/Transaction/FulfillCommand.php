<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\Webhook\Transaction;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Wallee\PluginCore\Transaction\State as CoreTransactionState;
use Wallee\PluginCore\Webhook\Command\WebhookCommand;
use Wallee\PluginCore\Webhook\Enum\LifecycleAction;
use Wallee\PluginCore\Webhook\TransactionActionResolver;
use Wallee\PluginCore\Webhook\WebhookContext;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;

class FulfillCommand extends WebhookCommand
{
    use TransactionCommandTrait;

    /**
     *
     * @param WebhookContext $context
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param OrderResourceModel $orderResourceModel
     * @param TransactionActionResolver $transactionActionResolver
     */
    public function __construct(
        WebhookContext $context,
        LoggerInterface $logger,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly TransactionInfoRepositoryInterface $transactionInfoRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly OrderResourceModel $orderResourceModel,
        private readonly TransactionActionResolver $transactionActionResolver,
    ) {
        parent::__construct($context, $logger);
    }

    /**
     * Execute fulfill command for the current webhook context.
     *
     * @return mixed
     */
    public function execute(): mixed
    {
        $this->logger->info(sprintf('Running FulfillCommand for entity ID: %d', $this->context->entityId));

        $order = $this->findOrder();
        if (!$order) {
            $this->logger->warning(
                sprintf(
                    'FulfillCommand: No order found for entity ID: %d',
                    $this->context->entityId
                )
            );
            return null;
        }

        $remoteState = CoreTransactionState::tryFrom($this->context->remoteState);
        $action = $remoteState !== null ? $this->transactionActionResolver->resolve($remoteState) : null;

        if ($action !== LifecycleAction::FULFILL) {
            $this->logger->warning(sprintf(
                'FulfillCommand: Resolved action %s does not imply fulfillment; skipping order %s.',
                $action !== null ? $action->name : 'UNKNOWN',
                $order->getIncrementId()
            ));
            return null;
        }

        // Get a FRESH copy of the order from the DB to ensure we have the latest data.
        $this->orderResourceModel->load($order, $order->getId());

        // Handle Payment Review
        if ($order->getState() == Order::STATE_PAYMENT_REVIEW) {
            $this->logger->info('FulfillCommand: Order is in Payment Review. Approving transaction.');
            /** @var \Magento\Sales\Model\Order\Payment $payment */
            $payment = $order->getPayment();
            $payment->setIsTransactionApproved(true);
            $payment->update(false);
        }

        // Ensure Order is fully "Processing"
        $shouldUpdate = $order->canHold() || $order->getState() === Order::STATE_PAYMENT_REVIEW;

        if ($shouldUpdate) {
            if ($order->getState() !== Order::STATE_PROCESSING || $order->getStatus() !== Order::STATE_PROCESSING
            ) {

                $this->logger->info(sprintf(
                    'Transitioning order %s to Processing state. Previous state: %s/%s',
                    $order->getIncrementId(),
                    $order->getState(),
                    $order->getStatus()
                ));

                $order->setState(Order::STATE_PROCESSING);
                $order->setStatus(Order::STATE_PROCESSING);

                $order->addStatusToHistory(
                    Order::STATE_PROCESSING,
                    __('The order can be fulfilled now.')->render(),
                    false
                );
            } else {
                $this->logger->debug('Order is already in correct Processing state/status.');
                $order->addStatusToHistory(
                    $order->getStatus(),
                    __('The order can be fulfilled now.')->render(),
                    false
                );
            }
        } else {
            $this->logger->debug(sprintf(
                'FulfillCommand: Order is in final/protected state %s. Skipping update.',
                $order->getState()
            ));
        }

        $this->orderRepository->save($order);

        $this->logger->info('FulfillCommand: Completed.', [
            'orderId' => $order->getIncrementId(),
            'state' => $order->getState(),
        ]);

        return $order;
    }
}
