<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\Webhook\Refund;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\OrderRepositoryInterface;
use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Refund\RefundGatewayInterface;
use Wallee\PluginCore\Webhook\Command\WebhookCommand;
use Wallee\PluginCore\Webhook\WebhookContext;

class FailedCommand extends WebhookCommand
{
    use RefundCommandTrait;

    /**
     *
     * @param WebhookContext $context
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param RefundGatewayInterface $pluginCoreRefundGateway
     */
    public function __construct(
        WebhookContext $context,
        LoggerInterface $logger,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly TransactionInfoRepositoryInterface $transactionInfoRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly RefundGatewayInterface $pluginCoreRefundGateway,
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

        $refund = $this->loadRefund();
        if (!$refund) {
            $this->logger->warning(
                sprintf(
                    'FailedCommand: No refund found for entity ID: %d',
                    $this->context->entityId
                )
            );
            return null;
        }

        $order = $this->findOrderFromRefund($refund);
        if (!$order) {
            $this->logger->warning(
                sprintf(
                    'FailedCommand: No order found for entity ID: %d',
                    $this->context->entityId
                )
            );
            return null;
        }

        $failureReason = $refund->failureReason;
        if ($failureReason) {
            $order->addCommentToStatusHistory(
                \__(
                    'The refund of %1 failed on the gateway: %2',
                    $order->getBaseCurrency()->formatTxt($refund->amount),
                    $failureReason->getDefault()
                )->render()
            );
            $this->orderRepository->save($order);
        }

        $this->logger->info('FailedCommand: Completed.', [
            'orderId' => $order->getIncrementId(),
            'refundAmount' => $refund->amount,
        ]);
        // Return the objects needed by the postProcess hook
        return ['refund' => $refund, 'order' => $order];
    }
}
