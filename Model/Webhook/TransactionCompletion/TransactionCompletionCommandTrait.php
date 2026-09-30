<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\Webhook\TransactionCompletion;

use Magento\Sales\Model\Order;
use Wallee\PluginCore\Transaction\Completion\Exception\CompletionException;
use Wallee\PluginCore\Transaction\Completion\TransactionCompletion;
use Wallee\PluginCore\Webhook\Exception\RetryableWebhookException;
use Wallee\Payment\Model\Webhook\BaseOrderLookupTrait;

/**
 * A trait for reusable logic within transaction-completion related webhook commands.
 */
trait TransactionCompletionCommandTrait
{
    use BaseOrderLookupTrait;

    /**
     * Load the transaction completion domain entity via plugin-core.
     *
     * A retryable failure must not be swallowed into a null here — doing so would
     * make the caller ack the webhook as "nothing to do" and the portal would
     * never retry it. A non-retryable failure returns null, same as the gateway
     * confirming the entity does not exist.
     *
     * @return TransactionCompletion|null
     * @throws RetryableWebhookException If the failure is retryable.
     */
    protected function loadTransactionCompletion(): ?TransactionCompletion
    {
        try {
            return $this->completionGateway->find($this->context->spaceId, $this->context->entityId);
        } catch (CompletionException $e) {
            if ($e->isRetryable()) {
                throw new RetryableWebhookException(
                    "Could not load TransactionCompletion {$this->context->entityId}: " . $e->getMessage(),
                    null,
                    $e
                );
            }

            $this->logger->error(
                "Could not load TransactionCompletion {$this->context->entityId}: " . $e->getMessage(),
                ['exception' => $e]
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
