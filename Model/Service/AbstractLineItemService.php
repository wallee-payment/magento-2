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

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Model\GroupRegistry as CustomerGroupRegistry;
use Magento\Framework\DataObject;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Tax\Api\TaxClassRepositoryInterface;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Tax\Model\Calculation as TaxCalculation;
use Wallee\Payment\Helper\Data as Helper;
use Wallee\Payment\Helper\LineItem as LineItemHelper;
use Wallee\Sdk\Model\LineItemAttributeCreate;
use Wallee\Sdk\Model\LineItemCreate;
use Wallee\Sdk\Model\LineItemType;
use Wallee\Sdk\Model\TaxCreate;

/**
 * Abstract service to handle line items.
 */
abstract class AbstractLineItemService
{

    /**
     *
     * @var Helper
     */
    protected $_helper;

    /**
     *
     * @var LineItemHelper
     */
    protected $_lineItemHelper;

    /**
     *
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     *
     * @var TaxClassRepositoryInterface
     */
    protected $_taxClassRepository;

    /**
     *
     * @var TaxHelper
     */
    protected $_taxHelper;

    /**
     *
     * @var TaxCalculation
     */
    protected $_taxCalculation;

    /**
     *
     * @var CustomerGroupRegistry
     */
    protected $_groupRegistry;

    /**
     *
     * @var EventManagerInterface
     */
    protected $_eventManager;

    /**
     *
     * @var ProductRepositoryInterface
     */
    protected $_productRepository;

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
        $this->_helper = $helper;
        $this->_lineItemHelper = $lineItemHelper;
        $this->_scopeConfig = $scopeConfig;
        $this->_taxClassRepository = $taxClassRepository;
        $this->_taxHelper = $taxHelper;
        $this->_taxCalculation = $taxCalculation;
        $this->_groupRegistry = $groupRegistry;
        $this->_eventManager = $eventManager;
        $this->_productRepository = $productRepository;
    }

    /**
     * Convers the entity's items to line items.
     *
     * @param \Magento\Quote\Model\Quote|\Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Invoice $entity
     * @return LineItemCreate[]
     */
    protected function convertLineItems($entity)
    {
        $items = [];

        foreach ($entity->getAllItems() as $entityItem) {
            if ($this->isIncludeItem($entityItem)) {
                $items[] = $this->convertItem($entityItem, $entity);
            }
        }

        $shippingLineItems = $this->convertShippingLineItem($entity);
        if ($shippingLineItems instanceof LineItemCreate) {
            $items[] = $shippingLineItems;
        }

        $transport = new DataObject([
            'items' => $items
        ]);
        $this->_eventManager->dispatch('wallee_payment_convert_line_items',
            [
                'transport' => $transport,
                'entity' => $entity
            ]);
        return $transport->getData('items');
    }

    /**
     * Gets whether the given entity item is to be included in the line items.
     *
     * @param \Magento\Quote\Model\Quote\Item|\Magento\Sales\Model\Order\Item|\Magento\Sales\Model\Order\Invoice\Item $entityItem
     * @return boolean
     */
    protected function isIncludeItem($entityItem)
    {
        if ($entityItem->getParentItemId() != null && $entityItem->getParentItem()->getProductType() ==
            \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
            return false;
        }

        if ($entityItem->getProductType() == \Magento\Catalog\Model\Product\Type::TYPE_BUNDLE &&
            $entityItem->getParentItemId() == null &&
            $entityItem->getProduct()->getPriceType() != \Magento\Bundle\Model\Product\Price::PRICE_TYPE_FIXED) {
            return false;
        }

        return true;
    }

    /**
     * Converts the given entity item to line items.
     *
     * @param \Magento\Quote\Model\Quote\Item|\Magento\Sales\Model\Order\Item|\Magento\Sales\Model\Order\Invoice\Item $entityItem
     * @param \Magento\Quote\Model\Quote|\Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Invoice $entity
     * @return LineItemCreate
     */
    protected function convertItem($entityItem, $entity)
    {
        $amountIncludingTax = $entityItem->getRowTotal() - $entityItem->getDiscountAmount() + $entityItem->getTaxAmount() +
            $entityItem->getDiscountTaxCompensationAmount();

        $productItem = new LineItemCreate();
        $productItem->setType(LineItemType::PRODUCT);
        $productItem->setUniqueId($this->getUniqueId($entityItem));
        $productItem->setAmountIncludingTax(
            $this->_helper->roundAmount($amountIncludingTax, $this->getCurrencyCode($entity)));
        $productItem->setName($this->_helper->fixLength($entityItem->getName(), 150));
        $productItem->setQuantity($entityItem->getQty() ? $entityItem->getQty() : $entityItem->getQtyOrdered());
        $productItem->setShippingRequired(! $entityItem->getIsVirtual());
        $productItem->setSku($this->_helper->fixLength($entityItem->getSku(), 200));
        $tax = $this->getTax($entityItem);
        if ($tax instanceof TaxCreate) {
            $productItem->setTaxes([
                $tax
            ]);
        }
        $attributes = $this->getAttributes($entityItem);
        if (! empty($attributes)) {
            $productItem->setAttributes($attributes);
        }
        return $productItem;
    }

    /**
     * Gets the key of the given product option.
     *
     * @param array $option
     * @return string
     */
    protected function getAttributeKey($option)
    {
        if (isset($option['option_id']) && ! empty($option['option_id'])) {
            return $this->_helper->fixLength('option_' . $option['option_id'], 40);
        } else {
            return $this->_helper->fixLength(\preg_replace('/[^a-z0-9]/', '', strtolower($option['label'])), 40);
        }
    }

    /**
     * Gets the tax for the given item.
     *
     * @param \Magento\Quote\Model\Quote\Item|\Magento\Sales\Model\Order\Item|\Magento\Sales\Model\Order\Invoice\Item $entityItem
     * @return TaxCreate
     */
    protected function getTax($entityItem)
    {
        if ($entityItem->getTaxAmount() > 0 && $entityItem->getTaxPercent() > 0) {
            $taxClassId = $entityItem->getProduct()->getTaxClassId();
            if ($taxClassId > 0) {
                $taxClass = $this->_taxClassRepository->get($taxClassId);

                $tax = new TaxCreate();
                $tax->setRate($entityItem->getTaxPercent());
                $tax->setTitle($taxClass->getClassName());
                return $tax;
            }
        } else {
            return null;
        }
    }

    /**
     * Gets the attributes for the given entity item.
     *
     * @param \Magento\Quote\Model\Quote\Item|\Magento\Sales\Model\Order\Item|\Magento\Sales\Model\Order\Invoice\Item $entityItem
     * @return \Wallee\Sdk\Model\LineItemAttributeCreate[]
     */
    abstract protected function getAttributes($entityItem);

    /**
     * Gets the line item attributes by the configured product attributes.
     *
     * @param int $productId
     * @param int $storeId
     * @return \Wallee\Sdk\Model\LineItemAttributeCreate[]
     */
    protected function getCustomAttributes($productId, $storeId)
    {
        $attributes = [];
        $productAttributeCodeConfig = $this->_scopeConfig->getValue(
            'wallee_payment/line_items/product_attributes', ScopeInterface::SCOPE_STORE, $storeId);
        if (! empty($productAttributeCodeConfig)) {
            $product = $this->_productRepository->getById($productId, false, $storeId);
            $productAttributeCodes = \explode(',', $productAttributeCodeConfig);
            foreach ($productAttributeCodes as $productAttributeCode) {
                $productAttribute = $product->getResource()->getAttribute($productAttributeCode);
                $label = \__($productAttribute->getStoreLabel($storeId));
                $value = $productAttribute->getFrontend()->getValue($product);
                if ($value !== null && $value !== "" && $value !== false) {
                    $attribute = new LineItemAttributeCreate();
                    $attribute->setLabel($this->_helper->fixLength($this->_helper->getFirstLine($label), 512));
                    $attribute->setValue($this->_helper->fixLength($this->_helper->getFirstLine($value), 512));
                    $attributes['product_' . $productAttributeCode] = $attribute;
                }
            }
        }
        return $attributes;
    }

    /**
     * Gets the product options.
     *
     * @param \Magento\Quote\Model\Quote\Item|\Magento\Sales\Model\Order\Item|\Magento\Sales\Model\Order\Invoice\Item $entityItem
     * @return array
     */
    protected function getProductOptions($entityItem)
    {
        $options = $entityItem->getProductOptions();
        if (isset($options['attributes_info'])) {
            return $options['attributes_info'];
        } elseif (isset($options['options'])) {
            return $options['options'];
        } else {
            return [];
        }
    }

    /**
     * Converts the entity's shipping information to a line item.
     *
     * @param \Magento\Quote\Model\Quote|\Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Invoice $entity
     * @return LineItemCreate
     */
    protected function convertShippingLineItem($entity)
    {
        return $this->convertShippingLineItemInner($entity, $entity->getShippingAmount(),
            $entity->getShippingTaxAmount(),
            $entity->getShippingDiscountAmount() - $entity->getShippingDiscountTaxCompensationAmount(),
            $entity->getShippingDescription());
    }

    /**
     * Converts the entity's shipping information to a line item.
     *
     * @param \Magento\Quote\Model\Quote|\Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Invoice $entity
     * @param float $shippingAmount
     * @param string $shippingDescription
     * @param float $shippingDiscountAmount
     * @return LineItemCreate
     */
    protected function convertShippingLineItemInner($entity, $shippingAmount, $shippingTaxAmount, $shippingDiscountAmount,
        $shippingDescription)
    {
        if ($shippingAmount > 0) {
            $shippingItem = new LineItemCreate();
            $shippingItem->setType(LineItemType::SHIPPING);
            $shippingItem->setUniqueId('shipping');
            $shippingItem->setAmountIncludingTax(
                $this->_helper->roundAmount($shippingAmount + $shippingTaxAmount - $shippingDiscountAmount,
                    $this->getCurrencyCode($entity)));
            if ($this->_scopeConfig->getValue('wallee_payment/line_items/overwrite_shipping_description',
                ScopeInterface::SCOPE_STORE, $entity->getStoreId())) {
                $shippingItem->setName(
                    $this->_helper->fixLength(
                        $this->_scopeConfig->getValue(
                            'wallee_payment/line_items/custom_shipping_description',
                            ScopeInterface::SCOPE_STORE, $entity->getStoreId()), 150));
            } else {
                $shippingItem->setName($this->_helper->fixLength($shippingDescription, 150));
            }
            $shippingItem->setQuantity(1);
            $shippingItem->setSku('shipping');
            if ($shippingTaxAmount > 0) {
                $tax = $this->getShippingTax($entity);
                if ($tax instanceof TaxCreate) {
                    $shippingItem->setTaxes([
                        $tax
                    ]);
                }
            }
            return $shippingItem;
        } else {
            return null;
        }
    }

    /**
     * Gets the shipping tax for the given entity.
     *
     * @param \Magento\Quote\Model\Quote|\Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Invoice $entity
     * @return TaxCreate
     */
    protected function getShippingTax($entity)
    {
        $customerGroup = $this->_groupRegistry->retrieve($entity->getCustomerGroupId());
        $taxRateRequest = $this->_taxCalculation->getRateRequest($entity->getShippingAddress(),
            $entity->getBillingAddress(), $customerGroup->getTaxClassId(), $entity->getStore());
        $shippingTaxClassId = $this->_scopeConfig->getValue(
            \Magento\Tax\Model\Config::CONFIG_XML_PATH_SHIPPING_TAX_CLASS, ScopeInterface::SCOPE_STORE,
            $entity->getStoreId());
        if ($shippingTaxClassId > 0) {
            $shippingTaxClass = $this->_taxClassRepository->get($shippingTaxClassId);
            $taxRateRequest->setProductClassId($shippingTaxClassId);
            $rate = $this->_taxCalculation->getRate($taxRateRequest);
            if ($rate > 0) {
                $tax = new TaxCreate();
                $tax->setRate($rate);
                $tax->setTitle($shippingTaxClass->getClassName());
                return $tax;
            }
        }
    }

    /**
     * Gets the unique ID for the line item of the given entity.
     *
     * @param \Magento\Quote\Model\Quote\Item|\Magento\Sales\Model\Order\Item|\Magento\Sales\Model\Order\Invoice\Item $entityItem
     * @return string
     */
    abstract protected function getUniqueId($entityItem);

    /**
     * Gets the currency code of the given entity.
     *
     * @param \Magento\Quote\Model\Quote|\Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Invoice $entity
     * @return string
     */
    abstract protected function getCurrencyCode($entity);
}