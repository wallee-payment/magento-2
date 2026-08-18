<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\Webhook\Refund;

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
use Wallee\Payment\Model\Service\Order\TransactionService;
use Wallee\Payment\Model\Webhook\OrderInvoiceTrait;

// PluginCore Dependencies
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\LineItem\LineItem as CoreLineItem;
use Wallee\PluginCore\Refund\Refund as CoreRefund;
use Wallee\PluginCore\Refund\RefundGatewayInterface;
use Wallee\PluginCore\Webhook\Command\WebhookCommand;
use Wallee\PluginCore\Webhook\WebhookContext;

// SDK Dependencies
use Wallee\PluginCore\Transaction\Invoice\State as CoreTransactionInvoiceState;

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
     * @param TransactionService $transactionService
     * @param Helper $helper
     * @param OrderRepositoryInterface $orderRepository
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param RefundJobRepositoryInterface $refundJobRepository
     * @param RefundGatewayInterface $pluginCoreRefundGateway
     */
    public function __construct(
        WebhookContext $context,
        LoggerInterface $logger,
        private readonly CreditmemoRepositoryInterface $creditmemoRepository,
        private readonly CreditmemoFactory $creditmemoFactory,
        private readonly CreditmemoManagementInterface $creditmemoManagement,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly StockConfigurationInterface $stockConfiguration,
        private readonly TransactionService $transactionService,
        private readonly Helper $helper,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly TransactionInfoRepositoryInterface $transactionInfoRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly RefundJobRepositoryInterface $refundJobRepository,
        private readonly RefundGatewayInterface $pluginCoreRefundGateway
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
            $invoice = $this->getInvoiceForTransaction(
                $refund->transactionId,
                $this->context->spaceId,
                $order
            );
            if (!($invoice instanceof InvoiceInterface) || $invoice->getState() == Invoice::STATE_OPEN) {
                // TODO: Review if this custom flag logic is still needed
                // if (! ($invoice instanceof InvoiceInterface)) {
                //     $order->setWalleeInvoiceAllowManipulation(true);
                // }

                if (!($invoice instanceof InvoiceInterface) || $invoice->getState() == Invoice::STATE_OPEN) {
                    $payment = $order->getPayment();
                    $payment->registerCaptureNotification($refund->amount);
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
            $refund->externalId,
            'wallee_external_id'
        );
        $this->logger->debug(
            sprintf(
                'Looked up existing creditmemo for external ID "%s": %s',
                $refund->externalId,
                $creditmemo->getId() ? "found (creditmemo #{$creditmemo->getId()})" : 'not found'
            )
        );
        if (!$creditmemo->getId()) {
            $creditmemo = $this->registerRefund($refund, $order);
        }

        $this->logger->info('SuccessfulCommand: Completed.', [
            'orderId' => $order->getIncrementId(),
            'creditmemoId' => $creditmemo->getIncrementId(),
            'refundAmount' => $refund->amount,
        ]);
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
        return $transactionInvoice->state === CoreTransactionInvoiceState::DERECOGNIZED;
    }

    /**
     * Create and refund a credit memo for the given refund.
     *
     * @param CoreRefund $refund
     * @param Order $order
     * @return \Magento\Sales\Model\Order\Creditmemo
     */
    private function registerRefund(CoreRefund $refund, Order $order)
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
        // This credit memo mirrors a refund the portal already processed — it must
        // never trigger a second, live refund request against the gateway.
        $creditmemo->setPaymentRefundDisallowed(true);
        $creditmemo->setAutomaticallyCreated(true);
        $creditmemo->addComment(\__('The credit memo has been created automatically.')->render());
        $creditmemo->setData('wallee_external_id', $refund->externalId);

        foreach ($creditmemo->getAllItems() as $creditmemoItem) {
            $creditmemoItem->setBackToStock($this->stockConfiguration->isAutoReturnEnabled());
        }

        $this->creditmemoManagement->refund($creditmemo, true);

        return $creditmemo;
    }

    /**
     * Build credit memo data from refund reductions.
     *
     * @param CoreRefund $refund
     * @param Order $order
     * @return array
     */
    private function collectCreditmemoData(CoreRefund $refund, Order $order): array
    {
        $orderItemMap = [];
        foreach ($order->getAllItems() as $orderItem) {
            $orderItemMap[$orderItem->getQuoteItemId()] = $orderItem;
        }

        $refundQuantities = [];
        foreach ($order->getAllItems() as $orderItem) {
            $refundQuantities[$orderItem->getQuoteItemId()] = 0;
        }

        $creditmemoAmount = 0;
        $shippingAmount = 0;
        foreach ($refund->lineItems ?? [] as $item) {
            switch ($item->type) {
                case CoreLineItem::TYPE_PRODUCT:
                    if ($item->quantity > 0) {
                        // WhitelabelMachineName reports the resulting line items of a refund tagged by
                        // reduction type (e.g. "36__qty"), not the plain uniqueId we sent
                        // when creating the transaction (e.g. "36"). Strip the tag to
                        // recover the original quote item id.
                        $baseUniqueId = strstr((string)$item->uniqueId, '__', true) ?: $item->uniqueId;
                        $orderItem = $orderItemMap[$baseUniqueId] ?? null;
                        if ($orderItem === null) {
                            $this->logger->warning(
                                sprintf(
                                    'SuccessfulCommand: No matching order item found for refund line item uniqueId "%s".',
                                    $item->uniqueId
                                )
                            );
                            break;
                        }
                        $refundQuantities[$orderItem->getId()] = $item->quantity;
                        $creditmemoAmount += $item->amountIncludingTax;
                    }
                    break;
                case CoreLineItem::TYPE_FEE:
                case CoreLineItem::TYPE_DISCOUNT:
                    break;
                case CoreLineItem::TYPE_SHIPPING:
                    $shippingAmount = $item->amountIncludingTax;

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
            $order->getOrderCurrencyCode()
        );

        $positiveAdjustment = 0;
        $negativeAdjustment = 0;
        if ($roundedCreditmemoAmount > $refund->amount) {
            $negativeAdjustment = $roundedCreditmemoAmount - $refund->amount;
        } elseif ($roundedCreditmemoAmount < $refund->amount) {
            $positiveAdjustment = $refund->amount - $roundedCreditmemoAmount;
        }

        return [
            'qtys' => $refundQuantities,
            'shipping_amount' => $shippingAmount,
            'adjustment_positive' => $positiveAdjustment,
            'adjustment_negative' => $negativeAdjustment
        ];
    }
}
