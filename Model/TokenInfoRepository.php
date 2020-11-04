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
namespace Wallee\Payment\Model;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Wallee\Payment\Api\TokenInfoRepositoryInterface;
use Wallee\Payment\Api\Data\TokenInfoInterface;
use Wallee\Payment\Api\Data\TokenInfoSearchResultsInterfaceFactory;
use Wallee\Payment\Model\ResourceModel\TokenInfo as TokenInfoResource;
use Wallee\Payment\Model\ResourceModel\TokenInfo\CollectionFactory as TokenInfoCollectionFactory;

/**
 * Token info CRUD service.
 */
class TokenInfoRepository implements TokenInfoRepositoryInterface
{

    /**
     *
     * @var TokenInfoFactory
     */
    private $tokenInfoFactory;

    /**
     *
     * @var TokenInfoCollectionFactory
     */
    private $tokenInfoCollectionFactory;

    /**
     *
     * @var TokenInfoSearchResultsInterfaceFactory
     */
    private $searchResultsFactory;

    /**
     *
     * @var TokenInfoResource
     */
    private $resource;

    /**
     *
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;

    /**
     *
     * @param TokenInfoFactory $tokenInfoFactory
     * @param TokenInfoCollectionFactory $tokenInfoCollectionFactory
     * @param TokenInfoSearchResultsInterfaceFactory $searchResultsFactory
     * @param TokenInfoResource $resource
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(TokenInfoFactory $tokenInfoFactory,
        TokenInfoCollectionFactory $tokenInfoCollectionFactory,
        TokenInfoSearchResultsInterfaceFactory $searchResultsFactory, TokenInfoResource $resource,
        CollectionProcessorInterface $collectionProcessor)
    {
        $this->tokenInfoFactory = $tokenInfoFactory;
        $this->tokenInfoCollectionFactory = $tokenInfoCollectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->resource = $resource;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * Create token info
     *
     * @param TokenInfoInterface $object
     * @return TokenInfo
     * @throws CouldNotSaveException
     */
    public function save(TokenInfoInterface $object)
    {
        try {
            $this->resource->save($object);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(\__('Could not save the token info: %1', $exception->getMessage()),
                $exception);
        }
        return $object;
    }

    /**
     * Get info about token info by entity ID
     *
     * @param int $entityId
     * @return TokenInfo
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function get($entityId)
    {
        if (! $entityId) {
            throw new InputException(\__('ID required'));
        }

        /** @var TokenInfo $object */
        $object = $this->tokenInfoFactory->create();
        $this->resource->load($object, $entityId);
        if (! $object->getEntityId()) {
            throw new NoSuchEntityException(\__('Requested entity does not exist'));
        }
        return $object;
    }

    /**
     * Get info about token info by token ID
     *
     * @param int $spaceId
     * @param int $tokenId
     * @return TokenInfo
     * @throws NoSuchEntityException
     */
    public function getByTokenId($spaceId, $tokenId)
    {
        if (! $spaceId) {
            throw new InputException(\__('Space ID required'));
        }
        if (! $tokenId) {
            throw new InputException(\__('Token ID required'));
        }

        /** @var TokenInfo $object */
        $object = $this->tokenInfoFactory->create();
        $this->resource->loadByToken($object, $spaceId, $tokenId);
        if (! $object->getEntityId()) {
            throw new NoSuchEntityException(\__('Requested entity does not exist'));
        }
        return $object;
    }

    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        /** @var \Wallee\Payment\Model\ResourceModel\TokenInfo\Collection $collection */
        $collection = $this->tokenInfoCollectionFactory->create();

        $this->collectionProcessor->process($searchCriteria, $collection);

        /** @var \Wallee\Payment\Api\Data\TokenInfoSearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    public function delete(TokenInfoInterface $object)
    {
        try {
            $this->resource->delete($object);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(\__('Could not delete the token info: %1', $exception->getMessage()));
        }
        return true;
    }

    public function deleteByIdentifier($entityId)
    {
        $this->delete($this->get($entityId));
    }
}