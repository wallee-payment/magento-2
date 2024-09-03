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

        $isOrderInReview = ($order->getState() == Order::STATE_PAYMENT_REVIEW);
        if (!$isOrderInReview) {
            $order->setState(Order::STATE_PAYMENT_REVIEW);
            $order->addStatusToHistory('pending',
                \__('The order should not be fulfilled yet, as the payment is not guaranteed.'));
        }
        
        $invoice = $this->getInvoiceForTransaction($transaction, $order);
        if (! ($invoice instanceof InvoiceInterface) || $invoice->getState() == Invoice::STATE_OPEN) {
            $isOrderInReview = ($order->getState() == Order::STATE_PAYMENT_REVIEW);

            if (! ($invoice instanceof InvoiceInterface)) {
                $order->setWalleeInvoiceAllowManipulation(true);
            }

            if (! ($invoice instanceof InvoiceInterface) || $invoice->getState() == Invoice::STATE_OPEN) {
                /** @var \Magento\Sales\Model\Order\Payment $payment */
                $payment = $order->getPayment();
                $payment->setTransactionId(null);
                $payment->setParentTransactionId($payment->getTransactionId());
                $payment->setIsTransactionClosed(true);
                $payment->registerCaptureNotification($entity->getAmount());
                if (! ($invoice instanceof InvoiceInterface) && !empty($payment->getCreatedInvoice())) {
                    $invoice = $payment->getCreatedInvoice();
                    $order->addRelatedObject($invoice);
                } else {
                    // Fix an issue that invoice doesn't have the correct status after call to registerCaptureNotification
                    // see \Magento\Sales\Model\Order\Payment\Operations\RegisterCaptureNotificationOperation::registerCaptureNotification
                    foreach ($order->getRelatedObjects() as $object) {
                        if ($object instanceof InvoiceInterface) {
                            $invoice = $object;
                            break;
                        }
                    }
                }

                if ($invoice instanceof InvoiceInterface) {
                    $invoice->setWalleeCapturePending(false);
                } else {
                    return false;
                }
            }

            if ($transaction->getState() == TransactionState::COMPLETED) {
                $order->setStatus('processing');
            }

            if ($isOrderInReview) {
                $order->setState(Order::STATE_PAYMENT_REVIEW);
                $order->addStatusToHistory(true);
            }

            $order->setWalleeAuthorized(true);
            $order->setStatus('processing');
            $order->setState(Order::STATE_PROCESSING);

            $this->orderRepository->save($order);
            $this->sendOrderEmail($order);
        }
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