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
namespace Wallee\Payment\Model\Service\Quote;

use Magento\Catalog\Helper\Product\Configuration as ProductConfigurationHelper;
use Magento\Customer\Model\GroupRegistry as CustomerGroupRegistry;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Quote\Model\Quote;
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
     * @var ProductConfigurationHelper
     */
    protected $_productConfigurationHelper;

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
     * @param ProductConfigurationHelper $productConfigurationHelper
     */
    public function __construct(Helper $helper, LineItemHelper $lineItemHelper, ScopeConfigInterface $scopeConfig,
        TaxClassRepositoryInterface $taxClassRepository, TaxHelper $taxHelper, TaxCalculation $taxCalculation,
        CustomerGroupRegistry $groupRegistry, EventManagerInterface $eventManager,
        ProductConfigurationHelper $productConfigurationHelper)
    {
        parent::__construct($helper, $lineItemHelper, $scopeConfig, $taxClassRepository, $taxHelper, $taxCalculation,
            $groupRegistry, $eventManager);
        $this->_productConfigurationHelper = $productConfigurationHelper;
    }

    /**
     * Convers the quote's items to line items.
     *
     * @param Quote $quote
     * @return \Wallee\Sdk\Model\LineItemCreate[]
     */
    public function convertQuoteLineItems(Quote $quote)
    {
        return $this->_lineItemHelper->correctLineItems($this->convertLineItems($quote), $quote->getGrandTotal(),
            $this->getCurrencyCode($quote));
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
        foreach ($this->_productConfigurationHelper->getOptions($entityItem) as $option) {
            $value = $option['value'];
            if (\is_array($value)) {
                $value = \current($value);
            }

            $attribute = new LineItemAttributeCreate();
            $attribute->setLabel($this->_helper->fixLength($this->_helper->getFirstLine($option['label']), 512));
            $attribute->setValue($this->_helper->fixLength($this->_helper->getFirstLine($value), 512));
            $attributes[$this->getAttributeKey($option)] = $attribute;
        }
        return $attributes;
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
            ->getShippingInclTax(), $quote->getShippingAddress()
            ->getShippingDescription(), $quote->getShippingAddress()
            ->getShippingDiscountAmount());
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