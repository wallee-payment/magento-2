<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\CoreWebhook\Refund;

use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Wallee\Payment\Api\RefundJobRepositoryInterface;
use Wallee\Payment\Helper\Data as Helper;
use Wallee\Payment\Model\Service\LineItemReductionService;
use Wallee\Payment\Model\Service\Order\TransactionService;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Webhook\Command\WebhookCommandInterface;
use Wallee\PluginCore\Webhook\Listener\WebhookListenerInterface;
use Wallee\PluginCore\Webhook\WebhookContext;

class SuccessfulListener implements WebhookListenerInterface
{

    /**
     *
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository
     * @param CreditmemoRepositoryInterface $creditmemoRepository
     * @param CreditmemoFactory $creditmemoFactory
     * @param CreditmemoManagementInterface $creditmemoManagement
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param StockConfigurationInterface $stockConfiguration
     * @param LineItemReductionService $lineItemReductionService
     * @param TransactionService $transactionService
     * @param Helper $helper
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SdkProvider $sdkProvider
     * @param RefundJobRepositoryInterface $refundJobRepository
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly CreditmemoRepositoryInterface $creditmemoRepository,
        private readonly CreditmemoFactory $creditmemoFactory,
        private readonly CreditmemoManagementInterface $creditmemoManagement,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly StockConfigurationInterface $stockConfiguration,
        private readonly LineItemReductionService $lineItemReductionService,
        private readonly TransactionService $transactionService,
        private readonly Helper $helper,
        private readonly TransactionInfoRepositoryInterface $transactionInfoRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SdkProvider $sdkProvider,
        private readonly RefundJobRepositoryInterface $refundJobRepository
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
        return new SuccessfulCommand(
            $context,
            $this->logger,
            $this->creditmemoRepository,
            $this->creditmemoFactory,
            $this->creditmemoManagement,
            $this->invoiceRepository,
            $this->stockConfiguration,
            $this->lineItemReductionService,
            $this->transactionService,
            $this->helper,
            $this->orderRepository,
            $this->transactionInfoRepository,
            $this->searchCriteriaBuilder,
            $this->sdkProvider,
            $this->refundJobRepository
        );
    }
}
