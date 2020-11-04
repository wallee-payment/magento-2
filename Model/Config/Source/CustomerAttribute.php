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
namespace Wallee\Payment\Model\Config\Source;

/**
 * Provides customer attributes as array options.
 */
class CustomerAttribute implements \Magento\Framework\Option\ArrayInterface
{

    /**
     *
     * @var \Magento\Customer\Model\Form
     */
    private $customerForm;

    /**
     *
     * @param \Magento\Customer\Model\Form $customerForm
     */
    public function __construct(\Magento\Customer\Model\Form $customerForm)
    {
        $this->customerForm = $customerForm;
    }

    public function toOptionArray()
    {
        $options = [];
        $attributes = $this->customerForm->setFormCode('adminhtml_customer')->getAttributes();
        /** @var $attribute \Magento\Eav\Model\Attribute */
        foreach ($attributes as $attribute) {
            $options[] = [
                'value' => $attribute->getAttributeCode(),
                'label' => $attribute->getFrontend()->getLocalizedLabel()
            ];
        }
        return $options;
    }
}