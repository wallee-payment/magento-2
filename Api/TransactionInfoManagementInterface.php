<?php
/**
 * wallee Magento 2
 *
 * This Magento 2 extension enables to process payments with wallee (https://www.wallee.com/).
 *
 * @package Wallee_Payment
 * @author wallee AG (http://www.wallee.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
namespace Wallee\Payment\Api;

use Magento\Sales\Model\Order;
use Wallee\Sdk\Model\Transaction;

/**
 * Transaction info management interface.
 *
 * @api
 */
interface TransactionInfoManagementInterface
{

    /**
     * Stores the transaction data in the database.
     *
     * @param Transaction $transaction
     * @param Order $order
     * @return Data\TransactionInfoInterface
     */
    public function update(Transaction $transaction, Order $order);

	/**
	 * Update the transaction info with the success and failure URL to redirect the customer after placing the order
	 *
	 * @param Transaction $transaction
	 * @param string $successUrl
	 * @param string $failureUrl
	 * @return Data\TransactionInfoInterface
	 */
	public function setRedirectUrls(Transaction $transaction, $successUrl, $failureUrl);
}