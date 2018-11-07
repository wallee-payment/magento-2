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
namespace Wallee\Payment\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Wallee\Payment\Api\Data\PaymentMethodConfigurationInterface;

/**
 * Payment method configuration CRUD interface.
 *
 * @api
 */
interface PaymentMethodConfigurationRepositoryInterface
{

    /**
     * Create payment method configuration
     *
     * @param PaymentMethodConfigurationInterface $object
     * @return PaymentMethodConfigurationInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(PaymentMethodConfigurationInterface $object);

    /**
     * Get info about payment method configuration by entity ID
     *
     * @param int $entityId
     * @return PaymentMethodConfigurationInterface
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function get($entityId);

    /**
     * Get info about payment method configuration by configuration ID
     *
     * @param int $spaceId
     * @param int $configurationId
     * @return PaymentMethodConfigurationInterface
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getByConfigurationId($spaceId, $configurationId);

    /**
     * Retrieve payment method configurations matching the specified criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return \Wallee\Payment\Api\Data\PaymentMethodConfigurationSearchResultsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getList(SearchCriteriaInterface $searchCriteria);

    /**
     * Delete payment method configuration
     *
     * @param PaymentMethodConfigurationInterface $object
     * @return bool Will returned True if deleted
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(PaymentMethodConfigurationInterface $object);

    /**
     * Delete payment method configuration by identifier
     *
     * @param string $entityId
     * @return bool Will returned True if deleted
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteByIdentifier($entityId);
}