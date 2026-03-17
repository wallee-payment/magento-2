<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\CoreWebhook;

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
 */
trait BaseOrderLookupTrait
{
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
