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

use Magento\Framework\Registry;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Wallee\Payment\Model\Payment\Method\Adapter;

/**
 * Observer to store the invoice on capture.
 */
class CapturePayment implements ObserverInterface
{

    /**
     *
     * @var Registry
     */
    private $registry;

    /**
     *
     * @param Registry $registry
     */
    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
    }

    public function execute(Observer $observer)
    {
        $this->registry->unregister(Adapter::CAPTURE_INVOICE_REGISTRY_KEY);
        $this->registry->register(Adapter::CAPTURE_INVOICE_REGISTRY_KEY, $observer->getInvoice());
    }
}