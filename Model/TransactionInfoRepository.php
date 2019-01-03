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
namespace Wallee\Payment\Model;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Wallee\Payment\Api\Data\TransactionInfoInterface;
use Wallee\Payment\Api\Data\TransactionInfoSearchResultsInterfaceFactory;
use Wallee\Payment\Model\ResourceModel\TransactionInfo as TransactionInfoResource;
use Wallee\Payment\Model\ResourceModel\TransactionInfo\CollectionFactory as TransactionInfoCollectionFactory;

/**
 * Transaction info CRUD service.
 */
class TransactionInfoRepository implements TransactionInfoRepositoryInterface
{

    /**
     *
     * @var TransactionInfoFactory
     */
    private $transactionInfoFactory;

    /**
     *
     * @var TransactionInfoCollectionFactory
     */
    private $transactionInfoCollectionFactory;

    /**
     *
     * @var TransactionInfoSearchResultsInterfaceFactory
     */
    private $searchResultsFactory;

    /**
     *
     * @var TransactionInfoResource
     */
    private $resource;

    /**
     *
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;

    /**
     *
     * @param TransactionInfoFactory $transactionInfoFactory
     * @param TransactionInfoCollectionFactory $transactionInfoCollectionFactory
     * @param TransactionInfoSearchResultsInterfaceFactory $searchResultsFactory
     * @param TransactionInfoResource $resource
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(TransactionInfoFactory $transactionInfoFactory,
        TransactionInfoCollectionFactory $transactionInfoCollectionFactory,
        TransactionInfoSearchResultsInterfaceFactory $searchResultsFactory, TransactionInfoResource $resource,
        CollectionProcessorInterface $collectionProcessor)
    {
        $this->transactionInfoFactory = $transactionInfoFactory;
        $this->transactionInfoCollectionFactory = $transactionInfoCollectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->resource = $resource;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * Create transaction info
     *
     * @param TransactionInfoInterface $object
     * @return TransactionInfo
     * @throws CouldNotSaveException
     */
    public function save(TransactionInfoInterface $object)
    {
        try {
            $this->resource->save($object);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(\__('Could not save the transaction info: %1', $exception->getMessage()),
                $exception);
        }
        return $object;
    }

    /**
     * Get info about transaction info by entity ID
     *
     * @param int $entityId
     * @return TransactionInfo
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function get($entityId)
    {
        if (! $entityId) {
            throw new InputException(\__('ID required'));
        }

        /** @var TransactionInfo $object */
        $object = $this->transactionInfoFactory->create();
        $this->resource->load($object, $entityId);
        if (! $object->getEntityId()) {
            throw new NoSuchEntityException(\__('Requested entity does not exist'));
        }
        return $object;
    }

    /**
     * Get info about transaction info by transaction ID
     *
     * @param int $spaceId
     * @param int $transactionId
     * @return TransactionInfo
     * @throws NoSuchEntityException
     */
    public function getByTransactionId($spaceId, $transactionId)
    {
        if (! $spaceId) {
            throw new InputException(\__('Space ID required'));
        }
        if (! $transactionId) {
            throw new InputException(\__('Transaction ID required'));
        }

        /** @var TransactionInfo $object */
        $object = $this->transactionInfoFactory->create();
        $this->resource->loadByTransaction($object, $spaceId, $transactionId);
        if (! $object->getEntityId()) {
            throw new NoSuchEntityException(\__('Requested entity does not exist'));
        }
        return $object;
    }

    /**
     * Get info about transaction info by order ID
     *
     * @param int $orderId
     * @return TransactionInfo
     * @throws NoSuchEntityException
     */
    public function getByOrderId($orderId)
    {
        if (! $orderId) {
            throw new InputException(\__('Order ID required'));
        }

        /** @var TransactionInfo $object */
        $object = $this->transactionInfoFactory->create();
        $this->resource->load($object, $orderId, TransactionInfoInterface::ORDER_ID);
        if (! $object->getEntityId()) {
            throw new NoSuchEntityException(\__('Requested entity does not exist'));
        }
        return $object;
    }

    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        /** @var \Wallee\Payment\Model\ResourceModel\TransactionInfo\Collection $collection */
        $collection = $this->transactionInfoCollectionFactory->create();

        $this->collectionProcessor->process($searchCriteria, $collection);

        /** @var \Wallee\Payment\Api\Data\TransactionInfoSearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    public function delete(TransactionInfoInterface $object)
    {
        try {
            $this->resource->delete($object);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(\__('Could not delete the transaction info: %1', $exception->getMessage()));
        }
        return true;
    }

    public function deleteByIdentifier($entityId)
    {
        $this->delete($this->get($entityId));
    }
}