<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\Webhook\Refund;

use Magento\Sales\Model\Order;
use Wallee\PluginCore\Refund\Exception\RefundException;
use Wallee\PluginCore\Refund\Refund as CoreRefund;
use Wallee\PluginCore\Refund\RefundGatewayInterface;
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
     * @return CoreRefund|null
     */
    protected function loadRefund(): ?CoreRefund
    {
        /** @var RefundGatewayInterface $pluginCoreRefundGateway */
        $pluginCoreRefundGateway = $this->pluginCoreRefundGateway;

        try {
            return $pluginCoreRefundGateway->findById($this->context->spaceId, (int) $this->context->entityId);
        } catch (RefundException $e) {
            $this->logger->error($e->getLocalizedMessage()->getDefault(), [
                'entityId' => $this->context->entityId,
                'spaceId' => $this->context->spaceId,
                'exception' => $e,
            ]);
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
