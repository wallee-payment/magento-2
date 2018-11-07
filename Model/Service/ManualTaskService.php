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
namespace Wallee\Payment\Model\Service;

use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface as StorageWriter;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Wallee\Payment\Helper\Data as Helper;
use Wallee\Payment\Model\ApiClient;
use Wallee\Sdk\Model\ManualTaskState;
use Wallee\Sdk\Service\ManualTaskService as ManualTaskApiService;

/**
 * Service to handle manual tasks.
 */
class ManualTaskService
{

    const CONFIG_KEY = 'wallee_payment/general/manual_tasks';

    /**
     *
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     *
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     *
     * @var CollectionFactory
     */
    protected $_configCollectionFactory;

    /**
     *
     * @var StorageWriter
     */
    protected $_configWriter;

    /**
     *
     * @var Helper
     */
    protected $_helper;

    /**
     *
     * @var ApiClient
     */
    protected $_apiClient;

    /**
     *
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param CollectionFactory $configCollectionFactory
     * @param StorageWriter $configWriter
     * @param Helper $helper
     * @param ApiClient $apiClient
     */
    public function __construct(StoreManagerInterface $storeManager, ScopeConfigInterface $scopeConfig,
        CollectionFactory $configCollectionFactory, StorageWriter $configWriter, Helper $helper, ApiClient $apiClient)
    {
        $this->_storeManager = $storeManager;
        $this->_scopeConfig = $scopeConfig;
        $this->_configCollectionFactory = $configCollectionFactory;
        $this->_configWriter = $configWriter;
        $this->_helper = $helper;
        $this->_apiClient = $apiClient;
    }

    /**
     * Gets the number of open manual tasks by website.
     *
     * @return array
     */
    public function getNumberOfManualTasks()
    {
        $numberOfManualTasks = [];
        foreach ($this->_storeManager->getWebsites() as $website) {
            $websiteNumberOfManualTasks = $this->_configCollectionFactory->create()
                ->addFieldToFilter('scope', ScopeInterface::SCOPE_WEBSITE)
                ->addFieldToFilter('scope_id', $website->getId())
                ->addFieldToFilter('path', self::CONFIG_KEY)
                ->getFirstItem()
                ->getValue();
            if (! empty($websiteNumberOfManualTasks)) {
                $numberOfManualTasks[$website->getId()] = $websiteNumberOfManualTasks;
            }
        }
        return $numberOfManualTasks;
    }

    /**
     * Updates the number of open manual tasks.
     *
     * @return array
     */
    public function update()
    {
        $numberOfManualTasks = [];
        $spaceIds = [];
        foreach ($this->_storeManager->getWebsites() as $website) {
            $spaceId = $this->_scopeConfig->getValue('wallee_payment/general/space_id',
                ScopeInterface::SCOPE_WEBSITE, $website->getId());
            if ($spaceId && ! in_array($spaceId, $spaceIds)) {
                $websiteNumberOfManualTasks = $this->_apiClient->getService(ManualTaskApiService::class)->count(
                    $spaceId, $this->_helper->createEntityFilter('state', ManualTaskState::OPEN));
                $this->_configWriter->save(self::CONFIG_KEY, $websiteNumberOfManualTasks, ScopeInterface::SCOPE_WEBSITE,
                    $website->getId());
                if (! empty($websiteNumberOfManualTasks)) {
                    $numberOfManualTasks[$website->getId()] = $websiteNumberOfManualTasks;
                }
                $spaceIds[] = $spaceId;
            } else {
                $this->_configWriter->delete(self::CONFIG_KEY, ScopeInterface::SCOPE_WEBSITE, $website->getId());
            }
        }
        return $numberOfManualTasks;
    }
}