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
namespace Wallee\Payment\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;

/**
 * Helper to provide line item reduction related functionality.
 */
class LineItemReduction extends AbstractHelper
{

    /**
     *
     * @var Data
     */
    private $helper;

    /**
     *
     * @param Context $context
     * @param Data $helper
     */
    public function __construct(Context $context, Data $helper)
    {
        parent::__construct($context);
        $this->helper = $helper;
    }

    /**
     * Gets the amount of the line item's reductions.
     *
     * @param \Wallee\Sdk\Model\LineItem[] $lineItems
     * @param \Wallee\Sdk\Model\LineItemReduction[] $reductions
     * @param string $currency
     */
    public function getReducedAmount(array $lineItems, array $reductions, $currency)
    {
        $lineItemMap = [];
        foreach ($lineItems as $lineItem) {
            $lineItemMap[$lineItem->getUniqueId()] = $lineItem;
        }

        $amount = 0;
        foreach ($reductions as $reduction) {
            if (! isset($lineItemMap[$reduction->getLineItemUniqueId()])) {
                throw new LocalizedException(
                    \__("The refund cannot be executed as the transaction's line items do not match the order's."));
            }

            $lineItem = $lineItemMap[$reduction->getLineItemUniqueId()];
            if ($lineItem->getQuantity() != 0) {
                $unitPrice = $lineItem->getAmountIncludingTax() / $lineItem->getQuantity();
                $amount += $unitPrice * $reduction->getQuantityReduction();
                $amount += $reduction->getUnitPriceReduction() *
                    ($lineItem->getQuantity() - $reduction->getQuantityReduction());
            }
        }

        return $this->helper->roundAmount($amount, $currency);
    }
}