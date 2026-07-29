<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\CoreWebhook\TransactionCompletion;

use Magento\Sales\Model\Order;
use Wallee\PluginCore\Transaction\Completion\TransactionCompletion;
use Wallee\Payment\Model\CoreWebhook\BaseOrderLookupTrait;

/**
 * A trait for reusable logic within transaction-completion related webhook commands.
 */
trait TransactionCompletionCommandTrait
{
    use BaseOrderLookupTrait;

    /**
     * Load the transaction completion domain entity via plugin-core.
     *
     * @return TransactionCompletion|null
     */
    protected function loadTransactionCompletion(): ?TransactionCompletion
    {
        try {
            return $this->completionGateway->find($this->context->spaceId, $this->context->entityId);
        } catch (\Exception $e) {
            $this->logger->error(
                "Could not load TransactionCompletion {$this->context->entityId}: " . $e->getMessage()
            );
            return null;
        }
    }

    /**
     * Find order linked to the given transaction completion.
     *
     * @param TransactionCompletion $completion
     * @return Order|null
     */
    protected function findOrderFromCompletion(TransactionCompletion $completion): ?Order
    {
        if (!$completion->linkedTransactionId) {
            $this->logger->warning(
                "Could not get parent Transaction from TransactionCompletion {$completion->id}"
            );
            return null;
        }

        return $this->findOrderByTransactionId($completion->linkedTransactionId);
    }
}
