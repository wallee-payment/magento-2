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
namespace Wallee\Payment\Model\Message;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Notification\MessageInterface;
use Magento\Store\Model\ScopeInterface;
use Wallee\Payment\Model\Service\ManualTaskService;

/**
 * System message to inform about manual tasks in wallee.
 */
class ManualTask implements MessageInterface
{

    /**
     *
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     *
     * @var ManualTaskService
     */
    private $manualTaskService;

    /**
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param ManualTaskService $manualTaskService
     */
    public function __construct(ScopeConfigInterface $scopeConfig, ManualTaskService $manualTaskService)
    {
        $this->scopeConfig = $scopeConfig;
        $this->manualTaskService = $manualTaskService;
    }

    public function getSeverity()
    {
        return self::SEVERITY_MINOR;
    }

    public function getIdentity()
    {
        return \md5('WLE_MANUAL_TASK');
    }

    public function getText()
    {
        $numberOfManualTasks = $this->manualTaskService->getNumberOfManualTasks();
        $totalNumberOfManualTasks = \array_sum($this->manualTaskService->getNumberOfManualTasks());
        $url = $this->buildManualTaskUrl(\count($numberOfManualTasks) == 1 ? \key($numberOfManualTasks) : null);
        if ($totalNumberOfManualTasks == 1) {
            return \__('There is a <a href="%1" target="_blank">manual task</a> that needs your attention.', $url);
        } else {
            return \__('There are <a href="%1" target="_blank">%2 manual tasks</a> that need your attention.', $url,
                $totalNumberOfManualTasks);
        }
    }

    public function isDisplayed()
    {
        return \array_sum($this->manualTaskService->getNumberOfManualTasks()) > 0;
    }

    /**
     *
     * @param int $websiteId
     * @return string
     */
    private function buildManualTaskUrl($websiteId = null)
    {
        $url = \rtrim($this->scopeConfig->getValue('wallee_payment/general/base_gateway_url'), '/');
        if ($websiteId != null) {
            $spaceId = $this->scopeConfig->getValue('wallee_payment/general/space_id',
                ScopeInterface::SCOPE_WEBSITE, $websiteId);
            $url .= '/s/' . $spaceId . '/manual-task/list';
        }
        return $url;
    }
}