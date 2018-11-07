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
namespace Wallee\Payment\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Wallee\Payment\Model\Payment\Method\Adapter;
use Wallee\Payment\Model\Service\Order\TransactionService;
use Wallee\Sdk\Model\TransactionState;

/**
 * Observer to validate the cancellation of an invoice.
 */
class CancelInvoice implements ObserverInterface
{

    /**
     *
     * @var TransactionService
     */
    protected $_transactionService;

    /**
     *
     * @param TransactionService $transactionService
     */
    public function __construct(TransactionService $transactionService)
    {
        $this->_transactionService = $transactionService;
    }

    public function execute(Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order\Invoice $invoice */
        $invoice = $observer->getInvoice();
        $order = $invoice->getOrder();

        if ($order->getPayment()->getMethodInstance() instanceof Adapter) {
            if ($invoice->getWalleeCapturePending()) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    \__('The invoice cannot be cancelled as its capture has already been requested.'));
            }

            if (! $order->getWalleeInvoiceAllowManipulation()) {
                // The invoice can only be cancelled by the merchant if the transaction is in state 'AUTHORIZED'.
                $transaction = $this->_transactionService->getTransaction($order->getWalleeSpaceId(),
                    $order->getWalleeTransactionId());
                if ($transaction->getState() != TransactionState::AUTHORIZED) {
                    throw new \Magento\Framework\Exception\LocalizedException(\__('The invoice cannot be cancelled.'));
                }
            }
        }
    }
}