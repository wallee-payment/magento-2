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
namespace Wallee\Payment\Model\Webhook\Listener;

use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Wallee\Sdk\Model\Transaction;

/**
 * Abstract webhook listener command for order related entites.
 */
abstract class AbstractOrderRelatedCommand implements CommandInterface
{

    /**
     * Gets the invoice linked to the given transaction.
     *
     * @param Transaction $transaction
     * @param Order $order
     * @return Invoice
     */
	/**
	 * Try to retrieve an invoice for the given transaction.
	 *
	 * The method checks multiple sources:
	 * 1. Existing invoices in the order (matched by transaction ID).
	 * 2. Newly created invoice from the payment object.
	 * 3. Related objects attached to the order (fallback).
	 *
	 * @param Transaction $transaction
	 * @param Order $order
	 * @return InvoiceInterface|null
	 */
	protected function getInvoiceForTransaction(Transaction $transaction, Order $order): ?InvoiceInterface
	{
		// 1. Check invoice collection for a matching transactionId
		foreach ($order->getInvoiceCollection() as $invoice) {
			if (\strpos((string) $invoice->getTransactionId(), $transaction->getLinkedSpaceId() . '_' . $transaction->getId()) === 0
			  && $invoice->getState() != Invoice::STATE_CANCELED) {
				return $invoice; // already loaded in collection, no need for load()
			}
		}

		// 2. If nothing found, check if a new invoice was created by the payment capture
		$payment = $order->getPayment();
		if ($payment) {
			$createdInvoice = $payment->getCreatedInvoice();
			if ($createdInvoice instanceof InvoiceInterface) {
				$order->addRelatedObject($createdInvoice);
				return $createdInvoice;
			}
		}

		// 3. As a final fallback, check related objects in the order
		foreach ($order->getRelatedObjects() as $object) {
			if ($object instanceof InvoiceInterface) {
				return $object;
			}
		}

		// 4. No invoice found
		return null;
	}
}
