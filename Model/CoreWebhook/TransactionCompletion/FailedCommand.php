<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\CoreWebhook\TransactionCompletion;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Wallee\Payment\Model\CoreWebhook\OrderInvoiceTrait;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Webhook\Command\WebhookCommand;
use Wallee\PluginCore\Webhook\WebhookContext;

class FailedCommand extends WebhookCommand
{
    use OrderInvoiceTrait;
    use TransactionCompletionCommandTrait;

    /**
     *
     * @param WebhookContext $context
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param OrderResourceModel $orderResourceModel
     * @param OrderFactory $orderFactory
     * @param SdkProvider $sdkProvider
     */
    public function __construct(
        WebhookContext $context,
        LoggerInterface $logger,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly TransactionInfoRepositoryInterface $transactionInfoRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly OrderResourceModel $orderResourceModel,
        private readonly OrderFactory $orderFactory,
        protected readonly SdkProvider $sdkProvider,
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
        $this->logger->info(
            sprintf(
                'Running FailedCommand for entity ID: %d',
                $this->context->entityId
            )
        );

        // Load Completion and Order
        $completion = $this->loadTransactionCompletion();
        if (!$completion) {
            return null;
        }

        $order = $this->findOrderFromCompletion($completion);
        if (!$order) {
            $this->logger->warning(
                sprintf(
                    'FailedCommand: No order found for entity ID: %d',
                    $this->context->entityId
                )
            );
            return null;
        }

        // Load fresh state from DB
        $freshOrder = $this->orderFactory->create();
        $this->orderResourceModel->load($freshOrder, $order->getId());

        // Guard against External Changes
        $protectedStates = [
            Order::STATE_COMPLETE,
            Order::STATE_CLOSED,
        ];

        if (in_array($freshOrder->getState(), $protectedStates, true)) {
            $this->logger->info(sprintf(
                'FailedCommand: Skipping cancellation. Order %s is in protected state %s.',
                $order->getIncrementId(),
                $freshOrder->getState()
            ));
            return $order;
        }

        // Get IDs directly from the Completion object (No need for findTransactionInfo)
        $spaceId = $completion->getLinkedSpaceId();
        $sdkTransactionId = $completion->getLineItemVersion()->getTransaction()->getId();

        // Cancel Invoice
        $invoice = $this->getInvoiceForTransaction($sdkTransactionId, $spaceId, $order);
        if ($invoice && $invoice->canCancel()) {
            $order->setWalleeInvoiceAllowManipulation(true);
            $invoice->cancel();
            $order->addRelatedObject($invoice);
            $this->logger->info("FailedCommand: Canceled invoice {$invoice->getIncrementId()}.");
        }

        // Safe Order Cancellation Logic
        if ($order->canCancel()) {
            $this->logger->info(sprintf('FailedCommand: Canceling order %s.', $order->getIncrementId()));
            $order->registerCancellation(null, false);
        } else {
            $this->logger->debug(sprintf(
                'FailedCommand: Order %s cannot be canceled (State: %s).',
                $order->getIncrementId(),
                $order->getState()
            ));
        }

        $this->orderRepository->save($order);
        $this->logger->debug(
            sprintf(
                'Command Failed for entity TransactionCompletion/%d completed.',
                $this->context->entityId
            )
        );

        return $order;
    }
}
