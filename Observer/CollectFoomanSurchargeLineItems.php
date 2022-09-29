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
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Tax\Model\Calculation as TaxCalculation;
use Wallee\Payment\Helper\Data as Helper;
use Wallee\Sdk\Model\LineItemCreate;
use Wallee\Sdk\Model\LineItemType;
use Wallee\Sdk\Model\TaxCreate;

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
     * @var Helper
     */
    private $helper;

    /**
     *
     * @param ObjectManagerInterface $objectManager
     * @param ModuleManager $moduleManager
     * @param TaxClassRepositoryInterface $taxClassRepository
     * @param TaxHelper $taxHelper
     * @param TaxCalculation $taxCalculation
     * @param CustomerGroupRegistry $groupRegistry
     * @param Helper $helper
     */
    public function __construct(ObjectManagerInterface $objectManager, ModuleManager $moduleManager,
        TaxClassRepositoryInterface $taxClassRepository, TaxHelper $taxHelper, TaxCalculation $taxCalculation,
        CustomerGroupRegistry $groupRegistry, Helper $helper)
    {
        $this->objectManager = $objectManager;
        $this->moduleManager = $moduleManager;
        $this->taxClassRepository = $taxClassRepository;
        $this->taxHelper = $taxHelper;
        $this->taxCalculation = $taxCalculation;
        $this->groupRegistry = $groupRegistry;
        $this->helper = $helper;
    }

    public function execute(Observer $observer)
    {
        /* @var Quote|Order|Invoice $entity */
        $entity = $observer->getEntity();
        $transport = $observer->getTransport();

        if ($this->moduleManager->isEnabled('Fooman_Surcharge')) {
            $transport->setData('items',
                \array_merge($transport->getData('items'), $this->convertFoomanSurchargeLineItems($entity)));
        }
    }

    /**
     *
     * @param Quote|Order|Invoice $entity
     * @return LineItemCreate[]
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
     *
     * @param Order $order
     * @return LineItemCreate[]
     */
    protected function convertOrderFoomanSurchargeLineItems(Order $order)
    {
        /* @var \Fooman\Totals\Model\OrderTotalManagement $orderTotalManagement */
        $orderTotalManagement = $this->objectManager->get('Fooman\Totals\Model\OrderTotalManagement');
        $surchargeCollection = $orderTotalManagement->getByOrderId($order->getId());

        $items = [];
        foreach ($surchargeCollection as $item) {
            if ($item->getAmount() <= 0) {
                continue;
            }
            $items[] = $this->createSurchargeLineItem($order, $order->getOrderCurrencyCode(), $item->getAmount(),
                $item->getTaxAmount(), $item->getTypeId(), $item->getLabel());
        }
        return $items;
    }

    /**
     *
     * @param Quote $quote
     * @return LineItemCreate[]
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
            $items[] = $this->createSurchargeLineItem($quote, $quote->getQuoteCurrencyCode(), $item->getAmount(),
                $item->getTaxAmount(), $item->getTypeId(), $item->getLabel());
        }
        return $items;
    }

    /**
     *
     * @param Invoice $invoice
     * @return LineItemCreate[]
     */
    protected function convertInvoiceFoomanSurchargeLineItems(Invoice $invoice)
    {
        /* @var \Fooman\Totals\Model\InvoiceTotalManagement $invoiceTotalManagement */
        $invoiceTotalManagement = $this->objectManager->get('Fooman\Totals\Model\InvoiceTotalManagement');
        $surchargeCollection = $invoiceTotalManagement->getByInvoiceId($invoice->getId());

        $items = [];
        foreach ($surchargeCollection as $item) {
            if ($item->getAmount() <= 0) {
                continue;
            }
            $items[] = $this->createSurchargeLineItem($invoice->getOrder(), $invoice->getOrderCurrencyCode(),
                $item->getAmount(), $item->getTaxAmount(), $item->getTypeId(), $item->getLabel());
        }
        return $items;
    }

    /**
     *
     * @param Quote|Order $entity
     * @param string $currency
     * @param float $amount
     * @param float $taxAmount
     * @param string $code
     * @param string $label
     * @return LineItemCreate
     */
    private function createSurchargeLineItem($entity, $currency, $amount, $taxAmount, $code, $label)
    {
        $surcharge = new LineItemCreate();
        $surcharge->setType(LineItemType::FEE);
        $surcharge->setAmountIncludingTax($this->helper->roundAmount($amount + $taxAmount, $currency));
        $surcharge->setSku('fooman-surcharge');
        $surcharge->setUniqueId('fooman_surcharge_' . $code);
        $surcharge->setName((string) $label);
        $surcharge->setQuantity(1);
        $surcharge->setShippingRequired(false);
        if ($taxAmount > 0) {
            $tax = $this->getTax($entity, $code);
            if ($tax instanceof TaxCreate) {
                $surcharge->setTaxes([
                    $tax
                ]);
            }
        }
        return $surcharge;
    }

    /**
     * Gets the tax for the surcharge.
     *
     * @param Quote|Order $entity
     * @param string $code
     * @return TaxCreate
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
        }
        $taxRateRequest = $this->taxCalculation->getRateRequest($entity->getShippingAddress(),
            $entity->getBillingAddress(), $taxClassId, $entity->getStore());

        /* @var \Fooman\Surcharge\Helper\Surcharge $surchargeHelper */
        $surchargeHelper = $this->objectManager->get('Fooman\Surcharge\Helper\Surcharge');
        $taxClassId = $surchargeHelper->getSurchargeTaxClassIdByTypeId($code);
        if ($taxClassId > 0) {
            $taxClass = $this->taxClassRepository->get($taxClassId);
            $taxRateRequest->setProductClassId($taxClassId);
            $rate = $this->taxCalculation->getRate($taxRateRequest);
            if ($rate > 0) {
                $tax = new TaxCreate();
                $tax->setRate($rate);
                $tax->setTitle($taxClass->getClassName());
                return $tax;
            }
        }
    }
}