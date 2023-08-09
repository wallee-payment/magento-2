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
namespace Wallee\Payment\Api;

use Magento\Sales\Api\Data\OrderInterface;

/**
 * Interface for wallee order data.
 *
 * @api
 */
interface OrderRepositoryInterface
{

	/**
	 * Get order by Order Increment Id
	 *
	 * @param $incrementId
	 * @return OrderInterface|null
	 */
    public function getOrderByIncrementId($incrementId);

	/**
	 * Get order by Id
	 *
	 * @param $id
	 * @return OrderInterface|null
	 */
    public function getOrderById($id);
}
