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

use Wallee\PluginCore\Transaction\TransactionPersistenceInterface;

/**
 * Persistence strategy for {@see \Wallee\PluginCore\Transaction\TransactionService::upsert()}
 * calls where the order already holds its transaction id.
 *
 * If upsert() had to create a replacement transaction, the id must not be adopted: the caller
 * compares the returned id with the order's and fails the checkout instead.
 */
class NoOpTransactionPersistence implements TransactionPersistenceInterface
{
    /**
     * @param int $transactionId
     * @return void
     */
    public function persist(int $transactionId): void
    {
    }
}
