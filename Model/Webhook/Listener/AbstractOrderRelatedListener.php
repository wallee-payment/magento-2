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

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NotFoundException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
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
    private $resource;

    /**
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     *
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     *
     * @var OrderResourceModel
     */
    private $orderResourceModel;

    /**
     *
     * @var CommandPoolInterface
     */
    private $commandPool;

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
     * @param ResourceConnection $resource
     * @param LoggerInterface $logger
     * @param OrderFactory $orderFactory
     * @param OrderResourceModel $orderResourceModel
     * @param CommandPoolInterface $commandPool
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param TransactionInfoManagementInterface $transactionInfoManagement
     */
    public function __construct(ResourceConnection $resource, LoggerInterface $logger, OrderFactory $orderFactory,
        OrderResourceModel $orderResourceModel, CommandPoolInterface $commandPool,
        TransactionInfoRepositoryInterface $transactionInfoRepository,
        TransactionInfoManagementInterface $transactionInfoManagement)
    {
        $this->resource = $resource;
        $this->logger = $logger;
        $this->orderFactory = $orderFactory;
        $this->orderResourceModel = $orderResourceModel;
        $this->commandPool = $commandPool;
        $this->transactionInfoRepository = $transactionInfoRepository;
        $this->transactionInfoManagement = $transactionInfoManagement;
    }

    public function execute(Request $request)
    {
        $entity = $this->loadEntity($request);

        $connection = $this->beginTransaction();
        try {
            $order = $this->loadOrder($this->getOrderId($entity));
            if ($order instanceof Order) {
                if ($order->getWalleeTransactionId() != $this->getTransactionId($entity)) {
                    $this->logger->warning(
                        'wallee webhook: The transaction ID on the order ' . $order->getId() .
                        ' does not match the webhook\'s: ' . $this->getTransactionId($entity));
                    $connection->commit();
                    return;
                }
                $this->lock($order);
                $this->process($this->loadEntity($request), $this->loadOrder($order->getId()));
            }
            $connection->commit();
        } catch (\Exception $e) {
            $this->logger->critical($e);
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
    private function beginTransaction()
    {
        $connection = $this->resource->getConnection('sales');
        $connection->rawQuery("SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;");
        $connection->beginTransaction();
        return $connection;
    }

    /**
     * Loads the order by the given ID.
     *
     * @param int $orderId
     * @return Order|NULL
     */
    private function loadOrder($orderId)
    {
        if (! $orderId) {
            return null;
        }

        $order = $this->orderFactory->create();
        $this->orderResourceModel->load($order, $orderId);
        if (! $order->getEntityId()) {
            return null;
        } else {
            return $order;
        }
    }

    /**
     * Gets the ID of the order linked to the given entity.
     *
     * @param mixed $entity
     * @return int|NULL
     */
    private function getOrderId($entity)
    {
        try {
            $transactionInfo = $this->transactionInfoRepository->getByTransactionId($entity->getLinkedSpaceId(),
                $this->getTransactionId($entity));
            return $transactionInfo->getOrderId();
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * Creates a lock to prevent concurrency.
     *
     * @param Order $order
     */
    private function lock(Order $order)
    {
        $this->resource->getConnection()->update($this->resource->getTableName('sales_order'),
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
            $this->commandPool->get(\strtolower($entity->getState()))
                ->execute($entity, $order);
        } catch (NotFoundException $e) {
            // If the command cannot be found, we ignore it.
        }
    }

    /**
     * Loads the entity for the webhook request.
     *
     * @param Request $request
     * @return mixed
     */
    abstract protected function loadEntity(Request $request);

    /**
     * Gets the transaction's id linked to the entity.
     *
     * @param mixed $entity
     * @return int
     */
    abstract protected function getTransactionId($entity);
}