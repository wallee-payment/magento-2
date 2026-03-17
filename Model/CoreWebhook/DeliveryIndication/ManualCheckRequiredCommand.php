<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\CoreWebhook\DeliveryIndication;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Webhook\WebhookContext;
use Wallee\PluginCore\Webhook\Command\WebhookCommand;
use Wallee\PluginCore\Sdk\SdkProvider;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;

class ManualCheckRequiredCommand extends WebhookCommand
{
    use DeliveryIndicationCommandTrait;

    /**
     *
     * @param WebhookContext $context
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SdkProvider $sdkProvider
     * @param OrderResourceModel $orderResourceModel
     */
    public function __construct(
        WebhookContext $context,
        LoggerInterface $logger,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly TransactionInfoRepositoryInterface $transactionInfoRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SdkProvider $sdkProvider,
        private readonly OrderResourceModel $orderResourceModel
    ) {
        parent::__construct($context, $logger);
    }

    /**
     * Execute manual check required command for the current webhook context.
     *
     * @return mixed
     */
    public function execute(): mixed
    {
        $this->logger->info("Running ManualCheckRequiredCommand for entity: " . $this->context->entityId);

        $indication = $this->loadDeliveryIndication();
        if (!$indication) {
            $this->logger->warning(
                "DeliveryIndication webhook {$this->context->entityId} could not be loaded from SDK."
            );
            return null;
        }

        $order = $this->findOrderFromIndication($indication);
        if (!$order) {
            $this->logger->warning("Could not find order for DeliveryIndication {$this->context->entityId}.");
            return null;
        }

        // Reload the order from the DB to ensure we aren't working with a stale object.
        $this->orderResourceModel->load($order, $order->getId());

        // Safe Update Logic
        if ($order->canHold()) {
            if ($order->getState() != Order::STATE_PAYMENT_REVIEW) {
                $this->logger->info(
                    sprintf(
                        'ManualCheckRequired: Setting order %s to Payment Review.',
                        $order->getIncrementId()
                    )
                );

                $order->setState(Order::STATE_PAYMENT_REVIEW);
                // Note: In Magento, setting the state usually sets the status to 'payment_review' automatically.
                $order->addStatusToHistory(
                    true,
                    \__('A manual decision about whether to accept the payment is required.')->render()
                );
            }
        } else {
            $this->logger->debug(sprintf(
                'ManualCheckRequired: Skipping update. Order %s is in protected state %s.',
                $order->getIncrementId(),
                $order->getState()
            ));
        }

        $this->orderRepository->save($order);

        $this->logger->debug(
            sprintf(
                'Command ManualCheckRequired for entity DeliveryIndication/%d completed.',
                $this->context->entityId
            )
        );

        // Return the order for the postProcess hook
        return $order;
    }
}
