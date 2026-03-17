<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\CoreWebhook\DeliveryIndication;

use Magento\Sales\Model\Order;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\Sdk\Model\DeliveryIndication;
use Wallee\Sdk\Service\DeliveryIndicationService;
use Wallee\Payment\Model\CoreWebhook\BaseOrderLookupTrait; // 1. Use the base trait

/**
 * A trait for reusable logic within delivery-indication related webhook commands.
 */
trait DeliveryIndicationCommandTrait
{
    use BaseOrderLookupTrait; // 2. Add the base trait

    /**
     * Load delivery indication entity from the SDK.
     *
     * @return DeliveryIndication|null
     */
    protected function loadDeliveryIndication(): ?DeliveryIndication
    {
        $deliveryIndicationService = $this->sdkProvider->getService(DeliveryIndicationService::class);

        // Note: Your original code had $this->context->spaceId.
        // If this is correct (from our WebhookProcessor update), this is fine.
        // If not, you may need to fetch the spaceId from the SdkProvider.
        $spaceId = $this->sdkProvider->getSpaceId(); // Safer way

        $deliveryIndication = $deliveryIndicationService->read($spaceId, $this->context->entityId);
        return $deliveryIndication;
    }

    /**
     * Find order linked to the given delivery indication.
     *
     * @param DeliveryIndication $indication
     * @return Order|null
     */
    protected function findOrderFromIndication(DeliveryIndication $indication): ?Order
    {
        $transaction = $indication->getTransaction();
        if (!$transaction) {
            return null;
        }

        // 3. Use the helper method from the base trait
        return $this->findOrderByTransactionId($transaction->getId());
    }
}
