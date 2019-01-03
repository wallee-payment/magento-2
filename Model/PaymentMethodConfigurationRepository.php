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
use Wallee\Payment\Api\PaymentMethodConfigurationRepositoryInterface;
use Wallee\Payment\Api\Data\PaymentMethodConfigurationInterface;
use Wallee\Payment\Api\Data\PaymentMethodConfigurationSearchResultsInterfaceFactory;
use Wallee\Payment\Model\ResourceModel\PaymentMethodConfiguration as PaymentMethodConfigurationResource;
use Wallee\Payment\Model\ResourceModel\PaymentMethodConfiguration\CollectionFactory as PaymentMethodConfigurationCollectionFactory;

/**
 * Payment method configuration CRUD service.
 */
class PaymentMethodConfigurationRepository implements PaymentMethodConfigurationRepositoryInterface
{

    /**
     *
     * @var PaymentMethodConfigurationFactory
     */
    private $paymentMethodConfigurationFactory;

    /**
     *
     * @var PaymentMethodConfigurationCollectionFactory
     */
    private $paymentMethodConfigurationCollectionFactory;

    /**
     *
     * @var PaymentMethodConfigurationSearchResultsInterfaceFactory
     */
    private $searchResultsFactory;

    /**
     *
     * @var PaymentMethodConfigurationResource
     */
    private $resource;

    /**
     *
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;

    /**
     *
     * @param PaymentMethodConfigurationFactory $paymentMethodConfigurationFactory
     * @param PaymentMethodConfigurationCollectionFactory $paymentMethodConfigurationCollectionFactory
     * @param PaymentMethodConfigurationSearchResultsInterfaceFactory $searchResultsFactory
     * @param PaymentMethodConfigurationResource $resource
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(PaymentMethodConfigurationFactory $paymentMethodConfigurationFactory,
        PaymentMethodConfigurationCollectionFactory $paymentMethodConfigurationCollectionFactory,
        PaymentMethodConfigurationSearchResultsInterfaceFactory $searchResultsFactory,
        PaymentMethodConfigurationResource $resource, CollectionProcessorInterface $collectionProcessor)
    {
        $this->paymentMethodConfigurationFactory = $paymentMethodConfigurationFactory;
        $this->paymentMethodConfigurationCollectionFactory = $paymentMethodConfigurationCollectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->resource = $resource;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * Create payment method configuration
     *
     * @param PaymentMethodConfigurationInterface $object
     * @return PaymentMethodConfiguration
     * @throws CouldNotSaveException
     */
    public function save(PaymentMethodConfigurationInterface $object)
    {
        try {
            $this->resource->save($object);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                \__('Could not save the payment method configuration: %1', $exception->getMessage()), $exception);
        }
        return $object;
    }

    /**
     * Get info about payment method configuration by entity ID
     *
     * @param int $entityId
     * @return PaymentMethodConfiguration
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function get($entityId)
    {
        if (! $entityId) {
            throw new InputException(\__('ID required'));
        }

        /** @var PaymentMethodConfiguration $object */
        $object = $this->paymentMethodConfigurationFactory->create();
        $this->resource->load($object, $entityId);
        if (! $object->getEntityId()) {
            throw new NoSuchEntityException(\__('Requested entity does not exist'));
        }
        return $object;
    }

    /**
     * Get info about payment method configuration by configuration ID
     *
     * @param int $spaceId
     * @param int $configurationId
     * @return PaymentMethodConfiguration
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getByConfigurationId($spaceId, $configurationId)
    {
        if (! $spaceId) {
            throw new InputException(\__('Space ID required'));
        }
        if (! $configurationId) {
            throw new InputException(\__('Configuration ID required'));
        }

        /** @var PaymentMethodConfiguration $object */
        $object = $this->paymentMethodConfigurationFactory->create();
        $this->resource->loadByConfigurationId($object, $spaceId, $configurationId);
        if (! $object->getEntityId()) {
            throw new NoSuchEntityException(\__('Requested entity does not exist'));
        }
        return $object;
    }

    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        /** @var \Wallee\Payment\Model\ResourceModel\PaymentMethodConfiguration\Collection $collection */
        $collection = $this->paymentMethodConfigurationCollectionFactory->create();

        $this->collectionProcessor->process($searchCriteria, $collection);

        /** @var \Wallee\Payment\Api\Data\PaymentMethodConfigurationSearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    public function delete(PaymentMethodConfigurationInterface $object)
    {
        try {
            $this->resource->delete($object);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                \__('Could not delete the payment method configuration: %1', $exception->getMessage()));
        }
        return true;
    }

    public function deleteByIdentifier($entityId)
    {
        $this->delete($this->get($entityId));
    }
}