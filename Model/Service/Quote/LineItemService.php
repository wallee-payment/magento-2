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
namespace Wallee\Payment\Model\Service\Quote;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Product\Configuration as ProductConfigurationHelper;
use Magento\Customer\Model\GroupRegistry as CustomerGroupRegistry;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;
use Magento\Tax\Api\TaxClassRepositoryInterface;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Tax\Model\Calculation as TaxCalculation;
use Wallee\Payment\Helper\Data as Helper;
use Wallee\Payment\Helper\LineItem as LineItemHelper;
use Wallee\Payment\Model\Service\AbstractLineItemService;
use Wallee\Sdk\Model\LineItemAttributeCreate;

/**
 * Service to handle line items in quote context.
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
     * @var ProductConfigurationHelper
     */
    private $productConfigurationHelper;

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
     * @param ProductConfigurationHelper $productConfigurationHelper
     */
    public function __construct(Helper $helper, LineItemHelper $lineItemHelper, ScopeConfigInterface $scopeConfig,
        TaxClassRepositoryInterface $taxClassRepository, TaxHelper $taxHelper, TaxCalculation $taxCalculation,
        CustomerGroupRegistry $groupRegistry, EventManagerInterface $eventManager,
        ProductRepositoryInterface $productRepository, ProductConfigurationHelper $productConfigurationHelper)
    {
        parent::__construct($helper, $lineItemHelper, $scopeConfig, $taxClassRepository, $taxHelper, $taxCalculation,
            $groupRegistry, $eventManager, $productRepository);
        $this->scopeConfig = $scopeConfig;
        $this->helper = $helper;
        $this->lineItemHelper = $lineItemHelper;
        $this->productConfigurationHelper = $productConfigurationHelper;
    }

    /**
     * Convers the quote's items to line items.
     *
     * @param Quote $quote
     * @return \Wallee\Sdk\Model\LineItemCreate[]
     */
    public function convertQuoteLineItems(Quote $quote)
    {
        return $this->lineItemHelper->correctLineItems($this->convertLineItems($quote), $quote->getGrandTotal(),
            $this->getCurrencyCode($quote),
            $this->scopeConfig->getValue('wallee_payment/line_items/enforce_consistency',
                ScopeInterface::SCOPE_STORE, $quote->getStoreId()), []);
    }

    /**
     * Gets the attributes for the given quote item.
     *
     * @param Quote\Item $entityItem
     * @return LineItemAttributeCreate[]
     */
    protected function getAttributes($entityItem)
    {
        $attributes = [];
        foreach ($this->productConfigurationHelper->getOptions($entityItem) as $option) {
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
            $this->getCustomAttributes($entityItem->getProductId(), $entityItem->getQuote()
                ->getStoreId()));
    }

    /**
     * Converts the quote's shipping information to a line item.
     *
     * @param Quote $quote
     * @return \Wallee\Sdk\Model\LineItemCreate
     */
    protected function convertShippingLineItem($quote)
    {
        return $this->convertShippingLineItemInner($quote, $quote->getShippingAddress()
            ->getShippingAmount(),
            $quote->getShippingAddress()
                ->getShippingTaxAmount() + $quote->getShippingAddress()
                ->getShippingDiscountTaxCompensationAmount(), $quote->getShippingAddress()
                ->getShippingDiscountAmount(), $quote->getShippingAddress()
                ->getShippingDescription());
    }

    /**
     *
     * @param Quote\Item $entityItem
     * @return string
     */
    protected function getUniqueId($entityItem)
    {
        return $entityItem->getId();
    }

    /**
     * Gets the currency code of the given quote.
     *
     * @param Quote $quote
     * @return string
     */
    protected function getCurrencyCode($quote)
    {
        return $quote->getQuoteCurrencyCode();
    }
}