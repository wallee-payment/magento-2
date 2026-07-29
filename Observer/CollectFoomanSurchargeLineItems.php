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

use Magento\Customer\Model\GroupRegistry as CustomerGroupRegistry;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Tax\Api\TaxClassRepositoryInterface;
use Magento\Tax\Model\Calculation as TaxCalculation;
use Wallee\Payment\Helper\Data as Helper;
use Wallee\PluginCore\LineItem\LineItem as CoreLineItem;
use Wallee\PluginCore\Tax\Tax as CoreTax;
use Psr\Log\LoggerInterface;

/**
 * Observer to collect the line items for the fooman surcharges.
 */
class CollectFoomanSurchargeLineItems implements ObserverInterface
{

    /**
     *
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     *
     * @var ModuleManager
     */
    private $moduleManager;

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
     * @var Helper
     */
    private $helper;

    /**
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     *
     * @param ObjectManagerInterface $objectManager
     * @param ModuleManager $moduleManager
     * @param TaxClassRepositoryInterface $taxClassRepository
     * @param TaxCalculation $taxCalculation
     * @param CustomerGroupRegistry $groupRegistry
     * @param Helper $helper
     * @param LoggerInterface $logger
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        ModuleManager $moduleManager,
        TaxClassRepositoryInterface $taxClassRepository,
        TaxCalculation $taxCalculation,
        CustomerGroupRegistry $groupRegistry,
        Helper $helper,
        LoggerInterface $logger
    ) {
        $this->objectManager = $objectManager;
        $this->moduleManager = $moduleManager;
        $this->taxClassRepository = $taxClassRepository;
        $this->taxCalculation = $taxCalculation;
        $this->groupRegistry = $groupRegistry;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    /**
     * Append Fooman surcharges to the line items.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        /* @var Quote|Order|Invoice $entity */
        $entity = $observer->getEntity();
        $transport = $observer->getTransport();

        if ($this->moduleManager->isEnabled('Fooman_Surcharge')) {
            $transport->setData(
                'items',
                \array_merge($transport->getData('items'), $this->convertFoomanSurchargeLineItems($entity))
            );
        }
    }

    /**
     * Convert Fooman surcharge totals into line items.
     *
     * @param Quote|Order|Invoice $entity
     * @return CoreLineItem[]
     */
    protected function convertFoomanSurchargeLineItems($entity)
    {
        if ($entity instanceof Order) {
            return $this->convertOrderFoomanSurchargeLineItems($entity);
        } elseif ($entity instanceof Quote) {
            return $this->convertQuoteFoomanSurchargeLineItems($entity);
        } elseif ($entity instanceof Invoice) {
            return $this->convertInvoiceFoomanSurchargeLineItems($entity);
        } else {
            return [];
        }
    }

    /**
     * Build surcharge line items for an order.
     *
     * @param Order $order
     * @return CoreLineItem[]
     */
    protected function convertOrderFoomanSurchargeLineItems(Order $order)
    {
        /* @var \Fooman\Totals\Model\OrderTotalManagement $orderTotalManagement */
        $orderTotalManagement = $this->objectManager->get(\Fooman\Totals\Model\OrderTotalManagement::class);
        $surchargeCollection = $orderTotalManagement->getByOrderId($order->getId());

        $items = [];
        foreach ($surchargeCollection as $item) {
            if ($item->getAmount() <= 0) {
                continue;
            }
            $items[] = $this->createSurchargeLineItem(
                $order,
                $order->getOrderCurrencyCode(),
                $item->getAmount(),
                $item->getTaxAmount(),
                $item->getTypeId(),
                $item->getLabel()
            );
        }
        return $items;
    }

    /**
     * Build surcharge line items for a quote.
     *
     * @param Quote $quote
     * @return CoreLineItem[]
     */
    protected function convertQuoteFoomanSurchargeLineItems(Quote $quote)
    {
        if (! $quote->getShippingAddress()->getExtensionAttributes()) {
            return [];
        }

        if (! $quote->getShippingAddress()
            ->getExtensionAttributes()
            ->getFoomanTotalGroup()) {
            return [];
        }

        $items = [];
        foreach ($quote->getShippingAddress()
            ->getExtensionAttributes()
            ->getFoomanTotalGroup()
            ->getItems() as $item) {
            if ($item->getAmount() <= 0) {
                continue;
            }
            $items[] = $this->createSurchargeLineItem(
                $quote,
                $quote->getQuoteCurrencyCode(),
                $item->getAmount(),
                $item->getTaxAmount(),
                $item->getTypeId(),
                $item->getLabel()
            );
        }
        return $items;
    }

    /**
     * Build surcharge line items for an invoice.
     *
     * @param Invoice $invoice
     * @return CoreLineItem[]
     */
    protected function convertInvoiceFoomanSurchargeLineItems(Invoice $invoice)
    {
        /* @var \Fooman\Totals\Model\InvoiceTotalManagement $invoiceTotalManagement */
        $invoiceTotalManagement = $this->objectManager->get(\Fooman\Totals\Model\InvoiceTotalManagement::class);
        $surchargeCollection = $invoiceTotalManagement->getByInvoiceId($invoice->getId());

        $items = [];
        foreach ($surchargeCollection as $item) {
            if ($item->getAmount() <= 0) {
                continue;
            }
            $items[] = $this->createSurchargeLineItem(
                $invoice->getOrder(),
                $invoice->getOrderCurrencyCode(),
                $item->getAmount(),
                $item->getTaxAmount(),
                $item->getTypeId(),
                $item->getLabel()
            );
        }
        return $items;
    }

    /**
     * Create a surcharge line item.
     *
     * @param Quote|Order $entity
     * @param string $currency
     * @param float $amount
     * @param float $taxAmount
     * @param string $code
     * @param string $label
     * @return CoreLineItem
     */
    private function createSurchargeLineItem($entity, $currency, $amount, $taxAmount, $code, $label)
    {
        $surcharge = new CoreLineItem();
        $surcharge->type = CoreLineItem::TYPE_FEE;
        $surcharge->amountIncludingTax = (float) $this->helper->roundAmount($amount + $taxAmount, $currency);
        $surcharge->unitPriceIncludingTax = $surcharge->amountIncludingTax;
        $surcharge->sku = 'fooman-surcharge';
        $surcharge->uniqueId = 'fooman_surcharge_' . $code;
        $surcharge->name = (string) $label;
        $surcharge->quantity = 1.0;
        $surcharge->shippingRequired = false;
        if ($taxAmount > 0) {
            $tax = $this->getTax($entity, $code);
            if ($tax instanceof CoreTax) {
                $surcharge->addTax($tax);
            }
        }
        return $surcharge;
    }

    /**
     * Gets the tax for the surcharge.
     *
     * @param Quote|Order $entity
     * @param string $code
     * @return CoreTax|null
     */
    protected function getTax($entity, $code)
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
            $this->logger->debug('Customer group not found.');
        }
        $taxRateRequest = $this->taxCalculation->getRateRequest(
            $entity->getShippingAddress(),
            $entity->getBillingAddress(),
            $taxClassId,
            $entity->getStore()
        );

        /* @var \Fooman\Surcharge\Helper\Surcharge $surchargeHelper */
        $surchargeHelper = $this->objectManager->get(\Fooman\Surcharge\Helper\Surcharge::class);
        $taxClassId = $surchargeHelper->getSurchargeTaxClassIdByTypeId($code);
        if ($taxClassId > 0) {
            $taxClass = $this->taxClassRepository->get($taxClassId);
            $taxRateRequest->setProductClassId($taxClassId);
            $rate = $this->taxCalculation->getRate($taxRateRequest);
            if ($rate > 0) {
                try {
                    return new CoreTax((string) $taxClass->getClassName(), (float) $rate);
                } catch (\InvalidArgumentException $e) {
                    $this->logger->warning('Skipping surcharge tax with invalid title: ' . $e->getMessage());
                    return null;
                }
            }
        }
        return null;
    }
}
