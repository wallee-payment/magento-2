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

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Wallee\Payment\Model\Service\WebhookService;

/**
 * Observer to install webhooks.
 */
class InstallWebhook implements ObserverInterface
{

    /**
     *
     * @var WebhookService
     */
    protected $_webhookService;

    /**
     *
     * @param WebhookService $webhookService
     */
    public function __construct(WebhookService $webhookService)
    {
        $this->_webhookService = $webhookService;
    }

    public function execute(Observer $observer)
    {
        $this->_webhookService->install();
    }
}