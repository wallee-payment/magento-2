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
namespace Wallee\Payment\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Sales\Model\Order\Creditmemo;
use Wallee\Payment\Helper\Data as Helper;
use Wallee\PluginCore\Currency\CurrencyRoundingService;

/**
 * Observer to collect the line item reductions for the fooman surcharges.
 */
class CollectFoomanSurchargeLineItemReductions implements ObserverInterface
{

    /**
     *
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     *
     * @var Helper
     */
    private $helper;

    /**
     *
     * @param ModuleManager $moduleManager
     * @param Helper $helper
     */
    public function __construct(ModuleManager $moduleManager, Helper $helper)
    {
        $this->moduleManager = $moduleManager;
        $this->helper = $helper;
    }

    /**
     * Append Fooman surcharges to the line item reductions.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        /* @var Creditmemo $creditmemo */
        $creditmemo = $observer->getCreditmemo();
        /* @var \Wallee\PluginCore\LineItem\LineItem[] $baseLineItems */
        $baseLineItems = $observer->getData('baseLineItems');
        $transport = $observer->getTransport();

        if ($this->moduleManager->isEnabled('Fooman_Surcharge')) {
            $transport->setData(
                'items',
                \array_merge($transport->getData('items'), $this->convertFoomanSurcharges($creditmemo, $baseLineItems))
            );
        }
    }

    /**
     * Converts the fooman surcharge lines to line item reductions.
     *
     * @param Creditmemo $creditmemo
     * @param \Wallee\PluginCore\LineItem\LineItem[] $baseLineItems
     * @return list<array{uniqueId: ?string, quantityReduction: float, unitPriceReduction: float}>
     */
    protected function convertFoomanSurcharges(Creditmemo $creditmemo, $baseLineItems)
    {
        if (! $creditmemo->getExtensionAttributes()) {
            return [];
        }

        if (! $creditmemo->getExtensionAttributes()->getFoomanTotalGroup()) {
            return [];
        }

        $baseLineItemMap = [];
        foreach ($baseLineItems as $lineItem) {
            $baseLineItemMap[$lineItem->uniqueId] = $lineItem;
        }

        $items = [];
        foreach ($creditmemo->getExtensionAttributes()
            ->getFoomanTotalGroup()
            ->getItems() as $item) {
            if ($item->getAmount() <= 0) {
                continue;
            }
            $items[] = $this->createSurchargeReduction(
                $creditmemo,
                $item->getTypeId(),
                $item->getAmount() + $item->getTaxAmount(),
                isset($baseLineItemMap['fooman_surcharge_' . $item->getTypeId()])
                    ? $baseLineItemMap['fooman_surcharge_' . $item->getTypeId()]
                    : null
            );
        }
        return $items;
    }

    /**
     * Create a surcharge line item reduction.
     *
     * @param Creditmemo $creditmemo
     * @param string $code
     * @param float $amount
     * @param mixed $baseLineItem
     * @return array{uniqueId: ?string, quantityReduction: float, unitPriceReduction: float}
     */
    private function createSurchargeReduction(Creditmemo $creditmemo, $code, $amount, $baseLineItem)
    {
        if ($baseLineItem != null && CurrencyRoundingService::areAmountsEqual(
            $baseLineItem->amountIncludingTax,
            $amount,
            $creditmemo->getOrderCurrencyCode()
        )) {
            return [
                'uniqueId' => 'fooman_surcharge_' . $code,
                'quantityReduction' => 1.0,
                'unitPriceReduction' => 0.0,
            ];
        }

        return [
            'uniqueId' => 'fooman_surcharge_' . $code,
            'quantityReduction' => 0.0,
            'unitPriceReduction' => $this->helper->roundAmount($amount, $creditmemo->getOrderCurrencyCode()),
        ];
    }
}
