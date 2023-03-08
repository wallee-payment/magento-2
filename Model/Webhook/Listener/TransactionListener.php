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
namespace Wallee\Payment\Model\Webhook\Listener;

use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Psr\Log\LoggerInterface;
use Wallee\Payment\Api\TransactionInfoManagementInterface;
use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Wallee\Payment\Model\ApiClient;
use Wallee\Payment\Model\Webhook\Request;
use Wallee\Sdk\Service\TransactionService;

/**
 * Webhook listener to handle transactions.
 */
class TransactionListener extends AbstractOrderRelatedListener
{
    /**
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     *
     * @var TransactionInfoRepositoryInterface
     */
    private $transactionInfoRepository;

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
     * @param ResourceConnection $resource
     * @param LoggerInterface $logger
     * @param OrderFactory $orderFactory
     * @param OrderResourceModel $orderResourceModel
     * @param CommandPoolInterface $commandPool
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param TransactionInfoManagementInterface $transactionInfoManagement
     * @param ApiClient $apiClient
     * @param LoggerInterface $logger
     */
    public function __construct(ResourceConnection $resource, LoggerInterface $logger, OrderFactory $orderFactory,
        OrderResourceModel $orderResourceModel, CommandPoolInterface $commandPool,
        TransactionInfoRepositoryInterface $transactionInfoRepository,
        TransactionInfoManagementInterface $transactionInfoManagement, ApiClient $apiClient)
    {
        parent::__construct($resource, $logger, $orderFactory, $orderResourceModel, $commandPool,
            $transactionInfoRepository, $transactionInfoManagement);
        $this->transactionInfoRepository = $transactionInfoRepository;
        $this->transactionInfoManagement = $transactionInfoManagement;
        $this->apiClient = $apiClient;
        $this->logger=$logger;
    }

    /**
     * Actually processes the order related webhook request.
     *
     * @param \Wallee\Sdk\Model\Transaction $entity
     * @param Order $order
     */
    protected function process($entity, Order $order)
    {
        $this->logger->debug("TRANSACTION-LISTENER::process");
        $transactionInfo = $this->transactionInfoRepository->getByOrderId($order->getId());
        if ($transactionInfo->getState() != $entity->getState()) {
            parent::process($entity, $order);
        }
        $this->transactionInfoManagement->update($entity, $order);
    }

    /**
     * Loads the transaction for the webhook request.
     *
     * @param Request $request
     * @return \Wallee\Sdk\Model\Transaction
     */
    protected function loadEntity(Request $request)
    {
        return $this->apiClient->getService(TransactionService::class)->read($request->getSpaceId(),
            $request->getEntityId());
    }

    /**
     * Gets the transaction's ID.
     *
     * @param \Wallee\Sdk\Model\Transaction $entity
     * @return int
     */
    protected function getTransactionId($entity)
    {
        return $entity->getId();
    }
}