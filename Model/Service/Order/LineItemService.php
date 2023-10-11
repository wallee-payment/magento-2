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
namespace Wallee\Payment\Model\Service\Order;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Model\GroupRegistry as CustomerGroupRegistry;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;
use Magento\Tax\Api\TaxClassRepositoryInterface;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Tax\Model\Calculation as TaxCalculation;
use Wallee\Payment\Helper\Data as Helper;
use Wallee\Payment\Helper\LineItem as LineItemHelper;
use Wallee\Payment\Model\Service\AbstractLineItemService;
use Wallee\Sdk\Model\LineItemAttributeCreate;

/**
 * Service to handle line items in order context.
 */
class LineItemService extends AbstractLineItemService
{

    /**
     *
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     *
     * @var Helper
     */
    private $helper;

    /**
     *
     * @var LineItemHelper
     */
    private $lineItemHelper;

    /**
     *
     * @var TaxHelper
     */
    private $taxHelper;

    /**
     *
     * @param Helper $helper
     * @param LineItemHelper $lineItemHelper
     * @param ScopeConfigInterface $scopeConfig
     * @param TaxClassRepositoryInterface $taxClassRepository
     * @param TaxHelper $taxHelper
     * @param TaxCalculation $taxCalculation
     * @param CustomerGroupRegistry $groupRegistry
     * @param EventManagerInterface $eventManager
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(Helper $helper, LineItemHelper $lineItemHelper, ScopeConfigInterface $scopeConfig,
        TaxClassRepositoryInterface $taxClassRepository, TaxHelper $taxHelper, TaxCalculation $taxCalculation,
        CustomerGroupRegistry $groupRegistry, EventManagerInterface $eventManager,
        ProductRepositoryInterface $productRepository)
    {
        parent::__construct($helper, $lineItemHelper, $scopeConfig, $taxClassRepository, $taxCalculation,
            $groupRegistry, $eventManager, $productRepository);
        $this->scopeConfig = $scopeConfig;
        $this->helper = $helper;
        $this->lineItemHelper = $lineItemHelper;
        $this->taxHelper = $taxHelper;
    }

    /**
     * Convers the order's items to line items.
     *
     * @param Order $order
     * @return \Wallee\Sdk\Model\LineItemCreate[]
     */
    public function convertOrderLineItems(Order $order)
    {
        return $this->lineItemHelper->correctLineItems($this->convertLineItems($order), $order->getGrandTotal(),
            $this->getCurrencyCode($order),
            $this->scopeConfig->getValue('wallee_payment/line_items/enforce_consistency',
                ScopeInterface::SCOPE_STORE, $order->getStoreId()), $this->taxHelper->getCalculatedTaxes($order));
    }

    /**
     * Gets the attributes for the given order item.
     *
     * @param Order\Item $entityItem
     * @return LineItemAttributeCreate[]
     */
    protected function getAttributes($entityItem)
    {
        $attributes = [];
        foreach ($this->getProductOptions($entityItem) as $option) {
            $value = $option['value'];
            if (\is_array($value)) {
                $value = \current($value);
            }

            $attribute = new LineItemAttributeCreate();
            $attribute->setLabel($this->helper->fixLength($this->helper->getFirstLine($option['label']), 512));
            $attribute->setValue(strip_tags($this->helper->fixLength($this->helper->getFirstLine($value), 512)));
            $attributes[$this->getAttributeKey($option)] = $attribute;
        }

        return \array_merge($attributes,
            $this->getCustomAttributes($entityItem->getProductId(), $entityItem->getOrder()
                ->getStoreId()));
    }

    /**
     *
     * @param Order\Item $entityItem
     * @return string
     */
    protected function getUniqueId($entityItem)
    {
        return $entityItem->getQuoteItemId();
    }

    /**
     * Gets the currency code of the given order.
     *
     * @param Order $order
     * @return string
     */
    protected function getCurrencyCode($order)
    {
        return $order->getOrderCurrencyCode();
    }
}