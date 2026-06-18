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
 * Stub base used when Magento_GiftCardAccount module is not present.
 * Wallee\Payment\Compat\GiftCardAccountBase is aliased
 * to this class so that GiftCardAccountWrapper can be declared and reflected
 * during DI compilation without fatal errors when GiftCardAccountManagement
 * from Magento_GiftCardAccount is not isntalled.
 */
class GiftCardAccountFallback
{
}
