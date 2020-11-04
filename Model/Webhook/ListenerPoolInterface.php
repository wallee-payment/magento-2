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
namespace Wallee\Payment\Model\Webhook;

/**
 * Webhook listener pool interface.
 */
interface ListenerPoolInterface
{

    /**
     * Retrieves listener.
     *
     * @param string $listenerCode
     * @return ListenerInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function get($listenerCode);
}