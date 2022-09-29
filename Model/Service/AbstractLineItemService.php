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
use Wallee\Payment\Model\Service\Quote\GiftCardAccountWrapper;
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
    private $helper;

    /**
     *
     * @var LineItemHelper
     */
    private $lineItemHelper;

    /**
     *
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     *
     * @var TaxClassRepositoryInterface
     */
    private $taxClassRepository;

    /**
     *
     * @var TaxHelper
     */
    private $taxHelper;

    /**
     *
     * @var TaxCalculation
     */
    private $taxCalculation;

    /**
     *
     * @var CustomerGroupRegistry
     */
    private $groupRegistry;

    /**
     *
     * @var EventManagerInterface
     */
    private $eventManager;

    /**
     *
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * Stores the gift card account object.
     * This property is a wrapper, which will return the GiftCardAccountManagement if it exists.
     * 
     * @var GiftCardAccountWrapper
     * 
     * @see \Magento\GiftCardAccount\Model\Service\GiftCardAccountManagement
     */
    private $giftCardAccountManagement;

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
     * @param GiftCardAccountWrapper $giftCardAccountManagement
     */
    public function __construct(Helper $helper, LineItemHelper $lineItemHelper, ScopeConfigInterface $scopeConfig,
        TaxClassRepositoryInterface $taxClassRepository, TaxHelper $taxHelper, TaxCalculation $taxCalculation,
        CustomerGroupRegistry $groupRegistry, EventManagerInterface $eventManager,
        ProductRepositoryInterface $productRepository, GiftCardAccountWrapper $giftCardAccountManagement = null)
    {
        $this->helper = $helper;
        $this->lineItemHelper = $lineItemHelper;
        $this->scopeConfig = $scopeConfig;
        $this->taxClassRepository = $taxClassRepository;
        $this->taxHelper = $taxHelper;
        $this->taxCalculation = $taxCalculation;
        $this->groupRegistry = $groupRegistry;
        $this->eventManager = $eventManager;
        $this->productRepository = $productRepository;
        $this->giftCardAccountManagement = $giftCardAccountManagement;
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

        if ($this->giftCardAccountManagement instanceof \Magento\GiftCardAccount\Model\Service\GiftCardAccountManagement) {
            /**
             * @var Magento\GiftCardAccount\Model\Giftcardaccount
             */
            $giftCardaccount = $this->giftCardAccountManagement->getListByQuoteId($entity->get()['entity_id']);

            if ($giftCardaccount instanceof \Magento\GiftCardAccount\Model\Giftcardaccount && count($giftCardaccount->getGiftCards()) > 0) {
                $giftCardCode = current($giftCardaccount->getGiftCards());
                $ammount = $giftCardaccount->getGiftCardsAmountUsed();
                $currencyCode = $this->getCurrencyCode($entity);

                // Builds the LineItem with gift card ammount.
                $items[] = $this->lineItemHelper->createGiftCardLineItem($giftCardCode, $ammount, $currencyCode);
            }
        }

        $transport = new DataObject([
            'items' => $items
        ]);
        $this->eventManager->dispatch('wallee_payment_convert_line_items',
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
    private function isIncludeItem($entityItem)
    {
        if ($entityItem->getParentItemId() != null &&
            $entityItem->getParentItem()->getProductType() ==
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
    private function convertItem($entityItem, $entity)
    {
        $amountIncludingTax = $entityItem->getRowTotal() - $entityItem->getDiscountAmount() + $entityItem->getTaxAmount() +
            $entityItem->getDiscountTaxCompensationAmount();

        $currency = $this->getCurrencyCode($entity);

        $productItem = new LineItemCreate();
        $productItem->setType(LineItemType::PRODUCT);
        $productItem->setUniqueId($this->getUniqueId($entityItem));
        $productItem->setAmountIncludingTax($this->helper->roundAmount($amountIncludingTax, $currency));
        $productItem->setName($this->helper->fixLength($entityItem->getName(), 150));
        $productItem->setQuantity($entityItem->getQty() ? $entityItem->getQty() : $entityItem->getQtyOrdered());
        $productItem->setShippingRequired(! $entityItem->getIsVirtual());
        $productItem->setSku($this->helper->fixLength($entityItem->getSku(), 200));
        $discount = $entityItem->getRowTotalInclTax() - $amountIncludingTax;
        $productItem->setDiscountIncludingTax($this->helper->roundAmount($discount, $currency));
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

        $transport = new DataObject([
            'item' => $productItem
        ]);
        $this->eventManager->dispatch('wallee_payment_convert_product_line_item',
            [
                'transport' => $transport,
                'entityItem' => $entityItem,
                'entity' => $entity
            ]);
        return $transport->getData('item');
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
            return $this->helper->fixLength('option_' . $option['option_id'], 40);
        } else {
            return $this->helper->fixLength(\preg_replace('/[^a-z0-9]/', '', \strtolower($option['label'])), 40);
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
                $taxClass = $this->taxClassRepository->get($taxClassId);

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
        $productAttributeCodeConfig = $this->scopeConfig->getValue(
            'wallee_payment/line_items/product_attributes', ScopeInterface::SCOPE_STORE, $storeId);
        if (! empty($productAttributeCodeConfig)) {
            $product = $this->productRepository->getById($productId, false, $storeId);
            $productAttributeCodes = \explode(',', $productAttributeCodeConfig);
            foreach ($productAttributeCodes as $productAttributeCode) {
                $productAttribute = $product->getResource()->getAttribute($productAttributeCode);
                $label = \__($productAttribute->getStoreLabel($storeId));
                $value = $productAttribute->getFrontend()->getValue($product);
                if ($value !== null && $value !== "" && $value !== false) {
                    $attribute = new LineItemAttributeCreate();
                    $attribute->setLabel($this->helper->fixLength($this->helper->getFirstLine($label), 512));
                    $attribute->setValue($this->helper->fixLength($this->helper->getFirstLine($value), 512));
                    $attributes['product_' . $this->helper->fixLength($productAttributeCode, 32)] = $attribute;
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
            $entity->getShippingTaxAmount() + $entity->getShippingDiscountTaxCompensationAmount(),
            $entity->getShippingDiscountAmount(),
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
    protected function convertShippingLineItemInner($entity, $shippingAmount, $shippingTaxAmount,
        $shippingDiscountAmount, $shippingDescription)
    {
        if ($shippingAmount > 0) {
            $shippingItem = new LineItemCreate();
            $shippingItem->setType(LineItemType::SHIPPING);
            $shippingItem->setUniqueId('shipping');
            $shippingItem->setAmountIncludingTax(
                $this->helper->roundAmount($shippingAmount + $shippingTaxAmount - $shippingDiscountAmount,
                    $this->getCurrencyCode($entity)));
            if ($this->scopeConfig->getValue('wallee_payment/line_items/overwrite_shipping_description',
                ScopeInterface::SCOPE_STORE, $entity->getStoreId())) {
                $shippingItem->setName(
                    $this->helper->fixLength(
                        $this->scopeConfig->getValue(
                            'wallee_payment/line_items/custom_shipping_description',
                            ScopeInterface::SCOPE_STORE, $entity->getStoreId()), 150));
            } else {
                $shippingItem->setName($this->helper->fixLength($shippingDescription, 150));
            }
            $shippingItem->setQuantity(1);
            $shippingItem->setSku('shipping');
            if ($shippingDiscountAmount > 0) {
                $shippingItem->setDiscountIncludingTax(
                    $this->helper->roundAmount($shippingDiscountAmount, $this->getCurrencyCode($entity)));
            }
            if ($shippingTaxAmount > 0) {
                $tax = $this->getShippingTax($entity);
                if ($tax instanceof TaxCreate) {
                    $shippingItem->setTaxes([
                        $tax
                    ]);
                }
            }

            $transport = new DataObject([
                'item' => $shippingItem
            ]);
            $this->eventManager->dispatch('wallee_payment_convert_shipping_line_item',
                [
                    'transport' => $transport,
                    'entity' => $entity
                ]);
            return $transport->getData('item');
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
    protected function getShippingTax($entity) {
        $taxClassId = null;
        try {
            $groupId = $entity->getCustomerGroupId();
            if ($groupId) {
                $customerGroup = $this->groupRegistry->retrieve($groupId);
                $taxClassId = $customerGroup->getTaxClassId();
            }
        } catch (NoSuchEntityException $e) {
            // group not found, do nothing
        }
        $taxRateRequest = $this->taxCalculation->getRateRequest($entity->getShippingAddress(),
            $entity->getBillingAddress(), $taxClassId, $entity->getStore());
        $shippingTaxClassId = $this->scopeConfig->getValue(
            \Magento\Tax\Model\Config::CONFIG_XML_PATH_SHIPPING_TAX_CLASS, ScopeInterface::SCOPE_STORE,
            $entity->getStoreId());
        if ($shippingTaxClassId > 0) {
            $shippingTaxClass = $this->taxClassRepository->get($shippingTaxClassId);
            $taxRateRequest->setProductClassId($shippingTaxClassId);
            $rate = $this->taxCalculation->getRate($taxRateRequest);
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