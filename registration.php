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
\Magento\Framework\Component\ComponentRegistrar::register(
    \Magento\Framework\Component\ComponentRegistrar::MODULE,
    'Wallee_Payment',
    __DIR__
);

/**
 * Compatibility aliases for optional dependencies.
 *
 * Two of our classes extend classes from external modules which may not always
 * be installed:
 *  - GiftCardAccountWrapper extends GiftCardAccountManagement from Magento_GiftCardAccount
 *  - PlaceOrderService extends AbstractPlaceOrderService from Hyvä Checkout
 *
 * To avoid fatal errors when those classes are missing, we point a base
 * alias at either the real class or a local stub (fallback), depending on
 * what is available.
 *
 * This runs from registration.php so the aliases are defined before Magento
 * compiles the DI graph and reflects the dependent classes.
 */

if (!\class_exists(\Wallee\Payment\Compat\GiftCardAccountBase::class, false)) {
    \class_alias(
        \class_exists(\Magento\GiftCardAccount\Model\Service\GiftCardAccountManagement::class)
            ? \Magento\GiftCardAccount\Model\Service\GiftCardAccountManagement::class
            : \Wallee\Payment\Compat\GiftCardAccountFallback::class,
        \Wallee\Payment\Compat\GiftCardAccountBase::class
    );
}

if (!\class_exists(\Wallee\Payment\Compat\PlaceOrderServiceBase::class, false)) {
    \class_alias(
        \class_exists(Hyva\Checkout\Model\Magewire\Payment\AbstractPlaceOrderService::class)
            ? Hyva\Checkout\Model\Magewire\Payment\AbstractPlaceOrderService::class
            : \Wallee\Payment\Compat\PlaceOrderServiceFallback::class,
        \Wallee\Payment\Compat\PlaceOrderServiceBase::class
    );
}
