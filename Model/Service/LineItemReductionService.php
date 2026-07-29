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
namespace Wallee\Payment\Model\Service;

use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Wallee\Payment\Helper\Data as Helper;
use Wallee\Payment\Helper\LineItemReduction as LineItemReductionHelper;
use Wallee\PluginCore\Currency\CurrencyRoundingService;
use Wallee\PluginCore\Refund\RefundService as CoreRefundService;

/**
 * Service to handle line item reductions.
 */
class LineItemReductionService
{

    /**
     *
     * @var Helper
     */
    private $helper;

    /**
     *
     * @var LineItemReductionHelper
     */
    private $reductionHelper;

    /**
     *
     * @var CoreRefundService
     */
    private $refundService;

    /**
     *
     * @var EventManagerInterface
     */
    private $eventManager;

    /**
     *
     * @param Helper $helper
     * @param LineItemReductionHelper $reductionHelper
     * @param CoreRefundService $refundService
     * @param EventManagerInterface $eventManager
     */
    public function __construct(
        Helper $helper,
        LineItemReductionHelper $reductionHelper,
        CoreRefundService $refundService,
        EventManagerInterface $eventManager
    ) {
        $this->helper = $helper;
        $this->reductionHelper = $reductionHelper;
        $this->refundService = $refundService;
        $this->eventManager = $eventManager;
    }

    /**
     * Converts the creditmemo's items to line item reductions.
     *
     * The result is mapped by the caller into
     * Wallee\PluginCore\Refund\LineItem\RefundLineItem entries
     * (uniqueId, returnedQuantity, unitPriceReduction) for RefundContext::$lineItems.
     *
     * @param Creditmemo $creditmemo
     * @return list<array{uniqueId: ?string, quantity: float, amount: float}>
     * @throws LineItemReductionException
     */
    public function convertCreditmemo(Creditmemo $creditmemo): array
    {
        $order = $creditmemo->getOrder();
        $baseLineItems = $this->getBaseLineItems(
            (int) $order->getWalleeSpaceId(),
            (int) $order->getWalleeTransactionId()
        );

        $unrefundedAmount = 0.0;
        foreach ($baseLineItems as $baseLineItem) {
            $unrefundedAmount += $baseLineItem->amountIncludingTax;
        }

        $reductions = [];
        if (CurrencyRoundingService::areAmountsEqual(
            $unrefundedAmount,
            $creditmemo->getGrandTotal(),
            $creditmemo->getOrderCurrencyCode()
        )) {
            foreach ($baseLineItems as $baseLineItem) {
                $reductions[] = $this->makeReduction($baseLineItem->uniqueId, (float) $baseLineItem->quantity, 0.0);
            }
        } else {
            foreach ($creditmemo->getAllItems() as $creditmemoItem) {
                if ($this->isIncludeItem($creditmemoItem)) {
                    $reductions[] = $this->convertItem($creditmemoItem, $creditmemo);
                }
            }

            $shippingReduction = $this->convertShipping($creditmemo);
            if ($shippingReduction !== null) {
                $reductions[] = $shippingReduction;
            }
        }

        $transport = new DataObject([
            'items' => $reductions
        ]);
        $this->eventManager->dispatch(
            'wallee_payment_convert_line_item_reductions',
            [
                'transport' => $transport,
                'creditmemo' => $creditmemo,
                'baseLineItems' => $baseLineItems
            ]
        );
        $reductions = $transport->getData('items');
        $this->validateReductions($reductions, $creditmemo, $baseLineItems);
        return $this->mapReductionsToRefundContextLineItems($reductions);
    }

    /**
     * Builds a single reduction in the internal array shape shared with event observers.
     *
     * @param string|null $uniqueId
     * @param float $quantityReduction
     * @param float $unitPriceReduction
     * @return array{uniqueId: ?string, quantityReduction: float, unitPriceReduction: float}
     */
    private function makeReduction(?string $uniqueId, float $quantityReduction, float $unitPriceReduction): array
    {
        return [
            'uniqueId' => $uniqueId,
            'quantityReduction' => $quantityReduction,
            'unitPriceReduction' => $unitPriceReduction,
        ];
    }

    /**
     * Maps the internal reduction arrays to the plain array shape the caller maps into
     * RefundLineItem entries, where 'amount' is the per-remaining-unit price reduction
     * (not the total reduced amount).
     *
     * @param array $reductions Each entry: ['uniqueId'=>?string,'quantityReduction'=>float,'unitPriceReduction'=>float]
     * @return list<array{uniqueId: ?string, quantity: float, amount: float}>
     */
    private function mapReductionsToRefundContextLineItems(array $reductions): array
    {
        return array_map(
            static function (array $reduction): array {
                return [
                    'uniqueId' => $reduction['uniqueId'],
                    'quantity' => (float) $reduction['quantityReduction'],
                    'amount' => (float) $reduction['unitPriceReduction'],
                ];
            },
            $reductions
        );
    }

    /**
     * Gets whether the given creditmemo item is to be included in the line item reductions.
     *
     * @param Creditmemo\Item $creditmemoItem
     * @return boolean
     */
    private function isIncludeItem(Creditmemo\Item $creditmemoItem)
    {
        if ($creditmemoItem->getOrderItem()->getParentItemId() != null &&
            $creditmemoItem->getOrderItem()
                ->getParentItem()
                ->getProductType() == \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
            return false;
        }

        if ($creditmemoItem->getOrderItem()->getProductType() == \Magento\Catalog\Model\Product\Type::TYPE_BUNDLE &&
            $creditmemoItem->getOrderItem()->getParentItemId() == null &&
            $creditmemoItem->getOrderItem()
                ->getProduct()
                ->getPriceType() != \Magento\Bundle\Model\Product\Price::PRICE_TYPE_FIXED) {
            return false;
        }

        return true;
    }

    /**
     * Converts the given creditmemo item to a line item reduction.
     *
     * @param Creditmemo\Item $creditmemoItem
     * @param Creditmemo $creditmemo
     * @return array{uniqueId: ?string, quantityReduction: float, unitPriceReduction: float}
     */
    private function convertItem(Creditmemo\Item $creditmemoItem, Creditmemo $creditmemo)
    {
        return $this->makeReduction(
            $creditmemoItem->getOrderItem()->getQuoteItemId(),
            (float) $creditmemoItem->getQty(),
            0.0
        );
    }

    /**
     * Converts the creditmemo's shipping information to a line item reduction.
     *
     * @param Creditmemo $creditmemo
     * @return array{uniqueId: ?string, quantityReduction: float, unitPriceReduction: float}|null
     */
    private function convertShipping(Creditmemo $creditmemo)
    {
        if ($creditmemo->getShippingAmount() > 0) {
            if (CurrencyRoundingService::areAmountsEqual(
                $creditmemo->getShippingAmount() + $creditmemo->getShippingTaxAmount(),
                $creditmemo->getOrder()
                ->getShippingInclTax(),
                $creditmemo->getOrderCurrencyCode()
            )) {
                return $this->makeReduction('shipping', 1.0, 0.0);
            }

            return $this->makeReduction(
                'shipping',
                0.0,
                $this->helper->roundAmount(
                    $creditmemo->getShippingAmount() + $creditmemo->getShippingTaxAmount(),
                    $creditmemo->getOrderCurrencyCode()
                )
            );
        }

        return null;
    }

    /**
     * Validates whether the given reductions total amount matches the one of the creditmemo.
     *
     * @param array $reductions Each entry: ['uniqueId'=>?string,'quantityReduction'=>float,'unitPriceReduction'=>float]
     * @param Creditmemo $creditmemo
     * @param \Wallee\PluginCore\LineItem\LineItem[] $baseLineItems
     * @return void
     * @throws LineItemReductionException
     */
    private function validateReductions(array $reductions, Creditmemo $creditmemo, $baseLineItems)
    {
        $reducedAmount = $this->reductionHelper->getReducedAmount(
            $baseLineItems,
            $reductions,
            $creditmemo->getOrderCurrencyCode()
        );
        if (!CurrencyRoundingService::areAmountsEqual(
            $reducedAmount,
            $creditmemo->getGrandTotal(),
            $creditmemo->getOrderCurrencyCode()
        )) {
            throw new LineItemReductionException();
        }
    }

    /**
     * Gets the line items that are still refundable and used to calculate the refund.
     *
     * Delegates to plugin-core, which resolves these via its Invoice domain: the latest successful
     * refund's reduced line items, or the transaction invoice's line items when no refund exists yet.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @return \Wallee\PluginCore\LineItem\LineItem[]
     */
    private function getBaseLineItems(int $spaceId, int $transactionId): array
    {
        return iterator_to_array(
            $this->refundService->getRefundableLineItems($spaceId, $transactionId)
        );
    }
}
