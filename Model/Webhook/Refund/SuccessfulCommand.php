<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\Webhook\Refund;

// Magento Dependencies
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
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
use Wallee\PluginCore\Refund\RefundService as CoreRefundService;
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
     * @param CoreRefundService $coreRefundService
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
        private readonly CoreRefundService $coreRefundService
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
        // Magento assembles the credit memo from quantities alone, so its total
        // still has to be brought back to the amount the portal actually refunded.
        $this->reconcileCreditmemoTotal($creditmemo, $refund, $order);

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
     * Every monetary value in the returned array is a **base**-currency amount:
     * Magento's CreditmemoFactory feeds `shipping_amount` straight into
     * setBaseShippingAmount(), while the portal reports refund amounts in the
     * transaction (= order) currency.
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

        // Keyed by *order* item id — the key CreditmemoFactory::getQtyToRefund()
        // looks up — so that an item left out of this refund is explicitly
        // credited with zero instead of being resolved through its parent.
        $refundQuantities = [];
        foreach ($order->getAllItems() as $orderItem) {
            $refundQuantities[$orderItem->getId()] = 0;
        }

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
                        // Never ask for more than Magento has left on the line. A
                        // reduction can lower the unit price without touching the
                        // quantity, and a line already credited in full can be
                        // refunded again; either way crediting the quantity a second
                        // time would push qty_refunded past qty_ordered. Whatever the
                        // capped quantity fails to cover in money is settled by
                        // reconcileCreditmemoTotal().
                        $quantity = min((float) $item->quantity, (float) $orderItem->getQtyToRefund());
                        if ($quantity <= 0) {
                            break;
                        }
                        $refundQuantities[$orderItem->getId()] = $quantity;
                    }
                    break;
                case CoreLineItem::TYPE_FEE:
                case CoreLineItem::TYPE_DISCOUNT:
                    break;
                case CoreLineItem::TYPE_SHIPPING:
                    $shippingAmount = $item->amountIncludingTax;

                    if ($shippingAmount != $order->getShippingInclTax()
                        && $shippingAmount > $order->getShippingInclTax() - $order->getShippingRefunded()
                    ) {
                        $shippingAmount = 0;
                    }

                    if ($shippingAmount > 0
                        && $order->getShippingDiscountAmount() > 0
                        && $order->getShippingAmount() > 0
                    ) {
                        $shippingAmount += ($shippingAmount / $order->getShippingAmount()) *
                            $order->getShippingDiscountAmount();
                    }
                    break;
            }
        }

        return [
            'qtys' => $refundQuantities,
            'shipping_amount' => $this->getBaseShippingAmount((float) $shippingAmount, $order)
        ];
    }

    /**
     * Aligns the credit memo's totals with the amount the portal refunded.
     *
     * Magento credits whole line items, so a refund that only lowered a unit price
     * — or one aimed at a line that is already fully credited — leaves a credit
     * memo whose total is not the refunded amount. The difference is booked as an
     * adjustment.
     *
     * @param Creditmemo $creditmemo
     * @param CoreRefund $refund
     * @param Order $order
     * @return void
     */
    private function reconcileCreditmemoTotal(Creditmemo $creditmemo, CoreRefund $refund, Order $order): void
    {
        $orderCurrency = (string) $order->getOrderCurrencyCode();
        $baseCurrency = (string) $order->getBaseCurrencyCode();

        $refundAmount = $this->helper->roundAmount((float) $refund->amount, $orderCurrency);
        $baseRefundAmount = $this->toBaseAmount($refundAmount, $order);

        $delta = $this->helper->roundAmount(
            (float) $creditmemo->getGrandTotal() - $refundAmount,
            $orderCurrency
        );
        $baseDelta = $this->helper->roundAmount(
            (float) $creditmemo->getBaseGrandTotal() - $baseRefundAmount,
            $baseCurrency
        );

        if ($delta == 0 && $baseDelta == 0) {
            return;
        }

        // Both columns are written directly instead of through
        // Creditmemo::setAdjustment*(), which derives the order-currency amount from
        // the base one and can leave the credit memo a cent off the amount the
        // portal actually refunded. Magento's Grand total collector reads the two
        // columns independently, so each one carries the difference measured in its
        // own currency and both totals land on the refunded amount.
        if ($delta > 0) {
            $creditmemo->setData(CreditmemoInterface::ADJUSTMENT_NEGATIVE, abs($delta));
            $creditmemo->setData(CreditmemoInterface::BASE_ADJUSTMENT_NEGATIVE, max($baseDelta, 0.0));
        } else {
            $creditmemo->setData(CreditmemoInterface::ADJUSTMENT_POSITIVE, abs($delta));
            $creditmemo->setData(CreditmemoInterface::BASE_ADJUSTMENT_POSITIVE, max(-$baseDelta, 0.0));
        }

        // Every credit memo total collector adds its part to the grand total, so the
        // grand total has to start from zero again or the amounts collected a moment
        // ago are counted twice.
        $creditmemo->setGrandTotal(0);
        $creditmemo->setBaseGrandTotal(0);
        $creditmemo->collectTotals();

        $this->logger->debug('Adjusted the credit memo to the refunded amount.', [
            'orderId' => $order->getIncrementId(),
            'refundAmount' => $refundAmount,
            'creditmemoAmountFromQuantities' => $this->helper->roundAmount(
                $refundAmount + $delta,
                $orderCurrency
            ),
            'adjustment' => $delta,
            'baseAdjustment' => $baseDelta
        ]);
    }

    /**
     * Converts a refunded shipping amount from the order to the base currency.
     *
     * Magento's Creditmemo\Total\Shipping validates the value it is given against
     * the order's *base* shipping amount, so an order-currency amount makes every
     * multi-currency shipping refund fail with "Maximum shipping amount allowed to
     * refund is …" — and the webhook retries that failure forever. A full refund
     * takes the order's own base amount instead of dividing by the rate, which can
     * land a cent off and trip the very same check.
     *
     * @param float $shippingAmount Amount in order currency.
     * @param Order $order
     * @return float
     */
    private function getBaseShippingAmount(float $shippingAmount, Order $order): float
    {
        if ($shippingAmount <= 0) {
            return 0.0;
        }

        if ($shippingAmount == (float) $order->getShippingInclTax()) {
            $baseShippingAmount = (float) $order->getBaseShippingInclTax();
        } else {
            $baseShippingAmount = $this->toBaseAmount($shippingAmount, $order);
        }

        $baseAllowed = (float) $order->getBaseShippingInclTax() - (float) $order->getBaseShippingRefunded();

        return min($baseShippingAmount, max($baseAllowed, 0.0));
    }

    /**
     * Converts an amount from the order currency to the base currency.
     *
     * @param float $amount
     * @param Order $order
     * @return float
     */
    private function toBaseAmount(float $amount, Order $order): float
    {
        $rate = (float) $order->getBaseToOrderRate();
        if ($rate <= 0 || $rate == 1.0) {
            return $amount;
        }

        return (float) $this->helper->roundAmount($amount / $rate, (string) $order->getBaseCurrencyCode());
    }
}
