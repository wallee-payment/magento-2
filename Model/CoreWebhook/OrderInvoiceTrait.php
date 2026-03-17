<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\CoreWebhook;

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
                return $invoice;
            }
        }

        // 2. If nothing found, check if a new invoice was created by the payment capture
        $payment = $order->getPayment();
        if ($payment) {
            $createdInvoice = $payment->getCreatedInvoice();
            if ($createdInvoice instanceof InvoiceInterface) {
                $order->addRelatedObject($createdInvoice);
                return $createdInvoice;
            }
        }

        // 3. As a final fallback, check related objects in the order
        foreach ($order->getRelatedObjects() as $object) {
            if ($object instanceof InvoiceInterface) {
                return $object;
            }
        }

        // 4. No invoice found
        return null;
    }
}
