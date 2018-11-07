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

use Magento\Framework\DataObject;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Wallee\Payment\Model\Service\Order\TransactionService;
use Wallee\Sdk\Model\TransactionState;

/**
 * Frontend controller action to handle successful payments.
 */
class Success extends \Wallee\Payment\Controller\Transaction
{

    /**
     *
     * @param Context $context
     * @param OrderRepositoryInterface $orderRepository
     * @param TransactionService $transactionService
     */
    public function __construct(Context $context, OrderRepositoryInterface $orderRepository,
        TransactionService $transactionService)
    {
        parent::__construct($context, $orderRepository, $transactionService);
    }

    public function execute()
    {
        $order = $this->getOrder();

        $this->_transactionService->waitForTransactionState($order,
            [
                TransactionState::AUTHORIZED,
                TransactionState::COMPLETED,
                TransactionState::FULFILL
            ], 5);

        return $this->_redirect($this->getSuccessRedirectionPath($order));
    }

    /**
     * Gets the path to redirect the customer to.
     *
     * @param Order $order
     * @return string
     */
    protected function getSuccessRedirectionPath(Order $order)
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