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

/**
 * Helper to provide line item related functionality.
 */
class LineItem extends AbstractHelper
{

    /**
     *
     * @var Data
     */
    protected $_helper;

    /**
     *
     * @param Context $context
     * @param Data $helper
     */
    public function __construct(Context $context, Data $helper)
    {
        parent::__construct($context);
        $this->_helper = $helper;
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
     * Checks whether the given line items' total amount matches the expected amount and ensures the uniqueness of the
     * unique IDs.
     *
     * @param \Wallee\Sdk\Model\LineItemCreate[] $items
     * @param float $expectedAmount
     * @param string $currencyCode
     * @return \Wallee\Sdk\Model\LineItemCreate[]
     */
    public function correctLineItems(array $items, $expectedAmount, $currencyCode)
    {
        $effectiveAmount = $this->_helper->roundAmount($this->getTotalAmountIncludingTax($items), $currencyCode);
        $difference = $this->_helper->roundAmount($expectedAmount, $currencyCode) - $effectiveAmount;
        if ($difference != 0) {
            throw new \Exception(
                'The line item total amount of ' . $effectiveAmount . ' does not match the expected amount of ' .
                $expectedAmount . '.');
        }
        return $this->ensureUniqueIds($items);
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
     * @throws \Exception
     * @return \Wallee\Sdk\Model\LineItemCreate[]
     */
    public function reduceAmount(array $items, $expectedAmount)
    {
        if (empty($items)) {
            throw new \Exception("No line items provided.");
        }

        $effectiveAmount = $this->getTotalAmountIncludingTax($items);
        $factor = $expectedAmount / $effectiveAmount;

        $appliedAmount = 0;
        foreach ($items as $item) {
            if ($item->getUniqueId() != 'shipping') {
                $item->setAmountIncludingTax($item->getAmountIncludingTax() * $factor);
            }
            $appliedAmount += $item->getAmountIncludingTax();
        }

        $roundingDifference = $expectedAmount - $appliedAmount;
        $items[0]->setAmountIncludingTax($items[0]->getAmountIncludingTax() + $roundingDifference);

        return $this->ensureUniqueIds($items);
    }
}