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
namespace Wallee\Payment\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Interface for wallee transaction info search results.
 *
 * @api
 */
interface TransactionInfoSearchResultsInterface extends SearchResultsInterface
{

    /**
     * Get transaction infos list.
     *
     * @return \Wallee\Payment\Api\Data\TransactionInfoInterface[]
     */
    public function getItems();

    /**
     * Set transaction infos list.
     *
     * @param \Wallee\Payment\Api\Data\TransactionInfoInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}