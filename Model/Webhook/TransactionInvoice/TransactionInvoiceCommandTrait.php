<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\Webhook\TransactionInvoice;

use Magento\Sales\Model\Order;
use Wallee\PluginCore\Transaction\Invoice\Exception\InvoiceException;
use Wallee\PluginCore\Transaction\Invoice\Invoice;
use Wallee\PluginCore\Transaction\Invoice\InvoiceGatewayInterface;
use Wallee\PluginCore\Webhook\Exception\RetryableWebhookException;
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
     * A retryable failure must not be swallowed into a null here — doing so would
     * make the caller ack the webhook as "nothing to do" and the portal would
     * never retry it. A non-retryable failure returns null, same as the gateway
     * confirming the entity does not exist.
     *
     * @return Invoice|null
     * @throws RetryableWebhookException If the failure is retryable.
     */
    protected function loadTransactionInvoice(): ?Invoice
    {
        try {
            return $this->invoiceGateway->find($this->context->spaceId, $this->context->entityId);
        } catch (InvoiceException $e) {
            if ($e->isRetryable()) {
                throw new RetryableWebhookException(
                    "Could not load TransactionInvoice {$this->context->entityId}: " . $e->getMessage(),
                    null,
                    $e
                );
            }

            $this->logger->error(
                "Could not load TransactionInvoice {$this->context->entityId}: " . $e->getMessage(),
                ['exception' => $e]
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
