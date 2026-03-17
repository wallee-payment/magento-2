<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\CoreWebhook\Refund;

// Magento Dependencies
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Invoice;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;

// Wallee Plugin Dependencies
use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Wallee\Payment\Api\RefundJobRepositoryInterface;
use Wallee\Payment\Helper\Data as Helper;
use Wallee\Payment\Model\Service\LineItemReductionService;
use Wallee\Payment\Model\Service\Order\TransactionService;
use Wallee\Payment\Model\CoreWebhook\OrderInvoiceTrait;

// PluginCore Dependencies
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Webhook\Command\WebhookCommand;
use Wallee\PluginCore\Webhook\WebhookContext;
use Wallee\PluginCore\Sdk\SdkProvider;

// SDK Dependencies
use Wallee\Sdk\Model\LineItemType;
use Wallee\Sdk\Model\Refund;
use Wallee\Sdk\Model\TransactionInvoiceState;

class SuccessfulCommand extends WebhookCommand
{
    use RefundCommandTrait;
    use OrderInvoiceTrait;

    /**
     *
     * @param WebhookContext $context
     * @param LoggerInterface $logger
     * @param CreditmemoRepositoryInterface $creditmemoRepository
     * @param CreditmemoFactory $creditmemoFactory
     * @param CreditmemoManagementInterface $creditmemoManagement
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param StockConfigurationInterface $stockConfiguration
     * @param LineItemReductionService $lineItemReductionService
     * @param TransactionService $transactionService
     * @param Helper $helper
     * @param OrderRepositoryInterface $orderRepository
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SdkProvider $sdkProvider
     * @param RefundJobRepositoryInterface $refundJobRepository
     */
    public function __construct(
        WebhookContext $context,
        LoggerInterface $logger,
        private readonly CreditmemoRepositoryInterface $creditmemoRepository,
        private readonly CreditmemoFactory $creditmemoFactory,
        private readonly CreditmemoManagementInterface $creditmemoManagement,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly StockConfigurationInterface $stockConfiguration,
        private readonly LineItemReductionService $lineItemReductionService,
        private readonly TransactionService $transactionService,
        private readonly Helper $helper,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly TransactionInfoRepositoryInterface $transactionInfoRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SdkProvider $sdkProvider,
        private readonly RefundJobRepositoryInterface $refundJobRepository
    ) {
        parent::__construct($context, $logger);
    }

    /**
     * Executes the logic to create a credit memo for a successful refund.
     *
     * @return mixed
     */
    public function execute(): mixed
    {
        $this->logger->info(
            sprintf(
                'Running SuccessfulCommand for entity ID: %d',
                $this->context->entityId
            )
        );

        $refund = $this->loadRefund();
        if (!$refund) {
            $this->logger->warning(
                sprintf(
                    'SuccessfulCommand: No refund found for entity ID: %d',
                    $this->context->entityId
                )
            );
            return null;
        }

        $order = $this->findOrderFromRefund($refund);
        if (!$order) {
            $this->logger->warning(
                sprintf(
                    'SuccessfulCommand: No order found for entity ID: %d',
                    $this->context->entityId
                )
            );
            return null;
        }

        // --- Ported Business Logic ---
        if ($this->isDerecognizedInvoice($order)) {
            $transaction = $refund->getTransaction();
            $invoice = $this->getInvoiceForTransaction(
                $transaction->getId(),
                $transaction->getLinkedSpaceId(),
                $order
            );
            if (!($invoice instanceof InvoiceInterface) || $invoice->getState() == Invoice::STATE_OPEN) {
                // TODO: Review if this custom flag logic is still needed
                // if (! ($invoice instanceof InvoiceInterface)) {
                //     $order->setWalleeInvoiceAllowManipulation(true);
                // }

                if (!($invoice instanceof InvoiceInterface) || $invoice->getState() == Invoice::STATE_OPEN) {
                    $payment = $order->getPayment();
                    $payment->registerCaptureNotification($refund->getAmount());
                    if (! ($invoice instanceof InvoiceInterface)) {
                        $invoice = $payment->getCreatedInvoice();
                        $order->addRelatedObject($invoice);
                    }
                }
                $this->orderRepository->save($order);
            }
        }

        /** @var \Magento\Sales\Model\Order\Creditmemo $creditmemo */
        $creditmemo = $this->creditmemoRepository->create()->load(
            $refund->getExternalId(),
            'wallee_external_id'
        );
        if (!$creditmemo->getId()) {
            $this->registerRefund($refund, $order);
        }

        $this->logger->debug(sprintf('Command Successful for entity Refund/%d completed.', $this->context->entityId));
        // Return the objects needed by the postProcess hook
        return ['refund' => $refund, 'order' => $order];
    }

    /**
     * Check whether the transaction invoice is derecognized.
     *
     * @param Order $order
     * @return bool
     */
    private function isDerecognizedInvoice(Order $order): bool
    {
        $transactionInvoice = $this->transactionService->getTransactionInvoice($order);
        return $transactionInvoice->getState() == TransactionInvoiceState::DERECOGNIZED;
    }

    /**
     * Create and refund a credit memo for the given refund.
     *
     * @param Refund $refund
     * @param Order $order
     * @return void
     */
    private function registerRefund(Refund $refund, Order $order): void
    {
        $creditmemoData = $this->collectCreditmemoData($refund, $order);
        try {
            $refundJob = $this->refundJobRepository->getByOrderId($order->getId());
            $invoice = $this->invoiceRepository->get($refundJob->getInvoiceId());
            $creditmemo = $this->creditmemoFactory->createByInvoice($invoice, $creditmemoData);
        } catch (NoSuchEntityException $e) {
            $paidInvoices = $order->getInvoiceCollection()->addFieldToFilter('state', Invoice::STATE_PAID);
            if ($paidInvoices->count() == 1) {
                $creditmemo = $this->creditmemoFactory->createByInvoice($paidInvoices->getFirstItem(), $creditmemoData);
            } else {
                $creditmemo = $this->creditmemoFactory->createByOrder($order, $creditmemoData);
            }
        }
        $creditmemo->setPaymentRefundDisallowed(false);
        $creditmemo->setAutomaticallyCreated(true);
        $creditmemo->addComment(\__('The credit memo has been created automatically.')->render());
        $creditmemo->setData('wallee_external_id', $refund->getExternalId());

        foreach ($creditmemo->getAllItems() as $creditmemoItem) {
            $creditmemoItem->setBackToStock($this->stockConfiguration->isAutoReturnEnabled());
        }

        $this->creditmemoManagement->refund($creditmemo);
    }

    /**
     * Build credit memo data from refund reductions.
     *
     * @param Refund $refund
     * @param Order $order
     * @return array
     */
    private function collectCreditmemoData(Refund $refund, Order $order): array
    {
        $orderItemMap = [];
        foreach ($order->getAllItems() as $orderItem) {
            $orderItemMap[$orderItem->getQuoteItemId()] = $orderItem;
        }

        $lineItems = [];
        foreach ($refund->getTransaction()->getLineItems() as $lineItem) {
            $lineItems[$lineItem->getUniqueId()] = $lineItem;
        }

        $baseLineItems = [];
        foreach (
            $this->lineItemReductionService->getBaseLineItems(
                $order->getWalleeSpaceId(),
                $refund->getTransaction()->getId(),
                $refund
            ) as $lineItem
        ) {
            $baseLineItems[$lineItem->getUniqueId()] = $lineItem;
        }

        $refundQuantities = [];
        foreach ($order->getAllItems() as $orderItem) {
            $refundQuantities[$orderItem->getQuoteItemId()] = 0;
        }

        $creditmemoAmount = 0;
        $shippingAmount = 0;
        foreach ($refund->getReductions() as $reduction) {
            $lineItem = $lineItems[$reduction->getLineItemUniqueId()];
            switch ($lineItem->getType()) {
                case LineItemType::PRODUCT:
                    if ($reduction->getQuantityReduction() > 0) {
                        $orderItem = $orderItemMap[$reduction->getLineItemUniqueId()];
                        $refundQuantities[$orderItem->getId()] = $reduction->getQuantityReduction();
                        $creditmemoAmount += $reduction->getQuantityReduction() *
                            ($orderItem->getRowTotal() + $orderItem->getTaxAmount() - $orderItem->getDiscountAmount() +
                                $orderItem->getDiscountTaxCompensationAmount()) / $orderItem->getQtyOrdered();
                    }
                    break;
                case LineItemType::FEE:
                case LineItemType::DISCOUNT:
                    break;
                case LineItemType::SHIPPING:
                    if ($reduction->getQuantityReduction() > 0) {
                        $shippingAmount = $baseLineItems[$reduction->getLineItemUniqueId()]->getAmountIncludingTax();
                    } elseif ($reduction->getUnitPriceReduction() > 0) {
                        $shippingAmount = $reduction->getUnitPriceReduction();
                    } else {
                        $shippingAmount = 0;
                    }

                    if ($shippingAmount == $order->getShippingInclTax()) {
                        $creditmemoAmount += $shippingAmount;
                    } elseif ($shippingAmount <= $order->getShippingInclTax() - $order->getShippingRefunded()) {
                        $creditmemoAmount += $shippingAmount;
                    } else {
                        $shippingAmount = 0;
                    }

                    if ($order->getShippingDiscountAmount() > 0 && $order->getShippingAmount() > 0) {
                        $shippingAmount += ($shippingAmount / $order->getShippingAmount()) *
                            $order->getShippingDiscountAmount();
                    }
                    break;
            }
        }

        $roundedCreditmemoAmount = $this->helper->roundAmount(
            $creditmemoAmount,
            $refund->getTransaction()->getCurrency()
        );

        $positiveAdjustment = 0;
        $negativeAdjustment = 0;
        if ($roundedCreditmemoAmount > $refund->getAmount()) {
            $negativeAdjustment = $roundedCreditmemoAmount - $refund->getAmount();
        } elseif ($roundedCreditmemoAmount < $refund->getAmount()) {
            $positiveAdjustment = $refund->getAmount() - $roundedCreditmemoAmount;
        }

        return [
            'qtys' => $refundQuantities,
            'shipping_amount' => $shippingAmount,
            'adjustment_positive' => $positiveAdjustment,
            'adjustment_negative' => $negativeAdjustment
        ];
    }
}
