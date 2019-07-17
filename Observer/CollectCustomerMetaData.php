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
namespace Wallee\Payment\Observer;

use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Observer to collect the customer's meta data for the transaction.
 */
class CollectCustomerMetaData implements ObserverInterface
{

    /**
     *
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     *
     * @var CustomerRegistry
     */
    private $customerRegistry;

    /**
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param CustomerRegistry $customerRegistry
     */
    public function __construct(ScopeConfigInterface $scopeConfig, CustomerRegistry $customerRegistry)
    {
        $this->scopeConfig = $scopeConfig;
        $this->customerRegistry = $customerRegistry;
    }

    public function execute(Observer $observer)
    {
        /* @var \Magento\Sales\Model\Order $order */
        $order = $observer->getOrder();
        $transport = $observer->getTransport();

        if (! empty($order->getCustomerId())) {
            $transport->setData('metaData',
                \array_merge($transport->getData('metaData'),
                    $this->collectCustomerMetaData($this->customerRegistry->retrieve($order->getCustomerId()))));
        }
    }

    /**
     * Collects the data that is to be transmitted to the gateway as transaction meta data.
     *
     * @param Customer $customer
     * @return array
     */
    protected function collectCustomerMetaData(Customer $customer)
    {
        $metaData = [];
        $attributeCodesConfig = $this->scopeConfig->getValue(
            'wallee_payment/meta_data/customer_attributes', ScopeInterface::SCOPE_STORE,
            $customer->getStoreId());
        if (! empty($attributeCodesConfig)) {
            $attributeCodes = \explode(',', $attributeCodesConfig);
            foreach ($attributeCodes as $attributeCode) {
                $value = $customer->getData($attributeCode);
                if ($value !== null && $value !== "" && $value !== false) {
                    $metaData['customer_' . $attributeCode] = $value;
                }
            }
        }
        return $metaData;
    }
}