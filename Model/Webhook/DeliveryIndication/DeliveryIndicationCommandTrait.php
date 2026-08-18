<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\Webhook\DeliveryIndication;

use Magento\Sales\Model\Order;
use Wallee\PluginCore\DeliveryIndication\DeliveryIndication;
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
     * @return DeliveryIndication|null
     */
    protected function loadDeliveryIndication(): ?DeliveryIndication
    {
        try {
            return $this->deliveryIndicationGateway->get($this->context->spaceId, $this->context->entityId);
        } catch (\Exception $e) {
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
