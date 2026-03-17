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
namespace Wallee\Payment\Api;

use Wallee\Sdk\Model\PaymentMethodConfiguration;

interface PaymentMethodConfigurationManagementInterface
{

    /**
     * Synchronizes the payment method configurations from wallee.
     *
     * @return void
     */
    public function synchronize();

    /**
     * Updates the payment method configuration information.
     *
     * @param \Wallee\Sdk\Model\PaymentMethodConfiguration $configuration
     * @return void
     */
    public function update(PaymentMethodConfiguration $configuration);
}
