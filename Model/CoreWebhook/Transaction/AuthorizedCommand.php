<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\CoreWebhook\Transaction;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Wallee\PluginCore\Webhook\Command\WebhookCommand;
use Wallee\PluginCore\Webhook\WebhookContext;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\Sdk\Model\TransactionState;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Magento\Sales\Model\OrderFactory;

class AuthorizedCommand extends WebhookCommand
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
     * @param OrderFactory $orderFactory
     */
    public function __construct(
        WebhookContext $context,
        LoggerInterface $logger,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly TransactionInfoRepositoryInterface $transactionInfoRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly OrderResourceModel $orderResourceModel,
        private readonly OrderFactory $orderFactory,
    ) {
        parent::__construct($context, $logger);
    }

    /**
     * Execute authorized command for the current webhook context.
     *
     * @return mixed
     */
    public function execute(): mixed
    {
        $this->logger->info(
            sprintf(
                'Running AuthorizedCommand for entity ID: %d',
                $this->context->entityId
            )
        );

        $order = $this->findOrder();
        if (!$order) {
            $this->logger->warning(
                sprintf(
                    'AuthorizedCommand: No order found for entity ID: %d',
                    $this->context->entityId
                )
            );
            return null;
        }

        // 1. Check the FRESH database state (bypassing cache)
        $freshOrder = $this->orderFactory->create();
        $this->orderResourceModel->load($freshOrder, $order->getId());

        $currentState = $freshOrder->getState();
        $currentStatus = $freshOrder->getStatus();

        // 2. Check if already authorized (Idempotency)
        if ($freshOrder->getData('wallee_authorized')) {
            $payment = $order->getPayment();
            $payment->setTransactionId($this->getTransactionIdForPayment());
            $this->orderRepository->save($order);

            $this->logger->debug(sprintf(
                'AuthorizedCommand: Skipping processing because order %s has already been processed.',
                $freshOrder->getIncrementId()
            ));
            return $order;
        }

        // 3. Register the Payment
        // This updates the Payment object and might implicitly change the $order state to PROCESSING
        $payment = $order->getPayment();
        $payment->setTransactionId($this->getTransactionIdForPayment());
        $payment->setIsTransactionClosed(false);
        $payment->registerAuthorizationNotification($payment->getAmountAuthorized());

        // 4. Apply Safe State Logic
        $protectedStates = [
            Order::STATE_PAYMENT_REVIEW,
            Order::STATE_COMPLETE,
            Order::STATE_CLOSED,
            Order::STATE_CANCELED,
            Order::STATE_HOLDED
        ];

        if (in_array($currentState, $protectedStates, true)) {
            // CASE A: Order is in a protected state.
            $this->logger->info(sprintf(
                "AuthorizedCommand: Order is in protected state '%s'. Preserving state.",
                $currentState
            ));

            // Force the local object back to the protected state from DB
            // (Undoing any implicit change made by registerAuthorizationNotification)
            $order->setState($currentState);
            $order->setStatus($currentStatus);

        } else {
            // CASE B: Safe to update.
            if ($this->context->remoteState != TransactionState::FULFILL) {
                $order->setState(Order::STATE_PROCESSING);
                $order->setStatus('pending'); // Status set to pending as per legacy logic

                $order->addStatusToHistory(
                    'pending',
                    __('The order should not be fulfilled yet, as the payment is not guaranteed.')->render(),
                    false
                );
            }
        }

        // We always set our internal flag, regardless of the order state
        $order->setData('wallee_authorized', true);

        $this->orderRepository->save($order);

        $this->logger->debug(
            sprintf(
                'Command Authorized for entity Transaction/%d completed.',
                $this->context->entityId
            )
        );

        return $order;
    }

    /**
     * Fetches transaction ID.
     *
     * @return string
     */
    private function getTransactionIdForPayment(): string
    {
        $transactionInfo = $this->findTransactionInfo();
        if ($transactionInfo) {
            return $transactionInfo->getSpaceId() . '_' . $this->context->entityId;
        }
        return (string)$this->context->entityId;
    }
}
