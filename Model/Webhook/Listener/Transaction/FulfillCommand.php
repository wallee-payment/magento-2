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
namespace Wallee\Payment\Model\Webhook\Listener\Transaction;

use Magento\Framework\DB\TransactionFactory as DBTransactionFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender as OrderEmailSender;

/**
 * Webhook listener command to handle fulfilled transactions.
 */
class FulfillCommand extends AbstractCommand
{

    /**
     *
     * @var AuthorizedCommand
     */
    protected $_authorizedCommand;

    /**
     *
     * @param DBTransactionFactory $dbTransactionFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderEmailSender $orderEmailSender
     * @param AuthorizedCommand $authorizedCommand
     */
    public function __construct(DBTransactionFactory $dbTransactionFactory, OrderRepositoryInterface $orderRepository,
        OrderEmailSender $orderEmailSender, AuthorizedCommand $authorizedCommand)
    {
        parent::__construct($dbTransactionFactory, $orderRepository, $orderEmailSender);
        $this->_authorizedCommand = $authorizedCommand;
    }

    /**
     *
     * @param \Wallee\Sdk\Model\Transaction $entity
     * @param Order $order
     */
    public function execute($entity, Order $order)
    {
        $this->_authorizedCommand->execute($entity, $order);

        if ($order->getState() == Order::STATE_PAYMENT_REVIEW) {
            /** @var \Magento\Sales\Model\Order\Payment $payment */
            $payment = $order->getPayment();
            $payment->setIsTransactionApproved(true);
            $payment->update(false);
        } elseif ($order->getStatus() == 'processing_wallee') {
            $order->setState(Order::STATE_PROCESSING);
            $order->addStatusToHistory(true, \__('The order can be fulfilled now.'));
        }
        $this->_orderRepository->save($order);
    }
}