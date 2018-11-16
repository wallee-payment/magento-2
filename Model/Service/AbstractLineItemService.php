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
     * @param Helper $helper
     * @param LineItemHelper $lineItemHelper
     * @param ScopeConfigInterface $scopeConfig
     * @param TaxClassRepositoryInterface $taxClassRepository
     * @param TaxHelper $taxHelper
     * @param TaxCalculation $taxCalculation
     * @param CustomerGroupRegistry $groupRegistry
     * @param EventManagerInterface $eventManager
     */
    public function __construct(Helper $helper, LineItemHelper $lineItemHelper, ScopeConfigInterface $scopeConfig,
        TaxClassRepositoryInterface $taxClassRepository, TaxHelper $taxHelper, TaxCalculation $taxCalculation,
        CustomerGroupRegistry $groupRegistry, EventManagerInterface $eventManager)
    {
        $this->_helper = $helper;
        $this->_lineItemHelper = $lineItemHelper;
        $this->_scopeConfig = $scopeConfig;
        $this->_taxClassRepository = $taxClassRepository;
        $this->_taxHelper = $taxHelper;
        $this->_taxCalculation = $taxCalculation;
        $this->_groupRegistry = $groupRegistry;
        $this->_eventManager = $eventManager;
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
                $items = \array_merge($items, $this->convertItem($entityItem, $entity));
            }
        }

        $shippingItem = $this->convertShippingLineItem($entity);
        if ($shippingItem instanceof LineItemCreate) {
            $items[] = $shippingItem;
        }

        $transport = new DataObject([
            'items' => $items
        ]);
        $this->_eventManager->dispatch('wallee_payment_convert_line_items', [
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
     * @return LineItemCreate[]
     */
    protected function convertItem($entityItem, $entity)
    {
        $items = [];

        $items[] = $this->convertProductItem($entityItem, $entity);

        $discountItem = $this->convertDiscountItem($entityItem, $entity);
        if ($discountItem instanceof LineItemCreate) {
            $items[] = $discountItem;
        }

        return $items;
    }

    /**
     * Converts the given entity item to a line item.
     *
     * @param \Magento\Quote\Model\Quote\Item|\Magento\Sales\Model\Order\Item|\Magento\Sales\Model\Order\Invoice\Item $entityItem
     * @param \Magento\Quote\Model\Quote|\Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Invoice $entity
     * @return LineItemCreate
     */
    protected function convertProductItem($entityItem, $entity)
    {
        $productItem = new LineItemCreate();
        $productItem->setType(LineItemType::PRODUCT);
        $productItem->setUniqueId($this->getUniqueId($entityItem));
        $productItem->setAmountIncludingTax(
            $this->_helper->roundAmount($entityItem->getRowTotalInclTax(), $this->getCurrencyCode($entity)));
        $productItem->setName($this->_helper->fixLength($entityItem->getName(), 150));
        $productItem->setQuantity($entityItem->getQty() ? $entityItem->getQty() : $entityItem->getQtyOrdered());
        $productItem->setShippingRequired(! $entityItem->getIsVirtual());
        $productItem->setSku($this->_helper->fixLength($entityItem->getSku(), 200));
        if ($entityItem->getTaxPercent() > 0) {
            $tax = $this->getTax($entityItem);
            if ($tax instanceof TaxCreate) {
                $productItem->setTaxes([
                    $tax
                ]);
            }
        }
        $attributes = $this->getAttributes($entityItem);
        if (! empty($attributes)) {
            $productItem->setAttributes($attributes);
        }
        return $productItem;
    }

    /**
     * Converts the given entity item to a line item representing its discount if any.
     *
     * @param \Magento\Quote\Model\Quote\Item|\Magento\Sales\Model\Order\Item|\Magento\Sales\Model\Order\Invoice\Item $entityItem
     * @param \Magento\Quote\Model\Quote|\Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Invoice $entity
     * @return LineItemCreate
     */
    protected function convertDiscountItem($entityItem, $entity)
    {
        if ($entityItem->getDiscountAmount() > 0) {
            if ($this->_taxHelper->priceIncludesTax($entityItem->getStore()) ||
                ! $this->_taxHelper->applyTaxAfterDiscount($entityItem->getStore())) {
                $discountAmount = $entityItem->getDiscountAmount();
            } else {
                $discountAmount = $entityItem->getDiscountAmount() * ($entityItem->getTaxPercent() / 100 + 1);
            }

            $discountItem = new LineItemCreate();
            $discountItem->setType(LineItemType::DISCOUNT);
            $discountItem->setUniqueId($this->getUniqueId($entityItem) . '-discount');
            $discountItem->setAmountIncludingTax(
                $this->_helper->roundAmount($discountAmount * - 1, $this->getCurrencyCode($entity)));
            $discountItem->setName((String) \__('Discount'));
            $discountItem->setQuantity($entityItem->getQty() ? $entityItem->getQty() : $entityItem->getQtyOrdered());
            $discountItem->setSku($this->_helper->fixLength($entityItem->getSku(), 191) . '-discount');
            if ($this->_taxHelper->applyTaxAfterDiscount($entityItem->getStore()) && $entityItem->getTaxPercent()) {
                $tax = $this->getTax($entityItem);
                if ($tax instanceof TaxCreate) {
                    $discountItem->setTaxes([
                        $tax
                    ]);
                }
            }
            return $discountItem;
        }
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
        $taxClassId = $entityItem->getProduct()->getTaxClassId();
        if ($taxClassId > 0) {
            $taxClass = $this->_taxClassRepository->get($taxClassId);

            $tax = new TaxCreate();
            $tax->setRate($entityItem->getTaxPercent());
            $tax->setTitle($taxClass->getClassName());
            return $tax;
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
     * Converts the entity's shipping information to a line item.
     *
     * @param \Magento\Quote\Model\Quote|\Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Invoice $entity
     * @return LineItemCreate
     */
    protected function convertShippingLineItem($entity)
    {
        return $this->convertShippingLineItemInner($entity, $entity->getShippingInclTax(),
            $entity->getShippingDescription());
    }

    /**
     * Converts the entity's shipping information to a line item.
     *
     * @param \Magento\Quote\Model\Quote|\Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Invoice $entity
     * @param float $shippingAmount
     * @param string $shippingDescription
     * @return LineItemCreate
     */
    protected function convertShippingLineItemInner($entity, $shippingAmount, $shippingDescription)
    {
        if ($shippingAmount > 0) {
            $shippingItem = new LineItemCreate();
            $shippingItem->setType(LineItemType::SHIPPING);
            $shippingItem->setUniqueId('shipping');
            $shippingItem->setAmountIncludingTax(
                $this->_helper->roundAmount($shippingAmount, $this->getCurrencyCode($entity)));
            if ($this->_scopeConfig->getValue('wallee_payment/line_items/overwrite_shipping_description',
                ScopeInterface::SCOPE_STORE, $entity->getStoreId())) {
                $shippingItem->setName(
                    $this->_scopeConfig->getValue(
                        'wallee_payment/line_items/custom_shipping_description',
                        ScopeInterface::SCOPE_STORE, $entity->getStoreId()));
            } else {
                $shippingItem->setName($shippingDescription);
            }
            $shippingItem->setQuantity(1);
            $shippingItem->setSku('shipping');
            $tax = $this->getShippingTax($entity);
            if ($tax instanceof TaxCreate) {
                $shippingItem->setTaxes([
                    $tax
                ]);
            }
            return $shippingItem;
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