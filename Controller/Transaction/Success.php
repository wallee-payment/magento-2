<?php
/**
 * wallee Magento 2
 *
 * This Magento 2 extension enables to process payments with wallee (https://www.wallee.com/).
 *
 * @package Wallee_Payment
 * @author wallee AG (http://www.wallee.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
namespace Wallee\Payment\Controller\Transaction;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Checkout\Model\Session\SuccessValidator;
use Magento\Framework\DataObject;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Wallee\Sdk\Model\TransactionState;

/**
 * Frontend controller action to handle successful payments.
 */
class Success extends \Wallee\Payment\Controller\Transaction
{
    /**
     *
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     *
     * @var SuccessValidator
     */
    private $successValidator;

    /**
     *
     * @param Context $context
     * @param OrderRepositoryInterface $orderRepository
     * @param CheckoutSession $checkoutSession
     * @param SuccessValidator $successValidator
     */
    public function __construct(Context $context, OrderRepositoryInterface $orderRepository,
        CheckoutSession $checkoutSession, SuccessValidator $successValidator)
    {
        parent::__construct($context, $orderRepository);
        $this->checkoutSession = $checkoutSession;
        $this->successValidator = $successValidator;
    }

    public function execute()
    {
        $order = $this->getOrder();
        if (! $this->successValidator->isValid()) {
            $this->messageManager->addErrorMessage(
                \__(
                    'There seems to have been a problem with your order. ' .
                    'However, the payment was successful. Please contact us.'));
            return $this->_redirect('checkout/cart');
        }

        $this->checkoutSession->setLastOrderId($order->getId())
            ->setLastRealOrderId($order->getIncrementId())
            ->setLastOrderStatus($order->getStatus());

        return $this->_redirect($this->getSuccessRedirectionPath($order));
    }

    /**
     * Gets the path to redirect the customer to.
     *
     * @param Order $order
     * @return string
     */
    private function getSuccessRedirectionPath(Order $order)
    {
        $response = new DataObject();
        $response->setPath('checkout/onepage/success');
        $this->_eventManager->dispatch('wallee_success_redirection_path',
            [
                'order' => $order,
                'response' => $response
            ]);
        return $response->getPath();
    }
}