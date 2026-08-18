<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\Webhook\TransactionInvoice;

use Magento\Sales\Model\Order;
use Wallee\PluginCore\Transaction\Invoice\Invoice;
use Wallee\PluginCore\Transaction\Invoice\InvoiceGatewayInterface;
use Wallee\Payment\Model\Webhook\BaseOrderLookupTrait;

/**
 * A trait for reusable logic within transaction-invoice related webhook commands.
 */
trait TransactionInvoiceCommandTrait
{
    use BaseOrderLookupTrait;

    /**
     * Load the transaction invoice domain entity via plugin-core.
     *
     * @return Invoice|null
     */
    protected function loadTransactionInvoice(): ?Invoice
    {
        try {
            return $this->invoiceGateway->find($this->context->spaceId, $this->context->entityId);
        } catch (\Exception $e) {
            $this->logger->error(
                "Could not load TransactionInvoice {$this->context->entityId}: " . $e->getMessage()
            );
            return null;
        }
    }

    /**
     * Find order linked to the given transaction invoice.
     *
     * @param Invoice $invoice
     * @return Order|null
     */
    protected function findOrderFromInvoice(Invoice $invoice): ?Order
    {
        if (!$invoice->linkedTransactionId) {
            $this->logger->warning(
                "Could not get parent Transaction ID from TransactionInvoice {$invoice->id}"
            );
            return null;
        }

        return $this->findOrderByTransactionId($invoice->linkedTransactionId);
    }
}
