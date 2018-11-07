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
use Wallee\Payment\Api\Data\TokenInfoInterface;

/**
 * Token info CRUD interface.
 *
 * @api
 */
interface TokenInfoRepositoryInterface
{

    /**
     * Create token info
     *
     * @param TokenInfoInterface $object
     * @return TokenInfoInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(TokenInfoInterface $object);

    /**
     * Get info about token info by entity ID
     *
     * @param int $entityId
     * @return TokenInfoInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function get($entityId);

    /**
     * Get info about token info by token ID
     *
     * @param int $spaceId
     * @param int $tokenId
     * @return TokenInfoInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getByTokenId($spaceId, $tokenId);

    /**
     * Retrieve token infos matching the specified criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return \Wallee\Payment\Api\Data\TokenInfoSearchResultsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getList(SearchCriteriaInterface $searchCriteria);

    /**
     * Delete token info
     *
     * @param TokenInfoInterface $object
     * @return bool Will returned True if deleted
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\StateException
     */
    public function delete(TokenInfoInterface $object);

    /**
     * Delete token info by identifier
     *
     * @param string $entityId
     * @return bool Will returned True if deleted
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\StateException
     */
    public function deleteByIdentifier($entityId);
}