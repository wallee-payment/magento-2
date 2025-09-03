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
namespace Wallee\Payment\Model\Webhook\Listener\TransactionInvoice;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Email\Sender\OrderSender as OrderEmailSender;
use Magento\Sales\Model\Order\Payment\Transaction as MagentoTransaction;
use Wallee\Payment\Model\Webhook\Listener\Transaction\AuthorizedCommand;
use Wallee\Sdk\Model\Transaction;
use Wallee\Sdk\Model\TransactionState;

/**
 * Webhook listener command to handle captured transaction invoices.
 */
class CaptureCommand extends AbstractCommand
{

    /**
     *
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     *
     * @var OrderEmailSender
     */
    private $orderEmailSender;

    /**
     *
     * @var AuthorizedCommand
     */
    private $authorizedCommand;

    /**
     *
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderEmailSender $orderEmailSender
     * @param AuthorizedCommand $authorizedCommand
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        OrderEmailSender $orderEmailSender,
        AuthorizedCommand $authorizedCommand)
    {
        $this->orderRepository = $orderRepository;
        $this->orderEmailSender = $orderEmailSender;
        $this->authorizedCommand = $authorizedCommand;
    }

    /**
     *
     * @param \Wallee\Sdk\Model\TransactionInvoice $entity
     * @param Order $order
     */
	public function execute($entity, Order $order)
	{
		$this->authorizedCommand->execute($entity, $order);

		$transaction = $entity->getCompletion()
		  ->getLineItemVersion()
		  ->getTransaction();
        
        $txState = $transaction->getState();
        
        // If the transaction is already FULFILL or COMPLETED - do not set payment_review
        if (!in_array($txState, [TransactionState::FULFILL, TransactionState::COMPLETED], true)) {
            // Put order into review if not already
            if ($order->getState() !== Order::STATE_PAYMENT_REVIEW) {
                $order->setState(Order::STATE_PAYMENT_REVIEW);
                $order->addStatusToHistory('pending', __('Payment is under review.'));
            }
        }

		$invoice = $this->getInvoiceForTransaction($transaction, $order);

		$needsCapture = !($invoice instanceof InvoiceInterface) || $invoice->getState() == Invoice::STATE_OPEN;
		if ($needsCapture) {
			$invoice = $this->captureInvoice($order, $entity->getAmount(), $invoice);
		}

		if (!$invoice) {
			return false;
		}

		// Mark transaction complete
		if ($transaction->getState() == TransactionState::FULFILL) {
			$order->setState(Order::STATE_PROCESSING);
			$order->setStatus('processing');
		}

		$order->setWalleeAuthorized(true);

		$this->orderRepository->save($order);
		$this->sendOrderEmail($order);
	}

	/**
	 * @return InvoiceInterface|null
	 */
	private function captureInvoice(Order $order, float $amount, ?InvoiceInterface $invoice)
	{
		/** @var \Magento\Sales\Model\Order\Payment $payment */
		$payment = $order->getPayment();
		$payment->setTransactionId(null);
		$payment->setParentTransactionId($payment->getTransactionId());
		$payment->setIsTransactionClosed(true);
		$payment->registerCaptureNotification($amount, true);

		$invoice = $payment->getCreatedInvoice() ?: $invoice;

		if ($invoice instanceof InvoiceInterface) {
			$invoice->pay();
			$invoice->setWalleeCapturePending(false);
			$order->addRelatedObject($invoice);
			return $invoice;
		}

		foreach ($order->getRelatedObjects() as $object) {
			if ($object instanceof InvoiceInterface) {
				return $object;
			}
		}

		return null;
	}

    /**
     * Sends the order email if not already sent.
     *
     * @param Order $order
     * @return void
     */
    private function sendOrderEmail(Order $order)
    {
        if ($order->getStore()->getConfig('wallee_payment/email/order') && ! $order->getEmailSent()) {
            $this->orderEmailSender->send($order);
        }
    }

}
