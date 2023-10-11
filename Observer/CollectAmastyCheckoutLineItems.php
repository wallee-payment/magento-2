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

use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Wallee\Payment\Helper\Data as Helper;
use Wallee\Sdk\Model\LineItemCreate;
use Wallee\Sdk\Model\LineItemType;
use Magento\Framework\Module\ModuleListInterface;

/**
 * Observer to collect the line items for the amasty checkout.
 */
class CollectAmastyCheckoutLineItems implements ObserverInterface
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
     * @var Helper
     */
    private $helper;

    /**
     *
     * @var ModuleListInterface
     */
    protected $_moduleList;

    /**
     *
     * @param ObjectManagerInterface $objectManager
     * @param ModuleManager $moduleManager
     * @param Helper $helper
     * @param ModuleListInterface $moduleList
     */
    public function __construct(ObjectManagerInterface $objectManager, ModuleManager $moduleManager, Helper $helper, ModuleListInterface $moduleList)
    {
        $this->objectManager = $objectManager;
        $this->moduleManager = $moduleManager;
        $this->helper = $helper;
        $this->_moduleList = $moduleList;
    }

    public function execute(Observer $observer)
    {
        /* @var Quote|Order|Invoice $entity */
        $entity = $observer->getEntity();
        $transport = $observer->getTransport();

        if ($this->moduleManager->isEnabled('Amasty_Checkout')) {
            $transport->setData('items',
                \array_merge($transport->getData('items'), $this->convertAmastyCheckoutLineItems($entity)));
        }
    }

    /**
     *
     * @param Quote|Order|Invoice $entity
     * @return LineItemCreate[]
     */
    protected function convertAmastyCheckoutLineItems($entity)
    {
        $items = [];
        $giftWrapLineItem = $this->convertGiftWrapLineItem($entity);
        if ($giftWrapLineItem != null) {
            $items[] = $giftWrapLineItem;
        }
        return $items;
    }

    /**
     *
     * @param Quote|Order|Invoice $entity
     * @return LineItemCreate
     */
    protected function convertGiftWrapLineItem($entity)
    {
        $moduleInfo = $this->_moduleList->getOne('Amasty_Checkout');

        if (version_compare($moduleInfo['setup_version'], '4.0.0', '>=')) {
            $feeRepository = $this->objectManager->get('Amasty\CheckoutCore\Api\FeeRepositoryInterface');
        } else {
            $feeRepository = $this->objectManager->get('Amasty\Checkout\Api\FeeRepositoryInterface');
        }

        $currency = null;
        $fee = null;
        if ($entity instanceof Order) {
            $currency = $entity->getOrderCurrencyCode();
            $fee = $feeRepository->getByQuoteId($entity->getQuoteId());
        } elseif ($entity instanceof Quote) {
            $currency = $entity->getQuoteCurrencyCode();
            $fee = $feeRepository->getByQuoteId($entity->getId());
        } elseif ($entity instanceof Invoice) {
            $currency = $entity->getOrderCurrencyCode();
            $fee = $feeRepository->getByOrderId($entity->getOrderId());
        }

        if ($fee->getId() && $fee->getAmount() > 0) {
            return $this->createGiftWrapLineItem($fee->getAmount(), $currency);
        }
    }

    /**
     *
     * @param float $amount
     * @param string $currency
     * @return LineItemCreate
     */
    private function createGiftWrapLineItem($amount, $currency)
    {
        $surcharge = new LineItemCreate();
        $surcharge->setType(LineItemType::FEE);
        $surcharge->setAmountIncludingTax($this->helper->roundAmount($amount, $currency));
        $surcharge->setSku('gift-wrap');
        $surcharge->setUniqueId('amasty_gift_wrap');
        $surcharge->setName((string) \__('Gift Wrap'));
        $surcharge->setQuantity(1);
        $surcharge->setShippingRequired(false);
        return $surcharge;
    }
}