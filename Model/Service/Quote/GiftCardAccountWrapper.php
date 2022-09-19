<?php

namespace Wallee\Payment\Model\Service\Quote;

/**
 * Defines the class GiftCardAccountWrapper.
 * 
 * This class extends the GiftCardAccountManagement class if it exists. It is and
 * empty class in other case.
 * 
 * The class GiftCardAccountManagement is provided by giftcardaccount module, which is present
 * in cloud versions of Magento, but not in the community version.
 */
if (class_exists('\Magento\GiftCardAccount\Model\Service\GiftCardAccountManagement')) {
    class GiftCardAccountWrapper extends \Magento\GiftCardAccount\Model\Service\GiftCardAccountManagement {}
} else {
    class GiftCardAccountWrapper {}
}
