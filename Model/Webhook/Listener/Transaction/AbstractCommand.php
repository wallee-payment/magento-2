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
namespace Wallee\Payment\Model\Webhook\Listener\Transaction;

use Wallee\Payment\Model\Webhook\Listener\AbstractOrderRelatedCommand;

/**
 * Abstract webhook listener command to handle transactions.
 */
abstract class AbstractCommand extends AbstractOrderRelatedCommand
{
}