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
namespace Wallee\Payment\Plugin\Sales\Model\AdminOrder;

use Wallee\Payment\Model\Payment\Method\Adapter;

class Create
{

    public function beforeCreateOrder(\Magento\Sales\Model\AdminOrder\Create $subject)
    {
        if ($subject->getQuote()
            ->getPayment()
            ->getMethodInstance() instanceof Adapter) {
            $subject->setSendConfirmation(false);
        }
    }
}