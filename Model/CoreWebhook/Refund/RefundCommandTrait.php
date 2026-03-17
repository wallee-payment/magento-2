<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\CoreWebhook\Refund;

use Magento\Sales\Model\Order;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\Sdk\Model\Refund;
use Wallee\Sdk\Service\RefundService;
use Wallee\Payment\Model\CoreWebhook\BaseOrderLookupTrait; // 1. Use the base trait

/**
 * A trait for reusable logic within refund-related commands.
 */
trait RefundCommandTrait
{
    use BaseOrderLookupTrait; // 2. Add the base trait

    /**
     * Load refund entity from the SDK.
     *
     * @return Refund|null
     */
    protected function loadRefund(): ?Refund
    {
        /** @var SdkProvider $sdkProvider */
        $sdkProvider = $this->sdkProvider;
        /** @var RefundService $refundService */
        $refundService = $sdkProvider->getService(RefundService::class);

        try {
            $spaceId = $sdkProvider->getSpaceId();
            $refundId = $this->context->entityId;
            return $refundService->read($spaceId, $refundId);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Find order linked to the given refund.
     *
     * @param Refund $refund
     * @return Order|null
     */
    protected function findOrderFromRefund(Refund $refund): ?Order
    {
        $transaction = $refund->getTransaction();
        if (!$transaction) {
            return null;
        }

        // 3. Use the helper method from the base trait
        return $this->findOrderByTransactionId($transaction->getId());
    }
}
