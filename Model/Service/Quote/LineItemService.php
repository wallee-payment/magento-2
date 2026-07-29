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
namespace Wallee\Payment\Model\Service\Quote;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Product\Configuration as ProductConfigurationHelper;
use Magento\Customer\Model\GroupRegistry as CustomerGroupRegistry;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Quote\Model\Quote;
use Magento\Tax\Api\TaxClassRepositoryInterface;
use Magento\Tax\Model\Calculation as TaxCalculation;
use Wallee\Payment\Helper\Data as Helper;
use Wallee\Payment\Helper\LineItem as LineItemHelper;
use Wallee\Payment\Model\Service\AbstractLineItemService;
use Wallee\PluginCore\LineItem\LineItemAttribute as CoreLineItemAttribute;
use Psr\Log\LoggerInterface;

/**
 * Service to handle line items in quote context.
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
     * @var ProductConfigurationHelper
     */
    private $productConfigurationHelper;

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
     * @param ProductConfigurationHelper $productConfigurationHelper
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
        ProductConfigurationHelper $productConfigurationHelper,
        LoggerInterface $logger,
        GiftCardAccountWrapper $giftCardAccountManagement
    ) {
        parent::__construct(
            $helper,
            $lineItemHelper,
            $scopeConfig,
            $taxClassRepository,
            $taxCalculation,
            $groupRegistry,
            $eventManager,
            $productRepository,
            $logger,
            $giftCardAccountManagement
        );
        $this->helper = $helper;
        $this->lineItemHelper = $lineItemHelper;
        $this->productConfigurationHelper = $productConfigurationHelper;
        $this->logger = $logger;
    }

    /**
     * Converts the quote's items to line items. Total consistency is enforced
     * downstream by plugin-core's LineItemConsistencyService, so we only
     * normalise unique IDs here.
     *
     * @param Quote $quote
     * @return \Wallee\PluginCore\LineItem\LineItem[]
     */
    public function convertQuoteLineItems(Quote $quote)
    {
        return $this->lineItemHelper->ensureUniqueIds($this->convertLineItems($quote));
    }

    /**
     * Gets the attributes for the given quote item.
     *
     * @param Quote\Item $entityItem
     * @return CoreLineItemAttribute[]
     */
    protected function getAttributes($entityItem)
    {
        $attributes = [];
        foreach ($this->productConfigurationHelper->getOptions($entityItem) as $option) {
            $value = $option['value'];
            if (\is_array($value)) {
                $value = \current($value);
            }

            $key = $this->getAttributeKey($option);
            $attributes[$key] = new CoreLineItemAttribute(
                $key,
                $this->helper->fixLength($this->helper->getFirstLine($option['label']), 512),
                $this->helper->getFirstLine($value)
            );
        }

        return \array_merge(
            $attributes,
            $this->getCustomAttributes($entityItem->getProductId(), $entityItem->getQuote()
            ->getStoreId())
        );
    }

    /**
     * Converts the quote's shipping information to a line item.
     *
     * @param Quote $quote
     * @return \Wallee\PluginCore\LineItem\LineItem|null
     */
    protected function convertShippingLineItem($quote)
    {
        return $this->convertShippingLineItemInner(
            $quote,
            $quote->getShippingAddress()
            ->getShippingAmount(),
            $quote->getShippingAddress()
                ->getShippingTaxAmount() + $quote->getShippingAddress()
                ->getShippingDiscountTaxCompensationAmount(),
            $quote->getShippingAddress()
                ->getShippingDiscountAmount(),
            $quote->getShippingAddress()
            ->getShippingDescription()
        );
    }

    /**
     * Gets the unique ID of the given item.
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
