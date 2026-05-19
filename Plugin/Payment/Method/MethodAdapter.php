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
namespace Wallee\Payment\Plugin\Payment\Method;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Prevents the vendor PostFinance Adapter from calling getPossiblePaymentMethods()
 * (and thereby updateTransactionByQuote) against an already-placed order's quote.
 * In Hyvä checkout, Magewire re-renders the payment method list after placeOrder(),
 * triggering isAvailable() while the quote is already inactive.
 */
class MethodAdapter
{
    /**
     * @param \Wallee\Payment\Model\Payment\Method\Adapter $subject
     * @param callable $proceed
     * @param CartInterface|null $quote
     * @return bool
     */
    public function aroundIsAvailable(
        \Wallee\Payment\Model\Payment\Method\Adapter $subject,
        callable $proceed,
        CartInterface $quote = null
    ): bool {
        if ($quote !== null && !$quote->getIsActive()) {
            return false;
        }
        return $proceed($quote);
    }
}
