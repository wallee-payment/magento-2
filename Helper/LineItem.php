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
namespace Wallee\Payment\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Wallee\Sdk\Model\LineItemCreate;
use Wallee\Sdk\Model\LineItemType;
use Wallee\Sdk\Model\TaxCreate;

/**
 * Helper to provide line item related functionality.
 */
class LineItem extends AbstractHelper
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
     * Gets the total amount including tax of the given line items.
     *
     * @param \Wallee\Sdk\Model\LineItem[] $items
     * @return float
     */
    public function getTotalAmountIncludingTax(array $items)
    {
        $sum = 0;
        foreach ($items as $item) {
            $sum += $item->getAmountIncludingTax();
        }
        return $sum;
    }

    /**
     * Returns the total tax amount of the given line items.
     *
     * @param LineItem[] $lineItems
     * @param string $currency
     * @return float
     */
    public function getTotalTaxAmount(array $lineItems, $currency)
    {
        $sum = 0;
        foreach ($lineItems as $lineItem) {
            $aggregatedTaxRate = 0;
            if (\is_array($lineItem->getTaxes())) {
                foreach ($lineItem->getTaxes() as $tax) {
                    $aggregatedTaxRate += $tax->getRate();
                }
            }
            $amountExcludingTax = $this->helper->roundAmount(
                $lineItem->getAmountIncludingTax() / (1 + $aggregatedTaxRate / 100), $currency);
            $sum += $lineItem->getAmountIncludingTax() - $amountExcludingTax;
        }

        return $sum;
    }

    /**
     * Checks whether the given line items' total amount matches the expected amount and ensures the uniqueness of the
     * unique IDs.
     *
     * @param \Wallee\Sdk\Model\LineItemCreate[] $items
     * @param float $expectedAmount
     * @param string $currencyCode
     * @param boolean $ensureConsistency
     * @param array $taxInfo
     * @return \Wallee\Sdk\Model\LineItemCreate[]
     */
    public function correctLineItems(array $items, $expectedAmount, $currencyCode, $ensureConsistency = true,
        array $taxInfo = [])
    {
        $expectedAmount = $this->helper->roundAmount($expectedAmount, $currencyCode);
        $effectiveAmount = $this->helper->roundAmount($this->getTotalAmountIncludingTax($items), $currencyCode);
        $difference = $expectedAmount - $effectiveAmount;
        if ($difference != 0) {
            if ($ensureConsistency) {
                throw new \Exception(
                    'The line item total amount of ' . $effectiveAmount . ' does not match the expected amount of ' .
                    $expectedAmount . '.');
            } else {
                $this->adjustLineItems($items, $expectedAmount, $currencyCode, $taxInfo);
            }
        }
        return $this->ensureUniqueIds($items);
    }

    /**
     *
     * @param \Wallee\Sdk\Model\LineItemCreate[] $items
     * @param float $expectedAmount
     * @param string $currencyCode
     * @param array $taxInfo
     */
    protected function adjustLineItems(array &$items, $expectedAmount, $currencyCode, array $taxInfo)
    {
        $effectiveAmount = $this->helper->roundAmount($this->getTotalAmountIncludingTax($items), $currencyCode);
        $difference = $expectedAmount - $effectiveAmount;

        $adjustmentLineItem = new LineItemCreate();
        $adjustmentLineItem->setAmountIncludingTax($this->helper->roundAmount($difference, $currencyCode));
        $adjustmentLineItem->setName((string) \__('Adjustment'));
        $adjustmentLineItem->setQuantity(1);
        $adjustmentLineItem->setSku('adjustment');
        $adjustmentLineItem->setUniqueId('adjustment');
        $adjustmentLineItem->setShippingRequired(false);
        $adjustmentLineItem->setType($difference > 0 ? LineItemType::FEE : LineItemType::DISCOUNT);

        if (! empty($taxInfo) && \count($taxInfo) == 1) {
            $taxAmount = $this->getTotalTaxAmount($items, $currencyCode);
            $taxDifference = $this->helper->roundAmount($taxInfo[0]['tax_amount'] - $taxAmount, $currencyCode);
            if ($taxDifference != 0) {
                $rate = $taxInfo[0]['percent'];
                $adjustmentTaxAmount = $this->helper->roundAmount($difference - $difference / (1 + $rate / 100),
                    $currencyCode);
                if ($adjustmentTaxAmount == $taxDifference) {
                    $tax = new TaxCreate();
                    $tax->setRate($rate);
                    $tax->setTitle($this->helper->fixLength($taxInfo[0]['title'], 40));
                    $adjustmentLineItem->setTaxes([
                        $tax
                    ]);
                }
            }
        }

        $items[] = $adjustmentLineItem;
    }

    /**
     * Ensures the uniqueness of the given line items.
     *
     * @param \Wallee\Sdk\Model\LineItemCreate[] $items
     * @return \Wallee\Sdk\Model\LineItemCreate[]
     */
    public function ensureUniqueIds(array $items)
    {
        $uniqueIds = [];
        foreach ($items as $item) {
            $uniqueId = $item->getUniqueId();
            if (empty($uniqueId)) {
                $uniqueId = preg_replace("/[^a-z0-9]/", '', \strtolower($item->getSku()));
            }

            if (empty($uniqueId)) {
                throw new \Exception("There is a line item without a unique ID.");
            }

            if (isset($uniqueIds[$uniqueId])) {
                $backup = $uniqueId;
                $uniqueId = $uniqueId . '_' . $uniqueIds[$uniqueId];
                $uniqueIds[$backup] ++;
            } else {
                $uniqueIds[$uniqueId] = 1;
            }

            $item->setUniqueId($uniqueId);
        }
        return $items;
    }

    /**
     * Reduces the amounts of the given line items proportionally to match the given expected amount.
     *
     * @param \Wallee\Sdk\Model\LineItemCreate[] $items
     * @param float $expectedAmount
     * @param string $currencyCode
     * @throws \Exception
     * @return \Wallee\Sdk\Model\LineItemCreate[]
     */
    public function reduceAmount(array $items, $expectedAmount, $currencyCode)
    {
        if (empty($items)) {
            throw new \Exception("No line items provided.");
        }

        $effectiveAmount = $this->getTotalAmountIncludingTax($items);
        $factor = $expectedAmount / $effectiveAmount;

        $appliedAmount = 0;
        foreach ($items as $item) {
            if ($item->getUniqueId() != 'shipping') {
                $item->setAmountIncludingTax(
                    $this->helper->roundAmount($item->getAmountIncludingTax() * $factor, $currencyCode));
            }
            $appliedAmount += $item->getAmountIncludingTax();
        }

        $roundingDifference = $expectedAmount - $appliedAmount;
        $items[0]->setAmountIncludingTax(
            $this->helper->roundAmount($items[0]->getAmountIncludingTax() + $roundingDifference, $currencyCode));

        return $this->ensureUniqueIds($items);
    }
}