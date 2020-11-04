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
namespace Wallee\Payment\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Observer to synchronize and update data when the configuration is saved.
 */
class SaveConfig implements ObserverInterface
{

    /**
     *
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     *
     * @var EventManager
     */
    private $eventManager;

    /**
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param EventManager $eventManager
     */
    public function __construct(ScopeConfigInterface $scopeConfig, EventManager $eventManager)
    {
        $this->scopeConfig = $scopeConfig;
        $this->eventManager = $eventManager;
    }

    public function execute(Observer $observer)
    {
        $userId = $this->scopeConfig->getValue('wallee_payment/general/api_user_id',
            ScopeInterface::SCOPE_STORE);
        $applicationKey = $this->scopeConfig->getValue('wallee_payment/general/api_user_id',
            ScopeInterface::SCOPE_STORE);
        if ($userId && $applicationKey) {
            try {
                $this->eventManager->dispatch('wallee_payment_config_synchronize');
            } catch (\Exception $exception) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    \__('Synchronizing with wallee failed: %1', $exception->getMessage()));
            }
        }
    }
}