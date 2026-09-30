<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\Webhook\DeliveryIndication;

use Magento\Sales\Model\Order;
use Wallee\PluginCore\DeliveryIndication\DeliveryIndication;
use Wallee\PluginCore\DeliveryIndication\Exception\DeliveryIndicationException;
use Wallee\PluginCore\Webhook\Exception\RetryableWebhookException;
use Wallee\Payment\Model\Webhook\BaseOrderLookupTrait;

/**
 * A trait for reusable logic within delivery-indication related webhook commands.
 */
trait DeliveryIndicationCommandTrait
{
    use BaseOrderLookupTrait;

    /**
     * Load delivery indication entity from PluginCore.
     *
     * A retryable failure (see AbstractDomainException::isRetryable()) must not be
     * swallowed into a null here — doing so would make the caller ack the webhook
     * as "nothing to do" and the portal would never retry it. A non-retryable
     * failure returns null, same as the gateway confirming the entity does not
     * exist.
     *
     * @return DeliveryIndication|null
     * @throws RetryableWebhookException If the failure is retryable.
     */
    protected function loadDeliveryIndication(): ?DeliveryIndication
    {
        try {
            return $this->deliveryIndicationGateway->get($this->context->spaceId, $this->context->entityId);
        } catch (DeliveryIndicationException $e) {
            if ($e->isRetryable()) {
                throw new RetryableWebhookException(
                    "Could not load DeliveryIndication {$this->context->entityId}: " . $e->getMessage(),
                    null,
                    $e
                );
            }

            $this->logger->error(
                "Could not load DeliveryIndication {$this->context->entityId}: " . $e->getMessage(),
                ['exception' => $e]
            );

            return null;
        }
    }

    /**
     * Find order linked to the given delivery indication.
     *
     * @param DeliveryIndication $indication
     * @return Order|null
     */
    protected function findOrderFromIndication(DeliveryIndication $indication): ?Order
    {
        if ($indication->transactionId === null) {
            return null;
        }

        return $this->findOrderByTransactionId($indication->transactionId);
    }
}
