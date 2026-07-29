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
namespace Wallee\Payment\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Wallee\Payment\Model\Payment\Method\Adapter;
use Wallee\PluginCore\Transaction\TransactionGatewayInterface;

/**
 * Observer to validate the cancellation of an invoice.
 */
class CancelInvoice implements ObserverInterface
{

    /**
     *
     * @var TransactionGatewayInterface
     */
    private $transactionGateway;

    /**
     *
     * @param TransactionGatewayInterface $transactionGateway
     */
    public function __construct(TransactionGatewayInterface $transactionGateway)
    {
        $this->transactionGateway = $transactionGateway;
    }

    /**
     * Prevent invoice cancellation for this payment method in certain transaction states.
     *
     * @param Observer $observer the observer instance containing event data
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order\Invoice $invoice */
        $invoice = $observer->getInvoice();
        $order = $invoice->getOrder();

        if ($order->getPayment()->getMethodInstance() instanceof Adapter) {
            if ($invoice->getWalleeCapturePending()) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    \__('The invoice cannot be cancelled as its capture has already been requested.')
                );
            }

            if (! $order->getWalleeInvoiceAllowManipulation() &&
                ! $invoice->getWalleeDerecognized()) {
                // The invoice can only be cancelled by the merchant if the transaction is in state 'AUTHORIZED'.
                $transaction = $this->transactionGateway->find(
                    (int) $order->getWalleeSpaceId(),
                    (int) $order->getWalleeTransactionId()
                );
                if ($transaction === null || ! $transaction->state->allowsInvoiceManipulation()) {
                    throw new \Magento\Framework\Exception\LocalizedException(\__('The invoice cannot be cancelled.'));
                }
            }
        }
    }
}
