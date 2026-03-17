<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\CoreWebhook\Transaction;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Wallee\Payment\Model\CoreWebhook\OrderInvoiceTrait;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Webhook\Command\WebhookCommand;
use Wallee\PluginCore\Webhook\WebhookContext;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Magento\Sales\Model\OrderFactory;

class FailedCommand extends WebhookCommand
{
    use OrderInvoiceTrait;
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
        private readonly OrderFactory $orderFactory
    ) {
        parent::__construct($context, $logger);
    }

    /**
     * Execute failed command for the current webhook context.
     *
     * @return mixed
     */
    public function execute(): mixed
    {
        $this->logger->info(sprintf('Running FailedCommand for entity ID: %d', $this->context->entityId));

        $order = $this->findOrder();
        if (!$order) {
            $this->logger->warning(
                sprintf(
                    'FailedCommand: No order found for entity ID: %d',
                    $this->context->entityId
                )
            );
            return null;
        }

        // Load fresh state to check for PROTECTED states only
        $freshOrder = $this->orderFactory->create();
        $this->orderResourceModel->load($freshOrder, $order->getId());

        // If the order was shipped or closed by another process, STOP.
        if (
            $freshOrder->getState() === Order::STATE_COMPLETE || $freshOrder->getState() === Order::STATE_CLOSED
        ) {
            $this->logger->debug("FailedCommand: Skipping. Order is already Complete/Closed.");
            return null;
        }

        $transactionInfo = $this->findTransactionInfo();
        if (!$transactionInfo) {
            return null;
        }

        $spaceId = (int) $transactionInfo->getSpaceId();
        $sdkTransactionId = $this->context->entityId;

        // Cancel Invoice (Modifies $order in memory)
        $invoice = $this->getInvoiceForTransaction($sdkTransactionId, $spaceId, $order);

        if ($invoice && $invoice->canCancel()) {
            $order->setWalleeInvoiceAllowManipulation(true);
            $invoice->cancel();
            $order->addRelatedObject($invoice);
            $this->logger->info("FailedCommand: Canceled invoice {$invoice->getIncrementId()}.");
        }

        // Cancel Order
        if ($order->canCancel()) {
            $this->logger->info(sprintf('FailedCommand: Canceling order %s.', $order->getIncrementId()));
            $order->registerCancellation(null, false);
        } else {
            $this->logger->debug(sprintf(
                'FailedCommand: Skipping cancellation. Order %s state is %s.',
                $order->getIncrementId(),
                $order->getState()
            ));
        }

        $this->orderRepository->save($order);

        return $order;
    }
}
