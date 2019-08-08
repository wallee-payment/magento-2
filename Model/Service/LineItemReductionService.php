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
namespace Wallee\Payment\Model\Service;

use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Creditmemo;
use Wallee\Payment\Helper\Data as Helper;
use Wallee\Payment\Helper\LineItem as LineItemHelper;
use Wallee\Payment\Helper\LineItemReduction as LineItemReductionHelper;
use Wallee\Payment\Model\ApiClient;
use Wallee\Sdk\Model\CriteriaOperator;
use Wallee\Sdk\Model\EntityQuery;
use Wallee\Sdk\Model\EntityQueryFilter;
use Wallee\Sdk\Model\EntityQueryFilterType;
use Wallee\Sdk\Model\EntityQueryOrderByType;
use Wallee\Sdk\Model\LineItemReductionCreate;
use Wallee\Sdk\Model\Refund;
use Wallee\Sdk\Model\RefundState;
use Wallee\Sdk\Model\TransactionInvoiceState;
use Wallee\Sdk\Service\RefundService;
use Wallee\Sdk\Service\TransactionInvoiceService;

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
     * @var LineItemHelper
     */
    private $lineItemHelper;

    /**
     *
     * @var ApiClient
     */
    private $apiClient;

    /**
     *
     * @var EventManagerInterface
     */
    private $eventManager;

    /**
     *
     * @param Helper $helper
     * @param LineItemReductionHelper $reductionHelper
     * @param LineItemHelper $lineItemHelper
     * @param ApiClient $apiClient
     * @param EventManagerInterface $eventManager
     */
    public function __construct(Helper $helper, LineItemReductionHelper $reductionHelper, LineItemHelper $lineItemHelper,
        ApiClient $apiClient, EventManagerInterface $eventManager)
    {
        $this->helper = $helper;
        $this->reductionHelper = $reductionHelper;
        $this->lineItemHelper = $lineItemHelper;
        $this->apiClient = $apiClient;
        $this->eventManager = $eventManager;
    }

    /**
     * Converts the creditmemo's items to line item reductions.
     *
     * @param Creditmemo $creditmemo
     * @return LineItemReductionCreate[]
     */
    public function convertCreditmemo(Creditmemo $creditmemo)
    {
        /** @var \Wallee\Sdk\Model\LineItem[] $baseLineItems */
        $baseLineItems = $this->getBaseLineItems($creditmemo->getOrder()
            ->getWalleeSpaceId(), $creditmemo->getOrder()
            ->getWalleeTransactionId());
        $unrefundedAmount = $this->lineItemHelper->getTotalAmountIncludingTax($baseLineItems);

        $reductions = [];
        if ($this->helper->compareAmounts($unrefundedAmount, $creditmemo->getGrandTotal(),
            $creditmemo->getOrderCurrencyCode()) == 0) {
            foreach ($baseLineItems as $baseLineItem) {
                $reduction = new LineItemReductionCreate();
                $reduction->setLineItemUniqueId($baseLineItem->getUniqueId());
                $reduction->setQuantityReduction($baseLineItem->getQuantity());
                $reduction->setUnitPriceReduction(0);
                $reductions[] = $reduction;
            }
        } else {
            foreach ($creditmemo->getAllItems() as $creditmemoItem) {
                if ($this->isIncludeItem($creditmemoItem)) {
                    $reductions[] = $this->convertItem($creditmemoItem, $creditmemo);
                }
            }

            $shippingReduction = $this->convertShipping($creditmemo);
            if ($shippingReduction instanceof LineItemReductionCreate) {
                $reductions[] = $shippingReduction;
            }
        }

        $transport = new DataObject([
            'items' => $reductions
        ]);
        $this->eventManager->dispatch('wallee_payment_convert_line_item_reductions',
            [
                'transport' => $transport,
                'creditmemo' => $creditmemo,
                'baseLineItems' => $baseLineItems
            ]);
        return $this->fixReductions($transport->getData('items'), $creditmemo, $baseLineItems);
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
     * @return LineItemReductionCreate[]
     */
    private function convertItem(Creditmemo\Item $creditmemoItem, Creditmemo $creditmemo)
    {
        $reduction = new LineItemReductionCreate();
        $reduction->setLineItemUniqueId($creditmemoItem->getOrderItem()
            ->getQuoteItemId());
        $reduction->setQuantityReduction($creditmemoItem->getQty());
        $reduction->setUnitPriceReduction(0);
        return $reduction;
    }

    /**
     * Converts the creditmemo's shipping information to a line item reduction.
     *
     * @param Creditmemo $creditmemo
     * @return LineItemReductionCreate
     */
    private function convertShipping(Creditmemo $creditmemo)
    {
        if ($creditmemo->getShippingAmount() > 0) {
            $reduction = new LineItemReductionCreate();
            $reduction->setLineItemUniqueId('shipping');
            if ($this->helper->compareAmounts($creditmemo->getShippingAmount() + $creditmemo->getShippingTaxAmount(),
                $creditmemo->getOrder()
                    ->getShippingInclTax(), $creditmemo->getOrderCurrencyCode()) == 0) {
                $reduction->setQuantityReduction(1);
                $reduction->setUnitPriceReduction(0);
            } else {
                $reduction->setQuantityReduction(0);
                $reduction->setUnitPriceReduction(
                    $this->helper->roundAmount($creditmemo->getShippingAmount() + $creditmemo->getShippingTaxAmount(),
                        $creditmemo->getOrderCurrencyCode()));
            }
            return $reduction;
        }
    }

    /**
     * Returns the fixed line item reductions for the creditmemo.
     *
     * If the amount of the given reductions does not match the creditmemo's grand total, the amount to refund is
     * distributed equally to the line items.
     *
     * @param LineItemReductionCreate[] $reductions
     * @param Creditmemo $creditmemo
     * @param \Wallee\Sdk\Model\LineItem[] $baseLineItems
     * @return LineItemReductionCreate[]
     */
    private function fixReductions(array $reductions, Creditmemo $creditmemo, $baseLineItems)
    {
        $reducedAmount = $this->reductionHelper->getReducedAmount($baseLineItems, $reductions,
            $creditmemo->getOrderCurrencyCode());
        if ($this->helper->compareAmounts($reducedAmount, $creditmemo->getGrandTotal(),
            $creditmemo->getOrderCurrencyCode()) != 0) {
            $baseAmount = $this->lineItemHelper->getTotalAmountIncludingTax($baseLineItems);
            $rate = $creditmemo->getGrandTotal() / $baseAmount;
            $fixedReductions = [];
            foreach ($baseLineItems as $lineItem) {
                if ($lineItem->getQuantity() > 0) {
                    $reduction = new LineItemReductionCreate();
                    $reduction->setLineItemUniqueId($lineItem->getUniqueId());
                    $reduction->setQuantityReduction(0);
                    $reduction->setUnitPriceReduction(
                        $this->helper->roundAmount(
                            $lineItem->getAmountIncludingTax() * $rate / $lineItem->getQuantity(),
                            $creditmemo->getOrderCurrencyCode()));
                    $fixedReductions[] = $reduction;
                }
            }
            $fixedReductionAmount = $this->reductionHelper->getReducedAmount($baseLineItems, $fixedReductions,
                $creditmemo->getOrderCurrencyCode());
            $roundingDifference = $creditmemo->getGrandTotal() - $fixedReductionAmount;
            return $this->distributeRoundingDifference($fixedReductions, 0, $roundingDifference, $baseLineItems,
                $creditmemo->getOrderCurrencyCode());
        } else {
            return $reductions;
        }
    }

    /**
     *
     * @param LineItemReductionCreate[] $reductions
     * @param int $index
     * @param number $remainder
     * @param \Wallee\Sdk\Model\LineItem[] $baseLineItems
     * @param string $currencyCode
     * @throws \Exception
     * @return LineItemReductionCreate[]
     */
    private function distributeRoundingDifference(array $reductions, $index, $remainder, array $baseLineItems,
        $currencyCode)
    {
        $digits = $this->helper->getCurrencyFractionDigits($currencyCode);
        $currentReduction = $reductions[$index];
        $delta = $remainder;
        $change = false;
        $positive = $delta > 0;
        $newReduction = null;
        $appliedDelta = null;
        if ($currentReduction->getUnitPriceReduction() != 0 && $currentReduction->getQuantityReduction() == 0) {
            $lineItem = $this->getLineItemByUniqueId($baseLineItems, $currentReduction->getLineItemUniqueId());
            if ($lineItem != null) {
                while ($delta != 0) {
                    if ($currentReduction->getUnitPriceReduction() < 0) {
                        $newReduction = $this->helper->roundAmount(
                            $currentReduction->getUnitPriceReduction() - ($delta / $lineItem->getQuantity()),
                            $currencyCode);
                    } else {
                        $newReduction = $this->helper->roundAmount(
                            $currentReduction->getUnitPriceReduction() + ($delta / $lineItem->getQuantity()),
                            $currencyCode);
                    }
                    $appliedDelta = ($newReduction - $currentReduction->getUnitPriceReduction()) *
                        $lineItem->getQuantity();
                    if ($appliedDelta <= $delta &&
                        $this->helper->compareAmounts($newReduction, $lineItem->getUnitPriceIncludingTax(),
                            $currencyCode) <= 0) {
                        $change = true;
                        break;
                    }

                    $newDelta = \round((\abs($delta) - \pow(0.1, $digits + 1)) * ($positive ? 1 : - 1), 10);
                    if (($positive xor $newDelta > 0) && $delta != 0) {
                        break;
                    }
                    $delta = $newDelta;
                }
            }
        }

        if ($change) {
            $currentReduction->setUnitPriceReduction($newReduction);
            $newRemainder = $remainder - $appliedDelta;
        } else {
            $newRemainder = $remainder;
        }

        if ($index + 1 < \count($reductions) && $newRemainder != 0) {
            return $this->distributeRoundingDifference($reductions, $index + 1, $newRemainder, $baseLineItems,
                $currencyCode);
        } else {
            if ($newRemainder > \pow(0.1, $digits + 1)) {
                throw new LocalizedException(\__('Could not distribute the rounding difference.'));
            } else {
                return $reductions;
            }
        }
    }

    /**
     *
     * @param \Wallee\Sdk\Model\LineItem[] $lineItems
     * @param string $uniqueId
     */
    private function getLineItemByUniqueId(array $lineItems, $uniqueId)
    {
        foreach ($lineItems as $lineItem) {
            if ($lineItem->getUniqueId() == $uniqueId) {
                return $lineItem;
            }
        }
        return null;
    }

    /**
     * Gets the line items that are to be used to calculate the refund.
     *
     * This returns the line items of the latest refund if there is one or of the transaction invoice otherwise.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @param Refund $refund
     * @return \Wallee\Sdk\Model\LineItem[]
     */
    public function getBaseLineItems($spaceId, $transactionId, Refund $refund = null)
    {
        $lastSuccessfulRefund = $this->getLastSuccessfulRefund($spaceId, $transactionId, $refund);
        if ($lastSuccessfulRefund instanceof Refund) {
            return $lastSuccessfulRefund->getReducedLineItems();
        } else {
            return $this->getTransactionInvoice($spaceId, $transactionId)->getLineItems();
        }
    }

    /**
     * Gets the transaction invoice for the given transaction.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @throws \Exception
     * @return \Wallee\Sdk\Model\TransactionInvoice
     */
    private function getTransactionInvoice($spaceId, $transactionId)
    {
        $query = new EntityQuery();
        $filter = new EntityQueryFilter();
        $filter->setType(EntityQueryFilterType::_AND);
        $filter->setChildren(
            [
                $this->helper->createEntityFilter('state', TransactionInvoiceState::CANCELED,
                    CriteriaOperator::NOT_EQUALS),
                $this->helper->createEntityFilter('completion.lineItemVersion.transaction.id', $transactionId)
            ]);
        $query->setFilter($filter);
        $query->setNumberOfEntities(1);
        $result = $this->apiClient->getService(TransactionInvoiceService::class)->search($spaceId, $query);
        if (! empty($result)) {
            return $result[0];
        } else {
            throw new LocalizedException(\__('The transaction invoice could not be found.'));
        }
    }

    /**
     * Gets the last successful refund of the given transaction, excluding the given refund.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @param Refund $refund
     * @return Refund
     */
    private function getLastSuccessfulRefund($spaceId, $transactionId, Refund $refund = null)
    {
        $query = new EntityQuery();
        $filter = new EntityQueryFilter();
        $filter->setType(EntityQueryFilterType::_AND);
        $filters = [
            $this->helper->createEntityFilter('state', RefundState::SUCCESSFUL),
            $this->helper->createEntityFilter('transaction.id', $transactionId)
        ];
        if ($refund != null) {
            $filters[] = $this->helper->createEntityFilter('id', $refund->getId(), CriteriaOperator::NOT_EQUALS);
        }
        $filter->setChildren($filters);
        $query->setFilter($filter);
        $query->setOrderBys([
            $this->helper->createEntityOrderBy('createdOn', EntityQueryOrderByType::DESC)
        ]);
        $query->setNumberOfEntities(1);
        $result = $this->apiClient->getService(RefundService::class)->search($spaceId, $query);
        if (! empty($result)) {
            return $result[0];
        }
    }
}