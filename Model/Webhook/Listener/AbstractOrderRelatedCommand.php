<?php
/**
 * wallee Magento 2
 *
 * This Magento 2 extension enables to process payments with wallee (https://www.wallee.com/).
 *
 * @package Wallee_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
namespace Wallee\Payment\Model\Webhook\Listener;

use Magento\Framework\DB\TransactionFactory as DBTransactionFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Email\Sender\OrderSender as OrderEmailSender;
use Wallee\Sdk\Model\Transaction;

/**
 * Abstract webhook listener command for order related entites.
 */
abstract class AbstractOrderRelatedCommand implements CommandInterface
{

    /**
     *
     * @var DBTransactionFactory
     */
    protected $_dbTransactionFactory;

    /**
     *
     * @var OrderRepositoryInterface
     */
    protected $_orderRepository;

    /**
     *
     * @var OrderEmailSender
     */
    protected $_orderEmailSender;

    /**
     *
     * @param DBTransactionFactory $dbTransactionFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderEmailSender $orderEmailSender
     */
    public function __construct(DBTransactionFactory $dbTransactionFactory, OrderRepositoryInterface $orderRepository,
        OrderEmailSender $orderEmailSender)
    {
        $this->_dbTransactionFactory = $dbTransactionFactory;
        $this->_orderRepository = $orderRepository;
        $this->_orderEmailSender = $orderEmailSender;
    }

    /**
     * Sends the order email if not already sent.
     *
     * @param Order $order
     */
    protected function sendOrderEmail(Order $order)
    {
        if ($order->getStore()->getConfig('wallee_payment/email/order') && ! $order->getEmailSent()) {
            $this->_orderEmailSender->send($order);
        }
    }

    /**
     * Gets the invoice linked to the given transaction.
     *
     * @param Transaction $transaction
     * @param Order $order
     * @return Invoice
     */
    protected function getInvoiceForTransaction(Transaction $transaction, Order $order)
    {
        foreach ($order->getInvoiceCollection() as $invoice) {
            /** @var Invoice $invoice */
            if (\strpos($invoice->getTransactionId(), $transaction->getLinkedSpaceId() . '_' . $transaction->getId()) ===
                0 && $invoice->getState() != Invoice::STATE_CANCELED) {
                $invoice->load($invoice->getId());
                return $invoice;
            }
        }
    }
}