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
namespace Wallee\Payment\Controller\Transaction;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\DataObject;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Wallee\Payment\Model\Service\Order\TransactionService;
use Wallee\Sdk\Model\Transaction;

/**
 * Frontend controller action to handle failed payments.
 */
class Failure extends \Wallee\Payment\Controller\Transaction
{

    /**
     *
     * @var CheckoutSession
     */
    protected $_checkoutSession;

    /**
     *
     * @param Context $context
     * @param OrderRepositoryInterface $orderRepository
     * @param TransactionService $transactionService
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(Context $context, OrderRepositoryInterface $orderRepository,
        TransactionService $transactionService, CheckoutSession $checkoutSession)
    {
        parent::__construct($context, $orderRepository, $transactionService);
        $this->_checkoutSession = $checkoutSession;
    }

    public function execute()
    {
        $order = $this->getOrder();

        $this->_checkoutSession->restoreQuote();

        $this->messageManager->addErrorMessage($this->getFailureMessage($order));
        return $this->_redirect($this->getFailureRedirectionPath($order));
    }

    /**
     * Gets the reason for the transaction to fail.
     *
     * @param Order $order
     * @return string
     */
    protected function getFailureMessage(Order $order)
    {
        try {
            $transaction = $this->_transactionService->getTransaction($order->getWalleeSpaceId(),
                $order->getWalleeTransactionId());
            if ($transaction instanceof Transaction && $transaction->getUserFailureMessage() != null) {
                return $transaction->getUserFailureMessage();
            }
        } catch (\Exception $e) {}
        return \__('The payment process could not have been finished successfully.');
    }

    /**
     * Gets the path to redirect the customer to.
     *
     * @param Order $order
     * @return string
     */
    protected function getFailureRedirectionPath(Order $order)
    {
        $response = new DataObject();
        $response->setPath('checkout/cart');
        $this->_eventManager->dispatch('wallee_failure_redirection_path',
            [
                'order' => $order,
                'response' => $response
            ]);
        return $response->getPath();
    }
}