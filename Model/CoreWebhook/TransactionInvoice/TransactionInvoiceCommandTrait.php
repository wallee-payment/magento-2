<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\CoreWebhook\TransactionInvoice;

use Magento\Sales\Model\Order;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\Sdk\Model\TransactionInvoice;
use Wallee\Sdk\Service\TransactionInvoiceService;
use Wallee\Payment\Model\CoreWebhook\BaseOrderLookupTrait; // 1. Use the base trait

/**
 * A trait for reusable logic within transaction-invoice related webhook commands.
 */
trait TransactionInvoiceCommandTrait
{
    use BaseOrderLookupTrait; // 2. Add the base trait

    /**
     * Load transaction invoice from the SDK.
     *
     * @return TransactionInvoice|null
     */
    protected function loadTransactionInvoice(): ?TransactionInvoice
    {
        /** @var SdkProvider $sdkProvider */
        $sdkProvider = $this->sdkProvider;
        /** @var TransactionInvoiceService $invoiceService */
        $invoiceService = $sdkProvider->getService(TransactionInvoiceService::class);

        try {
            $spaceId = $sdkProvider->getSpaceId();
            $invoiceId = $this->context->entityId;
            return $invoiceService->read($spaceId, $invoiceId);
        } catch (\Exception $e) {
            $this->logger->error(
                "Could not load SDK TransactionInvoice {$this->context->entityId}: " . $e->getMessage()
            );
            return null;
        }
    }

    /**
     * Load transaction invoice from the SDK.
     *
     * @param TransactionInvoice $invoice
     * @return Order|null
     */
    protected function findOrderFromInvoice(TransactionInvoice $invoice): ?Order
    {
        $transactionId = $invoice->getLinkedTransaction();
        if (!$transactionId) {
            $this->logger->warning(
                "Could not get parent Transaction ID from TransactionInvoice {$invoice->getId()}"
            );
            return null;
        }

        // 3. Use the helper method from the base trait
        return $this->findOrderByTransactionId($transactionId);
    }

    // 4. The duplicated findTransactionInfoByTransactionId() method is REMOVED
}
