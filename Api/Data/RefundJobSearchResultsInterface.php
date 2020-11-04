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
 * Interface for wallee refund job search results.
 *
 * @api
 */
interface RefundJobSearchResultsInterface extends SearchResultsInterface
{

    /**
     * Get refund jobs list.
     *
     * @return \Wallee\Payment\Api\Data\RefundJobInterface[]
     */
    public function getItems();

    /**
     * Set refund jobs list.
     *
     * @param \Wallee\Payment\Api\Data\RefundJobInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}