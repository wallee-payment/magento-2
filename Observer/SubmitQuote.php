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

use Magento\Framework\DB\TransactionFactory as DBTransactionFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Wallee\Payment\Api\TransactionInfoManagementInterface;
use Wallee\Payment\Helper\Data as Helper;
use Wallee\Payment\Model\ApiClient;
use Wallee\Payment\Model\Service\Order\TransactionService;
use Wallee\Sdk\Model\TransactionState;
use Wallee\Sdk\Service\ChargeFlowService;

/**
 * Observer to create an invoice and confirm the transaction when the quote is submitted.
 */
class SubmitQuote implements ObserverInterface
{

    /**
     *
     * @var DBTransactionFactory
     */
    private $dbTransactionFactory;

    /**
     *
     * @var Helper
     */
    private $helper;

    /**
     *
     * @var TransactionService
     */
    private $transactionService;

    /**
     *
     * @var TransactionInfoManagementInterface
     */
    private $transactionInfoManagement;

    /**
     *
     * @var ApiClient
     */
    private $apiClient;

    /**
     *
     * @param DBTransactionFactory $dbTransactionFactory
     * @param Helper $helper
     * @param TransactionService $transactionService
     * @param TransactionInfoManagementInterface $transactionInfoManagement
     * @param ApiClient $apiClient
     */
    public function __construct(DBTransactionFactory $dbTransactionFactory, Helper $helper,
        TransactionService $transactionService, TransactionInfoManagementInterface $transactionInfoManagement,
        ApiClient $apiClient)
    {
        $this->dbTransactionFactory = $dbTransactionFactory;
        $this->helper = $helper;
        $this->transactionService = $transactionService;
        $this->transactionInfoManagement = $transactionInfoManagement;
        $this->apiClient = $apiClient;
    }

    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getOrder();

        $transactionId = $order->getWalleeTransactionId();
        if (! empty($transactionId)) {
            $invoice = $this->createInvoice($order);

            $transaction = $this->transactionService->getTransaction($order->getWalleeSpaceId(),
                $order->getWalleeTransactionId());
            $this->transactionInfoManagement->update($transaction, $order);

            $transaction = $this->transactionService->confirmTransaction($transaction, $order, $invoice, $this->helper->isAdminArea(),
                $order->getWalleeToken());
            $this->transactionInfoManagement->update($transaction, $order);
        }

        if ($order->getWalleeChargeFlow() && $this->helper->isAdminArea()) {
            $this->apiClient->getService(ChargeFlowService::class)->applyFlow(
                $order->getWalleeSpaceId(), $order->getWalleeTransactionId());

            if ($order->getWalleeToken() != null) {
                $this->transactionService->waitForTransactionState($order,
                    [
                        TransactionState::AUTHORIZED,
                        TransactionState::COMPLETED,
                        TransactionState::FULFILL
                    ], 3);
            }
        }
    }

    /**
     * Creates an invoice for the order.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @param Order $order
     * @return Order\Invoice
     */
    private function createInvoice(Order $order)
    {
        $invoice = $order->prepareInvoice();
        $invoice->register();
        $invoice->setTransactionId(
            $order->getWalleeSpaceId() . '_' . $order->getWalleeTransactionId());

        $this->dbTransactionFactory->create()
            ->addObject($order)
            ->addObject($invoice)
            ->save();
        return $invoice;
    }
}