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
namespace Wallee\Payment\Model\Webhook\Listener;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NotFoundException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Wallee\Payment\Api\TransactionInfoManagementInterface;
use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Wallee\Payment\Model\Webhook\ListenerInterface;
use Wallee\Payment\Model\Webhook\Request;

/**
 * Abstract webhook listener for order related entities.
 */
abstract class AbstractOrderRelatedListener implements ListenerInterface
{

    /**
     *
     * @var ResourceConnection
     */
    protected $_resource;

    /**
     *
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     *
     * @var OrderRepositoryInterface
     */
    protected $_orderRepository;

    /**
     *
     * @var SearchCriteriaBuilder
     */
    protected $_searchCriteriaBuilder;

    /**
     *
     * @var CommandPoolInterface
     */
    protected $_commandPool;

    /**
     *
     * @var TransactionInfoRepositoryInterface
     */
    protected $_transactionInfoRepository;

    /**
     *
     * @var TransactionInfoManagementInterface
     */
    protected $_transactionInfoManagement;

    /**
     *
     * @param ResourceConnection $resource
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param CommandPoolInterface $commandPool
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param TransactionInfoManagementInterface $transactionInfoManagement
     */
    public function __construct(ResourceConnection $resource, LoggerInterface $logger,
        OrderRepositoryInterface $orderRepository, SearchCriteriaBuilder $searchCriteriaBuilder,
        CommandPoolInterface $commandPool, TransactionInfoRepositoryInterface $transactionInfoRepository,
        TransactionInfoManagementInterface $transactionInfoManagement)
    {
        $this->_resource = $resource;
        $this->_logger = $logger;
        $this->_orderRepository = $orderRepository;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_commandPool = $commandPool;
        $this->_transactionInfoRepository = $transactionInfoRepository;
        $this->_transactionInfoManagement = $transactionInfoManagement;
    }

    public function execute(Request $request)
    {
        $entity = $this->loadEntity($request);

        $connection = $this->beginTransaction();
        try {
            $order = $this->getOrderByIncrementId($this->getOrderIncrementId($entity));
            if ($order instanceof Order) {
                if ($order->getWalleeTransactionId() == $this->getTransactionId($entity)) {
                    $this->lock($order);
                    $this->process($entity, $order->load($order->getId()));
                }
            }
            $connection->commit();
        } catch (\Exception $e) {
            $this->_logger->critical($e);
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * Starts a database transaction with isolation level 'read uncommitted'.
     *
     * In case of two parallel requests linked to the same order, data written to the database by the first will
     * not be up-to-date in the second. This can lead to processing the same data multiple times. By setting the
     * isolation level to 'read uncommitted' this issue can be avoided.
     *
     * An alternative solution to this problem would be to use optimistic locking. However, this could lead to database
     * rollbacks and as for example updating the order status could lead to triggering further processes which may not
     * propertly handle rollbacks, this could result in inconsistencies.
     *
     * @return \Magento\Framework\DB\Adapter\AdapterInterface
     */
    protected function beginTransaction()
    {
        $connection = $this->_resource->getConnection('sales');
        $connection->rawQuery("SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;");
        $connection->beginTransaction();
        return $connection;
    }

    /**
     * Gets the order by its increment id.
     *
     * @param string $incrementId
     * @return Order|NULL
     */
    protected function getOrderByIncrementId($incrementId)
    {
        $searchCriteria = $this->_searchCriteriaBuilder->addFilter('increment_id', $incrementId)
            ->setPageSize(1)
            ->create();
        $orders = $this->_orderRepository->getList($searchCriteria)->getItems();
        if (! empty($orders)) {
            return \current($orders);
        } else {
            return null;
        }
    }

    /**
     * Creates a lock to prevent concurrency.
     *
     * @param Order $order
     */
    protected function lock(Order $order)
    {
        $this->_resource->getConnection()->update($this->_resource->getTableName('sales_order'),
            [
                'wallee_lock' => \date('Y-m-d H:i:s')
            ], [
                'entity_id = ?' => $order->getId()
            ]);
    }

    /**
     * Actually processes the order related webhook request.
     *
     * @param mixed $entity
     * @param Order $order
     */
    protected function process($entity, Order $order)
    {
        try {
            $this->_commandPool->get(\strtolower($entity->getState()))
                ->execute($entity, $order);
        } catch (NotFoundException $e) {}
    }

    /**
     * Loads the entity for the webhook request.
     *
     * @param Request $request
     * @return mixed
     */
    abstract protected function loadEntity(Request $request);

    /**
     * Gets the order's increment id linked to the entity.
     *
     * @param mixed $entity
     * @return string
     */
    abstract protected function getOrderIncrementId($entity);

    /**
     * Gets the transaction's id linked to the entity.
     *
     * @param mixed $entity
     * @return int
     */
    abstract protected function getTransactionId($entity);
}