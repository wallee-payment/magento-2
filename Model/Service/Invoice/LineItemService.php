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
namespace Wallee\Payment\Model\Service\Invoice;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Model\GroupRegistry as CustomerGroupRegistry;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Tax\Api\TaxClassRepositoryInterface;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Tax\Model\Calculation as TaxCalculation;
use Wallee\Payment\Helper\Data as Helper;
use Wallee\Payment\Helper\LineItem as LineItemHelper;
use Wallee\Payment\Model\Service\AbstractLineItemService;
use Wallee\Sdk\Model\LineItemAttributeCreate;
use Wallee\Sdk\Model\TaxCreate;

/**
 * Service to handle line items in invoice context.
 */
class LineItemService extends AbstractLineItemService
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
     * @var TaxClassRepositoryInterface
     */
    private $taxClassRepository;

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
        parent::__construct($helper, $lineItemHelper, $scopeConfig, $taxClassRepository, $taxHelper, $taxCalculation,
            $groupRegistry, $eventManager, $productRepository);
        $this->helper = $helper;
        $this->lineItemHelper = $lineItemHelper;
        $this->taxClassRepository = $taxClassRepository;
    }

    /**
     * Convers the invoice's items to line items.
     *
     * @param Invoice $invoice
     * @param float $expectedAmount
     * @return \Wallee\Sdk\Model\LineItemCreate[]
     */
    public function convertInvoiceLineItems(Invoice $invoice, $expectedAmount)
    {
        return $this->lineItemHelper->reduceAmount($this->convertLineItems($invoice), $expectedAmount,
            $invoice->getOrderCurrencyCode());
    }

    /**
     * Gets the attributes for the given invoice item.
     *
     * @param Invoice\Item $entityItem
     * @return LineItemAttributeCreate[]
     */
    protected function getAttributes($entityItem)
    {
        $attributes = [];
        foreach ($this->getProductOptions($entityItem->getOrderItem()) as $option) {
            $value = $option['value'];
            if (\is_array($value)) {
                $value = \current($value);
            }

            $attribute = new LineItemAttributeCreate();
            $attribute->setLabel($this->helper->fixLength($this->helper->getFirstLine($option['label']), 512));
            $attribute->setValue($this->helper->fixLength($this->helper->getFirstLine($value), 512));
            $attributes[$this->getAttributeKey($option)] = $attribute;
        }

        return \array_merge($attributes,
            $this->getCustomAttributes($entityItem->getProductId(), $entityItem->getInvoice()
                ->getStoreId()));
    }

    /**
     * Gets the tax for the given invoice item.
     *
     * @param Invoice\Item $entityItem
     * @return TaxCreate
     */
    protected function getTax($entityItem)
    {
        if ($entityItem->getTaxAmount() > 0 && $entityItem->getOrderItem()->getTaxPercent() > 0) {
            $taxClassId = $entityItem->getOrderItem()
                ->getProduct()
                ->getTaxClassId();
            if ($taxClassId > 0) {
                $taxClass = $this->taxClassRepository->get($taxClassId);

                $tax = new TaxCreate();
                $tax->setRate($entityItem->getOrderItem()
                    ->getTaxPercent());
                $tax->setTitle($taxClass->getClassName());
                return $tax;
            }
        } else {
            return null;
        }
    }

    /**
     * Converts the invoice's shipping information to a line item.
     *
     * @param Invoice $invoice
     * @return \Wallee\Sdk\Model\LineItemCreate
     */
    protected function convertShippingLineItem($invoice)
    {
        return $this->convertShippingLineItemInner($invoice, $invoice->getShippingAmount(),
            $invoice->getShippingTaxAmount(),
            $invoice->getOrder()
                ->getShippingDiscountAmount() - $invoice->getShippingDiscountTaxCompensationAmount(),
            $invoice->getOrder()
                ->getShippingDescription());
    }

    /**
     * Gets the shipping tax for the given entity.
     *
     * @param Invoice $invoice
     * @return \Wallee\Sdk\Model\TaxCreate
     */
    protected function getShippingTax($invoice)
    {
        return parent::getShippingTax($invoice->getOrder());
    }

    /**
     *
     * @param Invoice\Item $entityItem
     * @return string
     */
    protected function getUniqueId($entityItem)
    {
        return $entityItem->getOrderItem()->getQuoteItemId();
    }

    /**
     * Gets the currency code of the given order.
     *
     * @param Invoice $invoice
     * @return string
     */
    protected function getCurrencyCode($invoice)
    {
        return $invoice->getOrderCurrencyCode();
    }
}