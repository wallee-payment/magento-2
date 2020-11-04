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
namespace Wallee\Payment\Model\Webhook\Listener\TransactionCompletion;

use Wallee\Payment\Model\Webhook\Listener\AbstractOrderRelatedCommand;

/**
 * Abstract webhook listener command to handle transaction completions.
 */
abstract class AbstractCommand extends AbstractOrderRelatedCommand
{
}