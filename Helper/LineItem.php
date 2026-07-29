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
namespace Wallee\Payment\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Wallee\PluginCore\LineItem\LineItem as CoreLineItem;
use Wallee\PluginCore\LineItem\Exception\LineItemConsistencyException;
use Wallee\PluginCore\LineItem\LineItemConsistencyService;
use Wallee\PluginCore\LineItem\LineItemProrationService;
use Magento\Framework\Exception\LocalizedException;

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
     * @var LineItemConsistencyService
     */
    private $lineItemConsistencyService;

    /**
     *
     * @var LineItemProrationService
     */
    private $lineItemProrationService;

    /**
     *
     * @param Context $context
     * @param Data $helper
     * @param LineItemConsistencyService $lineItemConsistencyService
     * @param LineItemProrationService $lineItemProrationService
     */
    public function __construct(
        Context $context,
        Data $helper,
        LineItemConsistencyService $lineItemConsistencyService,
        LineItemProrationService $lineItemProrationService
    ) {
        parent::__construct($context);
        $this->helper = $helper;
        $this->lineItemConsistencyService = $lineItemConsistencyService;
        $this->lineItemProrationService = $lineItemProrationService;
    }

    /**
     * Gets the total amount including tax of the given line items.
     *
     * @param CoreLineItem[] $items
     * @return float
     */
    public function getTotalAmountIncludingTax(array $items)
    {
        $sum = 0;
        foreach ($items as $item) {
            $sum += $item->amountIncludingTax;
        }
        return $sum;
    }

    /**
     * Reconciles line item totals against the expected amount via plugin-core's
     * LineItemConsistencyService (which appends a tax-free "Rounding Adjustment"
     * item when needed, subject to its own 10-cent safety threshold and the
     * shop's consistency-enforcement setting), then ensures unique IDs.
     *
     * @param CoreLineItem[] $items
     * @param float $expectedAmount
     * @param string $currencyCode
     * @return CoreLineItem[]
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function correctLineItems(array $items, $expectedAmount, $currencyCode)
    {
        try {
            $items = $this->lineItemConsistencyService->ensureConsistency(
                $items,
                (float) $expectedAmount,
                (string) $currencyCode
            )->all();
        } catch (LineItemConsistencyException $e) {
            throw new LocalizedException(\__($e->getLocalizedMessage()->getDefault()));
        }
        return $this->ensureUniqueIds($items);
    }

    /**
     * Ensures the uniqueness of the given line items.
     *
     * @param CoreLineItem[] $items
     * @return CoreLineItem[]
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function ensureUniqueIds(array $items)
    {
        $uniqueIds = [];
        foreach ($items as $item) {
            $uniqueId = $item->uniqueId;
            if (empty($uniqueId)) {
                $uniqueId = preg_replace("/[^a-z0-9]/", '', \strtolower($item->sku));
            }

            if (empty($uniqueId)) {
                throw new LocalizedException(\__('There is a line item without a unique ID.'));
            }

            if (isset($uniqueIds[$uniqueId])) {
                $backup = $uniqueId;
                $uniqueId = $uniqueId . '_' . $uniqueIds[$uniqueId];
                $uniqueIds[$backup] ++;
            } else {
                $uniqueIds[$uniqueId] = 1;
            }

            $item->uniqueId = $uniqueId;
        }
        return $items;
    }

    /**
     * Reduces the amounts of the given line items proportionally to match the given expected
     * amount, via plugin-core's LineItemProrationService. The 'shipping' line item, if present,
     * is excluded from proration and merged back in unchanged.
     *
     * @param CoreLineItem[] $items
     * @param float $expectedAmount
     * @param string $currencyCode
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return CoreLineItem[]
     */
    public function reduceAmount(array $items, $expectedAmount, $currencyCode)
    {
        if (empty($items)) {
            throw new LocalizedException(\__('No line items provided.'));
        }

        $shippingAmount = 0.0;
        $itemsToScale = [];
        foreach ($items as $item) {
            if ($item->uniqueId == 'shipping') {
                $shippingAmount += $item->amountIncludingTax;
            } else {
                $itemsToScale[] = $item;
            }
        }

        $scaledItems = $itemsToScale !== []
            ? $this->lineItemProrationService->scaleItems(
                $itemsToScale,
                (float) $expectedAmount - $shippingAmount,
                (string) $currencyCode
            )
            : [];

        $result = [];
        $scaledIndex = 0;
        foreach ($items as $item) {
            $result[] = $item->uniqueId == 'shipping' ? $item : $scaledItems[$scaledIndex++];
        }

        return $this->ensureUniqueIds($result);
    }

    /**
     * Creates a Line Item specifically for a gift card.
     *
     * @param string $giftCardCode
     * @param float $giftCardAmount
     * @param string $currencyCode
     * @return CoreLineItem
     */
    public function createGiftCardLineItem(string $giftCardCode, float $giftCardAmount, string $currencyCode)
    {
        $giftCardLineItem = new CoreLineItem();
        $giftCardLineItem->amountIncludingTax = -(float) $this->helper->roundAmount($giftCardAmount, $currencyCode);
        $giftCardLineItem->unitPriceIncludingTax = $giftCardLineItem->amountIncludingTax;
        $giftCardLineItem->name = 'Gift card: ' . $giftCardCode;
        $giftCardLineItem->quantity = 1.0;
        $giftCardLineItem->sku = $giftCardCode;
        $giftCardLineItem->uniqueId = $giftCardCode;
        $giftCardLineItem->shippingRequired = false;
        $giftCardLineItem->type = CoreLineItem::TYPE_DISCOUNT;

        return $giftCardLineItem;
    }
}
