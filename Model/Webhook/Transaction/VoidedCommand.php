<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\Webhook\Transaction;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Wallee\Payment\Model\Webhook\OrderInvoiceTrait;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Webhook\WebhookContext;
use Wallee\PluginCore\Webhook\Command\WebhookCommand;
use Wallee\PluginCore\Webhook\Enum\LifecycleAction;
use Wallee\PluginCore\Webhook\TransactionActionResolver;
use Wallee\PluginCore\Transaction\State as CoreTransactionState;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Magento\Sales\Model\OrderFactory;

class VoidedCommand extends WebhookCommand
{
    use TransactionCommandTrait;
    use OrderInvoiceTrait;

    /**
     *
     * @param WebhookContext $context
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param OrderResourceModel $orderResourceModel
     * @param OrderFactory $orderFactory
     * @param TransactionActionResolver $transactionActionResolver
     */
    public function __construct(
        WebhookContext $context,
        LoggerInterface $logger,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly TransactionInfoRepositoryInterface $transactionInfoRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly OrderResourceModel $orderResourceModel,
        private readonly OrderFactory $orderFactory,
        private readonly TransactionActionResolver $transactionActionResolver
    ) {
        parent::__construct($context, $logger);
    }

    /**
     * Execute capture command for the current webhook context.
     *
     * @return mixed
     */
    public function execute(): mixed
    {
        $this->logger->info(sprintf('Running VoidedCommand for entity ID: %d', $this->context->entityId));

        $order = $this->findOrder();
        if (!$order) {
            $this->logger->warning(
                sprintf(
                    'VoidedCommand: No order found for entity ID: %d',
                    $this->context->entityId
                )
            );
            return null;
        }

        // Load fresh state from DB (Bypassing cache)
        $freshOrder = $this->orderFactory->create();
        $this->orderResourceModel->load($freshOrder, $order->getId());

        // Update Payment (Always record the notification)
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = $order->getPayment();
        $payment->registerVoidNotification();
        $this->logger->info('VoidedCommand: Registered void notification on payment.', [
            'orderId' => $order->getIncrementId(),
        ]);

        // Cancel Invoice (if exists)
        $transactionInfo = $this->findTransactionInfo();
        if ($transactionInfo) {
            $spaceId = (int) $transactionInfo->getSpaceId();
            $invoice = $this->getInvoiceForTransaction(
                $this->context->entityId,
                $spaceId,
                $order
            );
            if ($invoice) {
                $order->setWalleeInvoiceAllowManipulation(true); // TODO: Confirm flag name
                $invoice->cancel();
                $order->addRelatedObject($invoice);
                $this->logger->info('VoidedCommand: Canceled invoice.', [
                    'orderId' => $order->getIncrementId(),
                    'invoiceId' => $invoice->getIncrementId(),
                ]);
            }
        }

        // Safe State Update Logic
        $remoteState = CoreTransactionState::tryFrom($this->context->remoteState);
        $action = $remoteState !== null ? $this->transactionActionResolver->resolve($remoteState) : null;

        if ($action === LifecycleAction::CANCEL_ORDER) {
            if ($order->canCancel()) {
                $this->logger->info(sprintf('VoidedCommand: Canceling order %s.', $order->getIncrementId()));

                // Standardized cancellation: use Magento's own cancellation flow
                // (stock restoration, item state, order_cancel_after event) instead
                // of manually setting state/status.
                $order->registerCancellation(null, false);
            } else {
                $this->logger->debug(sprintf(
                    'VoidedCommand: Skipping cancellation. Order %s cannot be canceled (State: %s).',
                    $order->getIncrementId(),
                    $order->getState()
                ));
            }
        }

        $this->orderRepository->save($order);

        $this->logger->info('VoidedCommand: Completed.', [
            'orderId' => $order->getIncrementId(),
            'state' => $order->getState(),
        ]);
        return $order;
    }
}
