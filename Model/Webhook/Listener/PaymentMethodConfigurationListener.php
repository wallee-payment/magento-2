<?php
/**
 wallee Magento 2
 *
 * This Magento 2 extension enables to process payments with wallee (https://www.wallee.com).
 *
 * @package Wallee_Payment
 * @author wallee AG (https://www.wallee.com)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)

 */
namespace Wallee\Payment\Model\Webhook\Listener;

use Wallee\Payment\Api\PaymentMethodConfigurationManagementInterface;
use Wallee\Payment\Model\Webhook\ListenerInterface;
use Wallee\Payment\Model\Webhook\Request;

/**
 * Webhook listener to handle payment method configurations.
 */
class PaymentMethodConfigurationListener implements ListenerInterface
{

    /**
     *
     * @var PaymentMethodConfigurationManagementInterface
     */
    private $paymentMethodConfigurationManagement;

    /**
     *
     * @param PaymentMethodConfigurationManagementInterface $paymentMethodConfigurationManagement
     */
    public function __construct(PaymentMethodConfigurationManagementInterface $paymentMethodConfigurationManagement)
    {
        $this->paymentMethodConfigurationManagement = $paymentMethodConfigurationManagement;
    }

    public function execute(Request $request)
    {
        $this->paymentMethodConfigurationManagement->synchronize();
    }
}