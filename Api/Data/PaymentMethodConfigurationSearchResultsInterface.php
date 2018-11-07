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
namespace Wallee\Payment\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Interface for wallee payment method configuration search results.
 *
 * @api
 */
interface PaymentMethodConfigurationSearchResultsInterface extends SearchResultsInterface
{

    /**
     * Get payment method configurations list.
     *
     * @return \Wallee\Payment\Api\Data\PaymentMethodConfigurationInterface[]
     */
    public function getItems();

    /**
     * Set payment method configurations list.
     *
     * @param \Wallee\Payment\Api\Data\PaymentMethodConfigurationInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}