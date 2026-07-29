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

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Model\GroupRegistry as CustomerGroupRegistry;
use Magento\Framework\DataObject;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Store\Model\ScopeInterface;
use Magento\Tax\Api\TaxClassRepositoryInterface;
use Magento\Tax\Model\Calculation as TaxCalculation;
use Wallee\Payment\Helper\Data as Helper;
use Wallee\Payment\Helper\LineItem as LineItemHelper;
use Wallee\Payment\Model\Service\Quote\GiftCardAccountWrapper;
use Wallee\PluginCore\LineItem\LineItem as CoreLineItem;
use Wallee\PluginCore\LineItem\LineItemAttribute as CoreLineItemAttribute;
use Wallee\PluginCore\LineItem\LineItemAttributeCollection as CoreLineItemAttributeCollection;
use Wallee\PluginCore\LineItem\UnitPriceCalculator;
use Wallee\PluginCore\Tax\Tax as CoreTax;
use Psr\Log\LoggerInterface;

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
     * @var LoggerInterface
     */
    private $logger;

    /**
     *
     * @param Helper $helper
     * @param LineItemHelper $lineItemHelper
     * @param ScopeConfigInterface $scopeConfig
     * @param TaxClassRepositoryInterface $taxClassRepository
     * @param TaxCalculation $taxCalculation
     * @param CustomerGroupRegistry $groupRegistry
     * @param EventManagerInterface $eventManager
     * @param ProductRepositoryInterface $productRepository
     * @param LoggerInterface $logger
     * @param GiftCardAccountWrapper $giftCardAccountManagement
     */
    public function __construct(
        Helper $helper,
        LineItemHelper $lineItemHelper,
        ScopeConfigInterface $scopeConfig,
        TaxClassRepositoryInterface $taxClassRepository,
        TaxCalculation $taxCalculation,
        CustomerGroupRegistry $groupRegistry,
        EventManagerInterface $eventManager,
        ProductRepositoryInterface $productRepository,
        LoggerInterface $logger,
        ?GiftCardAccountWrapper $giftCardAccountManagement = null,
    ) {
        $this->helper = $helper;
        $this->lineItemHelper = $lineItemHelper;
        $this->scopeConfig = $scopeConfig;
        $this->taxClassRepository = $taxClassRepository;
        $this->taxCalculation = $taxCalculation;
        $this->groupRegistry = $groupRegistry;
        $this->eventManager = $eventManager;
        $this->productRepository = $productRepository;
        $this->logger = $logger;
        $this->giftCardAccountManagement = $giftCardAccountManagement;
    }

    /**
     * Check whether the current area is admin backend.
     *
     * @return bool
     */
    protected function checkIsAdmin()
    {
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $state =  $om->get(\Magento\Framework\App\State::class);
        return 'adminhtml' === $state->getAreaCode();
    }

    /**
     * Convers the entity's items to line items.
     *
     * @param \Magento\Quote\Model\Quote|\Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Invoice $entity
     * @return CoreLineItem[]
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
        if ($shippingLineItems instanceof CoreLineItem) {
            $items[] = $shippingLineItems;
        }

        if (!$this->checkIsAdmin()) {
            if ($this->giftCardAccountManagement
                instanceof \Magento\GiftCardAccount\Model\Service\GiftCardAccountManagement
            ) {
                $giftCardaccount = $this->giftCardAccountManagement->getListByQuoteId($entity->get()['entity_id']);

                if ($giftCardaccount instanceof \Magento\GiftCardAccount\Model\Giftcardaccount
                    && count($giftCardaccount->getGiftCards()) > 0
                ) {
                    $giftCardCode = current($giftCardaccount->getGiftCards());
                    $ammount = $giftCardaccount->getGiftCardsAmountUsed();
                    $currencyCode = $this->getCurrencyCode($entity);

                    // Builds the LineItem with gift card ammount.
                    $items[] = $this->lineItemHelper->createGiftCardLineItem($giftCardCode, $ammount, $currencyCode);
                }
            }
        }

        $transport = new DataObject([
            'items' => $items
        ]);
        $this->eventManager->dispatch(
            'wallee_payment_convert_line_items',
            [
                'transport' => $transport,
                'entity' => $entity
            ]
        );
        return $transport->getData('items');
    }

    /**
     * Gets whether the given entity item is to be included in the line items.
     *
     * @param \Magento\Framework\DataObject $entityItem
     * @return boolean
     */
    private function isIncludeItem($entityItem)
    {
        $item = $this->getItemForInclusionCheck($entityItem);

        if ($item->getParentItemId() != null &&
            $item->getParentItem()->getProductType() ==
            \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
            return false;
        }

        if ($item->getProductType() == \Magento\Catalog\Model\Product\Type::TYPE_BUNDLE &&
            $item->getParentItemId() == null &&
            $item->getProduct()->getPriceType() != \Magento\Bundle\Model\Product\Price::PRICE_TYPE_FIXED) {
            return false;
        }

        return true;
    }

    /**
     * Resolves the item whose parent-item-id and product-type should be inspected by
     * isIncludeItem(). Defaults to the entity item itself; overridden where the entity
     * item's own type doesn't carry that data (e.g. invoice items, whose underlying
     * sales_invoice_item table has no parent_item_id or product_type column at all).
     *
     * @param \Magento\Framework\DataObject $entityItem
     * @return \Magento\Framework\DataObject
     */
    protected function getItemForInclusionCheck($entityItem)
    {
        return $entityItem;
    }

    /**
     * Converts the given entity item to line items.
     *
     * @param \Magento\Framework\DataObject $entityItem
     * @param \Magento\Quote\Model\Quote|\Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Invoice $entity
     * @return CoreLineItem
     */
    private function convertItem($entityItem, $entity)
    {
        $amountIncludingTax = $entityItem->getRowTotal() -
        $entityItem->getDiscountAmount() +
        $entityItem->getTaxAmount() +
        $entityItem->getDiscountTaxCompensationAmount();

        $currency = $this->getCurrencyCode($entity);

        $productItem = new CoreLineItem();
        $productItem->type = CoreLineItem::TYPE_PRODUCT;
        $productItem->uniqueId = (string) $this->getUniqueId($entityItem);
        $productItem->amountIncludingTax = (float) $this->helper->roundAmount($amountIncludingTax, $currency);
        $discount = $entityItem->getRowTotalInclTax() - $amountIncludingTax;
        $productItem->discountIncludingTax = (float) $this->helper->roundAmount($discount, $currency);
        $productItem->name = $entityItem->getName();
        $productItem->quantity = (float) ($entityItem->getQty() ? $entityItem->getQty() : $entityItem->getQtyOrdered());
        $productItem->shippingRequired = ! $entityItem->getIsVirtual();
        $productItem->sku = $entityItem->getSku();
        $productItem->unitPriceIncludingTax = UnitPriceCalculator::deriveUnitPrice(
            $productItem->amountIncludingTax,
            $productItem->quantity
        );
        $tax = $this->getTax($entityItem);
        if ($tax instanceof CoreTax) {
            $productItem->addTax($tax);
        }
        $attributes = $this->getAttributes($entityItem);
        if (! empty($attributes)) {
            $productItem->attributes = new CoreLineItemAttributeCollection(...array_values($attributes));
        }

        $transport = new DataObject([
            'item' => $productItem
        ]);
        $this->eventManager->dispatch(
            'wallee_payment_convert_product_line_item',
            [
                'transport' => $transport,
                'entityItem' => $entityItem,
                'entity' => $entity
            ]
        );
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
            return 'option_' . $option['option_id'];
        } else {
            return (string) $option['label'];
        }
    }

    /**
     * Gets the tax for the given item.
     *
     * @param \Magento\Framework\DataObject $entityItem
     * @return CoreTax|null
     */
    protected function getTax($entityItem)
    {
        if ($entityItem->getTaxAmount() > 0 && $entityItem->getTaxPercent() > 0) {
            $taxClassId = $entityItem->getProduct()->getTaxClassId();
            if ($taxClassId > 0) {
                $taxClass = $this->taxClassRepository->get($taxClassId);
                return $this->buildTax((float) $entityItem->getTaxPercent(), (string) $taxClass->getClassName());
            }
        } else {
            return null;
        }
    }

    /**
     * Builds a plugin-core Tax value object from a rate and title. Tax
     * self-truncates an overlong title, but still rejects one under 2
     * characters — a store with such a misconfigured tax class name should
     * not crash checkout.
     *
     * @param float $rate
     * @param string $title
     * @return CoreTax|null
     */
    protected function buildTax(float $rate, string $title): ?CoreTax
    {
        try {
            return new CoreTax($title, $rate);
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Skipping line item tax with invalid title: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Gets the attributes for the given entity item.
     *
     * @param \Magento\Framework\DataObject $entityItem
     * @return CoreLineItemAttribute[]
     */
    abstract protected function getAttributes($entityItem);

    /**
     * Gets the line item attributes by the configured product attributes.
     *
     * @param int $productId
     * @param int $storeId
     * @return CoreLineItemAttribute[]
     */
    protected function getCustomAttributes($productId, $storeId)
    {
        $attributes = [];
        $productAttributeCodeConfig = $this->scopeConfig->getValue(
            'wallee_payment/line_items/product_attributes',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if (! empty($productAttributeCodeConfig)) {
            $product = $this->productRepository->getById($productId, false, $storeId);
            $productAttributeCodes = \explode(',', $productAttributeCodeConfig);
            foreach ($productAttributeCodes as $productAttributeCode) {
                $productAttribute = $product->getResource()->getAttribute($productAttributeCode);
                $label = \__($productAttribute->getStoreLabel($storeId));
                $value = $productAttribute->getFrontend()->getValue($product);
                if ($value !== null && $value !== "" && $value !== false) {
                    $key = 'product_' . $productAttributeCode;
                    $attributes[$key] = new CoreLineItemAttribute(
                        $key,
                        $this->helper->fixLength($this->helper->getFirstLine($label), 512),
                        $this->helper->getFirstLine($value)
                    );
                }
            }
        }
        return $attributes;
    }

    /**
     * Gets the product options.
     *
     * @param \Magento\Framework\DataObject $entityItem
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
     * @return CoreLineItem|null
     */
    protected function convertShippingLineItem($entity)
    {
        return $this->convertShippingLineItemInner(
            $entity,
            $entity->getShippingAmount(),
            $entity->getShippingTaxAmount() + $entity->getShippingDiscountTaxCompensationAmount(),
            $entity->getShippingDiscountAmount(),
            $entity->getShippingDescription()
        );
    }

    /**
     * Converts the entity's shipping information to a line item.
     *
     * @param Quote|Order|Invoice $entity
     * @param float $shippingAmount
     * @param float $shippingTaxAmount
     * @param float $shippingDiscountAmount
     * @param string $shippingDescription
     * @return CoreLineItem|null
     */
    protected function convertShippingLineItemInner(
        $entity,
        $shippingAmount,
        $shippingTaxAmount,
        $shippingDiscountAmount,
        $shippingDescription
    ) {
        if ($shippingAmount > 0) {
            $shippingItem = new CoreLineItem();
            $shippingItem->type = CoreLineItem::TYPE_SHIPPING;
            $shippingItem->uniqueId = 'shipping';
            $shippingItem->amountIncludingTax = (float) $this->helper->roundAmount(
                $shippingAmount + $shippingTaxAmount - $shippingDiscountAmount,
                $this->getCurrencyCode($entity)
            );
            if ($this->scopeConfig->getValue(
                'wallee_payment/line_items/overwrite_shipping_description',
                ScopeInterface::SCOPE_STORE,
                $entity->getStoreId()
            )
            ) {
                $shippingItem->name = $this->scopeConfig->getValue(
                    'wallee_payment/line_items/custom_shipping_description',
                    ScopeInterface::SCOPE_STORE,
                    $entity->getStoreId()
                );
            } else {
                $shippingItem->name = $shippingDescription;
            }
            $shippingItem->quantity = 1.0;
            $shippingItem->sku = 'shipping';
            $shippingItem->unitPriceIncludingTax = $shippingItem->amountIncludingTax;
            if ($shippingDiscountAmount > 0) {
                $shippingItem->discountIncludingTax = (float) $this->helper->roundAmount(
                    $shippingDiscountAmount,
                    $this->getCurrencyCode($entity)
                );
            }
            if ($shippingTaxAmount > 0) {
                $tax = $this->getShippingTax($entity);
                if ($tax instanceof CoreTax) {
                    $shippingItem->addTax($tax);
                }
            }

            $transport = new DataObject([
                'item' => $shippingItem
            ]);
            $this->eventManager->dispatch(
                'wallee_payment_convert_shipping_line_item',
                [
                    'transport' => $transport,
                    'entity' => $entity
                ]
            );
            return $transport->getData('item');
        } else {
            return null;
        }
    }

    /**
     * Gets the shipping tax for the given entity.
     *
     * @param \Magento\Quote\Model\Quote|\Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Invoice $entity
     * @return CoreTax|null
     */
    protected function getShippingTax($entity)
    {
        $taxClassId = null;
        try {
            $groupId = $entity->getCustomerGroupId();
            if ($groupId) {
                $customerGroup = $this->groupRegistry->retrieve($groupId);
                $taxClassId = $customerGroup->getTaxClassId();
            }
        } catch (NoSuchEntityException $e) {
            // group not found, do nothing
            $this->logger->debug(
                'There was an issue retrieving the customer group.',
                ['exception' => $e]
            );
        }
        $taxRateRequest = $this->taxCalculation->getRateRequest(
            $entity->getShippingAddress(),
            $entity->getBillingAddress(),
            $taxClassId,
            $entity->getStore()
        );
        $shippingTaxClassId = $this->scopeConfig->getValue(
            \Magento\Tax\Model\Config::CONFIG_XML_PATH_SHIPPING_TAX_CLASS,
            ScopeInterface::SCOPE_STORE,
            $entity->getStoreId()
        );
        if ($shippingTaxClassId > 0) {
            $shippingTaxClass = $this->taxClassRepository->get($shippingTaxClassId);
            $taxRateRequest->setProductClassId($shippingTaxClassId);
            $rate = $this->taxCalculation->getRate($taxRateRequest);
            if ($rate > 0) {
                return $this->buildTax((float) $rate, (string) $shippingTaxClass->getClassName());
            }
        }
        return null;
    }

    /**
     * Gets the unique ID for the line item of the given entity.
     *
     * @param \Magento\Framework\DataObject $entityItem
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
