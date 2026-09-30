<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\Webhook\Refund;

use Magento\Sales\Model\Order;
use Wallee\PluginCore\Refund\Exception\RefundException;
use Wallee\PluginCore\Refund\Refund as CoreRefund;
use Wallee\PluginCore\Refund\RefundGatewayInterface;
use Wallee\PluginCore\Webhook\Exception\RetryableWebhookException;
use Wallee\Payment\Model\Webhook\BaseOrderLookupTrait; // 1. Use the base trait

/**
 * A trait for reusable logic within refund-related commands.
 */
trait RefundCommandTrait
{
    use BaseOrderLookupTrait; // 2. Add the base trait

    /**
     * Load refund entity from the PluginCore refund gateway.
     *
     * `findById()` never returns null — even a genuine "not found" surfaces as a
     * RefundException — so `isRetryable()` is the only thing telling a retryable
     * failure apart from a terminal one here. A retryable failure must not be
     * swallowed into a null: doing so would make the caller ack the webhook as
     * "nothing to do" and the portal would never retry it.
     *
     * @return CoreRefund|null
     * @throws RetryableWebhookException If the failure is retryable.
     */
    protected function loadRefund(): ?CoreRefund
    {
        /** @var RefundGatewayInterface $pluginCoreRefundGateway */
        $pluginCoreRefundGateway = $this->pluginCoreRefundGateway;

        try {
            return $pluginCoreRefundGateway->findById($this->context->spaceId, (int) $this->context->entityId);
        } catch (RefundException $e) {
            if ($e->isRetryable()) {
                throw new RetryableWebhookException(
                    "Could not load Refund {$this->context->entityId}: " . $e->getMessage(),
                    null,
                    $e
                );
            }

            $this->logger->error(
                "Could not load Refund {$this->context->entityId}: " . $e->getMessage(),
                ['exception' => $e]
            );

            return null;
        }
    }

    /**
     * Find order linked to the given refund.
     *
     * @param CoreRefund $refund
     * @return Order|null
     */
    protected function findOrderFromRefund(CoreRefund $refund): ?Order
    {
        // 3. Use the helper method from the base trait
        return $this->findOrderByTransactionId($refund->transactionId);
    }
}
