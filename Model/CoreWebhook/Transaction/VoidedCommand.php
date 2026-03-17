<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\CoreWebhook\Transaction;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Wallee\Payment\Model\CoreWebhook\OrderInvoiceTrait;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Webhook\WebhookContext;
use Wallee\PluginCore\Webhook\Command\WebhookCommand;
use Wallee\Sdk\Model\TransactionState;
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
     */
    public function __construct(
        WebhookContext $context,
        LoggerInterface $logger,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly TransactionInfoRepositoryInterface $transactionInfoRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly OrderResourceModel $orderResourceModel,
        private readonly OrderFactory $orderFactory
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
            }
        }

        // Safe State Update Logic
        if ($this->context->remoteState == TransactionState::VOIDED) {
            if ($order->canCancel()) {
                $this->logger->info(sprintf('VoidedCommand: Canceling order %s.', $order->getIncrementId()));

                $order->setState(Order::STATE_CANCELED);
                $order->setStatus(Order::STATE_CANCELED);

                $order->addStatusToHistory(
                    Order::STATE_CANCELED,
                    __('The order has been canceled.')->render(),
                    false
                );
            } else {
                $this->logger->debug(sprintf(
                    'VoidedCommand: Skipping cancellation. Order %s cannot be canceled (State: %s).',
                    $order->getIncrementId(),
                    $order->getState()
                ));
            }
        }

        $this->orderRepository->save($order);

        $this->logger->debug(
            sprintf(
                'Command Voided for entity Transaction/%d completed.',
                $this->context->entityId
            )
        );
        return $order;
    }
}
