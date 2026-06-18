<?php

namespace Wallee\Payment\Model\Service\Quote;

use \Wallee\Payment\Compat\GiftCardAccountBase;

/**
 * Wrapper around GiftCardAccountManagement from Magento_GiftCardAccount.
 *
 * This class extends GiftCardAccountBase, which is aliased during registration.php
 * to GiftCardAccountManagement when Magento_GiftCardAccount is installed, and to an empty stub
 * (GiftCardAccountFallback) otherwise.
 *
 * The class GiftCardAccountManagement is provided by giftcardaccount module, which is present
 * in cloud versions of Magento, but not in the community version.
 */
class GiftCardAccountWrapper extends GiftCardAccountBase
{
}
