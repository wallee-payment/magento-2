<?php
/**
 * wallee Magento 2
 *
 * This Magento 2 extension enables to process payments with wallee (https://www.wallee.com).
 *
 * @package Wallee_Payment
 * @author wallee AG (https://www.wallee.com)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)

 */
declare(strict_types=1);

namespace Wallee\Payment\Model\PluginCore;

use Magento\Framework\App\ResourceConnection;
use Magento\Quote\Model\Quote;
use Wallee\PluginCore\Transaction\TransactionPersistenceInterface;

/**
 * Per-quote persistence strategy passed to {@see \Wallee\PluginCore\Transaction\TransactionService::upsert()}.
 *
 * The plugin-core contract only forwards the transaction id; the space id is
 * supplied at construction time by the caller that already knows which space
 * the quote belongs to.
 */
class QuoteTransactionPersistence implements TransactionPersistenceInterface
{
    /**
     *
     * @param Quote $quote
     * @param int $spaceId
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly Quote $quote,
        private readonly int $spaceId,
        private readonly ResourceConnection $resource,
    ) {
    }

    /**
     * Persists the resolved transaction id (and the owning space id) on the
     * quote model and the underlying quote table.
     *
     * @param int $transactionId
     * @return void
     */
    public function persist(int $transactionId): void
    {
        $this->quote->setWalleeSpaceId($this->spaceId);
        $this->quote->setWalleeTransactionId($transactionId);

        $this->resource->getConnection()->update(
            $this->resource->getTableName('quote'),
            [
                'wallee_space_id' => $this->spaceId,
                'wallee_transaction_id' => $transactionId,
            ],
            ['entity_id = ?' => $this->quote->getId()]
        );
    }
}
