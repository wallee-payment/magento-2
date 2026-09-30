<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\Webhook;

use Magento\Sales\Model\Order;
use Wallee\Payment\Api\Data\TransactionInfoInterface;

/**
 * A base trait for reusable logic to find Magento entities.
 *
 * It assumes the class using this trait has the following properties available:
 * - $this->transactionInfoRepository
 * - $this->orderRepository
 * - $this->searchCriteriaBuilder
 * - $this->logger (optional)
 * - $this->orderFactory and $this->orderResourceModel (only for reloadOrder())
 */
trait BaseOrderLookupTrait
{
    /**
     * Re-reads an order from the database, bypassing the repository's in-request cache.
     *
     * OrderRepository hands back the instance it cached when the lifecycle handler looked the
     * order up — and that lookup happens *before* the order lock is acquired. By the time a
     * command owns the lock, a concurrent webhook may have written paid totals, a new state or
     * new payment amounts that the cached snapshot knows nothing about; persisting that
     * snapshot pushes the pre-lock values straight back over them. A command must therefore
     * work on, and save, the instance this returns.
     *
     * A brand new object is built rather than reloading the cached one in place, because the
     * cached order also carries an already-loaded payment collection. Reloading only refreshes
     * the sales_order columns and would leave that stale payment attached — and the payment is
     * where amounts such as base_amount_paid_online live.
     *
     * @param Order $order The order as handed out by the repository.
     * @return Order The same order, re-read from the database.
     */
    protected function reloadOrder(Order $order): Order
    {
        $reloadedOrder = $this->orderFactory->create();
        $this->orderResourceModel->load($reloadedOrder, (int) $order->getId());

        return $reloadedOrder;
    }

    /**
     * Helper to find TransactionInfo by Wallee Transaction ID.
     *
     * @param int $transactionId
     * @return TransactionInfoInterface|null
     */
    protected function findTransactionInfoByTransactionId(int $transactionId): ?TransactionInfoInterface
    {
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('transaction_id', $transactionId)->create();
        $items = $this->transactionInfoRepository->getList($searchCriteria)->getItems();
        return empty($items) ? null : array_shift($items);
    }

    /**
     * Helper to find a Magento Order by a Wallee Transaction ID.
     *
     * @param int $transactionId
     * @return Order|null
     */
    protected function findOrderByTransactionId(int $transactionId): ?Order
    {
        $transactionInfo = $this->findTransactionInfoByTransactionId($transactionId);
        if ($transactionInfo === null) {
            if (property_exists($this, 'logger')) {
                $this->logger->warning("Could not find TransactionInfo for Transaction {$transactionId}");
            }
            return null;
        }

        return $this->orderRepository->get($transactionInfo->getOrderId());
    }
}
