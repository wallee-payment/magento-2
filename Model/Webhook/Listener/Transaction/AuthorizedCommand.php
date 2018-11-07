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
use Wallee\Sdk\Model\TransactionState;

/**
 * Webhook listener command to handle authorized transactions.
 */
class AuthorizedCommand extends AbstractCommand
{

    /**
     *
     * @param \Wallee\Sdk\Model\Transaction $entity
     * @param Order $order
     */
    public function execute($entity, Order $order)
    {
        if ($order->getWalleeAuthorized()) {
            // In case the order is already authorized.
            return;
        }

        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = $order->getPayment();
        $payment->setTransactionId($entity->getLinkedSpaceId() . '_' . $entity->getId());
        $payment->setIsTransactionClosed(false);
        $payment->registerAuthorizationNotification($entity->getAuthorizationAmount());

        if ($entity->getState() != TransactionState::FULFILL) {
            $order->setState(Order::STATE_PROCESSING);
            $order->addStatusToHistory('processing_wallee',
                \__('The order should not be fulfilled yet, as the payment is not guaranteed.'));
        }

        $order->setWalleeAuthorized(true);
        $this->_orderRepository->save($order);

        $this->sendOrderEmail($order);
    }
}