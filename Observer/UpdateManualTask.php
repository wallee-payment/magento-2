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
use Wallee\Payment\Model\Service\ManualTaskService;

/**
 * Observer to update the manual tasks.
 */
class UpdateManualTask implements ObserverInterface
{

    /**
     *
     * @var ManualTaskService
     */
    protected $_manualTaskService;

    /**
     *
     * @param ManualTaskService $manualTaskService
     */
    public function __construct(ManualTaskService $manualTaskService)
    {
        $this->_manualTaskService = $manualTaskService;
    }

    public function execute(Observer $observer)
    {
        $this->_manualTaskService->update();
    }
}