<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\CoreWebhook\Transaction;

use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Webhook\WebhookContext;
use Wallee\PluginCore\Webhook\Command\WebhookCommandInterface;
use Wallee\PluginCore\Webhook\Listener\WebhookListenerInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Magento\Sales\Model\OrderFactory;

class AuthorizedListener implements WebhookListenerInterface
{

    /**
     *
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param OrderResourceModel $orderResourceModel
     * @param OrderFactory $orderFactory
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly TransactionInfoRepositoryInterface $transactionInfoRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly OrderResourceModel $orderResourceModel,
        private readonly OrderFactory $orderFactory,
    ) {
    }

    /**
     * Create webhook command for the given context.
     *
     * @param WebhookContext $context
     * @return WebhookCommandInterface
     */
    public function getCommand(WebhookContext $context): WebhookCommandInterface
    {
        return new AuthorizedCommand(
            $context,
            $this->logger,
            $this->orderRepository,
            $this->transactionInfoRepository,
            $this->searchCriteriaBuilder,
            $this->orderResourceModel,
            $this->orderFactory,
        );
    }
}
