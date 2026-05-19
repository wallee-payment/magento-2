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
declare(strict_types=1);

namespace Wallee\Payment\Plugin\Hyva\Checkout\ViewModel\Payment;

use Hyva\Checkout\ViewModel\Checkout\Payment\MethodList;
use Magento\Framework\View\Element\Template;
use Magento\Payment\Model\MethodInterface;

class MethodListPlugin
{
    /**
     * Fallback to the generic wallee payment block if the specific dynamically generated method block isn't found.
     *
     * @param MethodList $subject
     * @param \Magento\Framework\View\Element\AbstractBlock|false $result
     * @param Template $block
     * @param MethodInterface $method
     * @return \Magento\Framework\View\Element\AbstractBlock|false
     */
    public function afterGetMethodBlock(MethodList $subject, $result, Template $block, MethodInterface $method)
    {
        // If Hyva already found a matched layout block, return it unmodified.
        if ($result !== false) {
            return $result;
        }

        // For dynamic WhitelabelMachineName payment methods, map to our base template block.
        if (strpos($method->getCode(), 'wallee_payment_') === 0) {
            $child = $block->getChildBlock('checkout.payment.method.wallee');
            if ($child) {
                return $child->setData('method', $method);
            }
        }

        return $result;
    }
}
