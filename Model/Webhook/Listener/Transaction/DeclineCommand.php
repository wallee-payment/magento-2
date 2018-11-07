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

use Magento\Sales\Model\Order;

/**
 * Webhook listener command to handle declined transactions.
 */
class DeclineCommand extends AbstractCommand
{

    /**
     *
     * @param \Wallee\Sdk\Model\Transaction $entity
     * @param Order $order
     */
    public function execute($entity, Order $order)
    {
        if ($order->getState() != Order::STATE_CANCELED && $order->canCancel()) {
            $order->setWalleeInvoiceAllowManipulation(true);
            /** @var \Magento\Sales\Model\Order\Payment $payment */
            $payment = $order->getPayment();
            $payment->setIsTransactionDenied(true);
            $payment->update(false);
        }
        $this->_orderRepository->save($order);
    }
}