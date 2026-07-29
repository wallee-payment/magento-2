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
namespace Wallee\Payment\Model\Service;

use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface as StorageWriter;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Wallee\PluginCore\ManualTask\ManualTaskGatewayInterface;
use Wallee\PluginCore\ManualTask\State as ManualTaskState;

/**
 * Service to handle manual tasks.
 */
class ManualTaskService
{

    public const CONFIG_KEY = 'wallee_payment/general/manual_tasks';

    /**
     *
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     *
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     *
     * @var CollectionFactory
     */
    private $configCollectionFactory;

    /**
     *
     * @var StorageWriter
     */
    private $configWriter;

    /**
     *
     * @var ManualTaskGatewayInterface
     */
    private $manualTaskGateway;

    /**
     *
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param CollectionFactory $configCollectionFactory
     * @param StorageWriter $configWriter
     * @param ManualTaskGatewayInterface $manualTaskGateway
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        CollectionFactory $configCollectionFactory,
        StorageWriter $configWriter,
        ManualTaskGatewayInterface $manualTaskGateway
    ) {
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->configCollectionFactory = $configCollectionFactory;
        $this->configWriter = $configWriter;
        $this->manualTaskGateway = $manualTaskGateway;
    }

    /**
     * Gets the number of open manual tasks by website.
     *
     * @return array
     */
    public function getNumberOfManualTasks()
    {
        $numberOfManualTasks = [];
        foreach ($this->storeManager->getWebsites() as $website) {
            $websiteNumberOfManualTasks = $this->configCollectionFactory->create()
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
        foreach ($this->storeManager->getWebsites() as $website) {
            $spaceId = $this->scopeConfig->getValue(
                'wallee_payment/general/space_id',
                ScopeInterface::SCOPE_WEBSITE,
                $website->getId()
            );
            if ($spaceId && ! \in_array($spaceId, $spaceIds)) {
                $websiteNumberOfManualTasks = $this->manualTaskGateway->countByState(
                    (int)$spaceId,
                    ManualTaskState::OPEN
                );
                $this->configWriter->save(
                    self::CONFIG_KEY,
                    $websiteNumberOfManualTasks,
                    ScopeInterface::SCOPE_WEBSITE,
                    $website->getId()
                );
                if (! empty($websiteNumberOfManualTasks)) {
                    $numberOfManualTasks[$website->getId()] = $websiteNumberOfManualTasks;
                }
                $spaceIds[] = $spaceId;
            } else {
                $this->configWriter->delete(self::CONFIG_KEY, ScopeInterface::SCOPE_WEBSITE, $website->getId());
            }
        }
        return $numberOfManualTasks;
    }
}
