<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\CoreWebhook\Transaction;

use Magento\Sales\Model\Order as MagentoOrder;
use Wallee\Payment\Api\Data\TransactionInfoInterface;
use Wallee\Payment\Model\CoreWebhook\BaseOrderLookupTrait; // 1. Use the base trait

/**
 * A trait for reusable logic within transaction-related webhook commands.
 *
 * Assumes the class using this trait has the required properties
 * (context, repositories, searchBuilder) available.
 */
trait TransactionCommandTrait
{
    use BaseOrderLookupTrait; // 2. Add the base trait

    /**
     * Finds the TransactionInfo for the current webhook context.
     */
    protected function findTransactionInfo(): ?TransactionInfoInterface
    {
        // For Transaction webhooks, entityId IS the transactionId
        return $this->findTransactionInfoByTransactionId($this->context->entityId);
    }

    /**
     * Finds the Order for the current webhook context.
     */
    protected function findOrder(): ?MagentoOrder
    {
        // For Transaction webhooks, entityId IS the transactionId
        return $this->findOrderByTransactionId($this->context->entityId);
    }
}
