<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\Webhook;

use Magento\Sales\Model\Order;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Model\Order\Invoice; // Import the Invoice class

/**
 * A trait for reusable logic related to finding invoices for an order.
 */
trait OrderInvoiceTrait
{
    /**
     * Finds (or retrieves from the payment) the invoice associated with a Wallee transaction.
     *
     * @param int $sdkTransactionId
     * @param int $sdkSpaceId
     * @param Order $order
     * @return InvoiceInterface|null
     */
    // Renamed to match the legacy method name for compatibility with the ported commands
    protected function getInvoiceForTransaction(int $sdkTransactionId, int $sdkSpaceId, Order $order): ?InvoiceInterface
    {
        // 1. Check invoice collection for a matching transactionId
        foreach ($order->getInvoiceCollection() as $invoice) {
            if (\strpos((string) $invoice->getTransactionId(), $sdkSpaceId . '_' . $sdkTransactionId) === 0
                && $invoice->getState() != Invoice::STATE_CANCELED) {
                return $this->associateOrder($invoice, $order);
            }
        }

        // 2. If nothing found, check if a new invoice was created by the payment capture
        $payment = $order->getPayment();
        if ($payment) {
            $createdInvoice = $payment->getCreatedInvoice();
            if ($createdInvoice instanceof InvoiceInterface) {
                $order->addRelatedObject($createdInvoice);
                return $this->associateOrder($createdInvoice, $order);
            }
        }

        // 3. As a final fallback, check related objects in the order
        foreach ($order->getRelatedObjects() as $object) {
            if ($object instanceof InvoiceInterface) {
                return $this->associateOrder($object, $order);
            }
        }

        // 4. No invoice found
        return null;
    }

    /**
     * Ensures the invoice's internal order reference is the exact instance the caller holds.
     *
     * Invoice::getOrder() lazily loads a brand-new Order from the DB whenever its internal
     * $_order isn't already set — which it typically isn't for an invoice found via
     * $order->getInvoiceCollection() (collection loading doesn't associate items back to the
     * order instance that fetched them). Any later mutation that goes through getOrder()
     * internally — pay(), cancel(), etc. — would then silently update that orphaned, never-saved
     * object instead of $order, leaving the invoice's own state persisted but the order's totals
     * (total_paid, total_invoiced, ...) unchanged.
     *
     * @param InvoiceInterface $invoice
     * @param Order $order
     * @return InvoiceInterface
     */
    private function associateOrder(InvoiceInterface $invoice, Order $order): InvoiceInterface
    {
        $invoice->setOrder($order);
        return $invoice;
    }
}
