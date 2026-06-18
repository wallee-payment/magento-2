<?php
/**
 * wallee Magento 2
 *
 * This Magento 2 extension enables to process payments with wallee (https://www.wallee.com).
 *
 * @package Wallee_Payment
 * @author wallee AG (https://www.wallee.com)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)

 */
namespace Wallee\Payment\Compat;

/**
 * Stub base used when Hyvä Checkout module is not present.
 * Wallee\Payment\Compat\PlaceOrderServiceBase is aliased
 * to this class so that PlaceOrderService can be declared and reflected
 * during DI compilation without a fatal errors when AbstractPlaceOrderService
 * from Hyvä Checkout is not isntalled.
 */
class PlaceOrderServiceFallback
{
}
