<?php
namespace Wallee\Payment\Setup\Patch\Data;

use \Magento\Framework\Setup\Patch\DataPatchInterface;
use \Magento\Framework\Setup\Patch\PatchVersionInterface;
use \Magento\Framework\Module\Setup\Migration;
use \Magento\Framework\Setup\ModuleDataSetupInterface;
use \Magento\Sales\Model\Order\StatusFactory;
use \Magento\Sales\Model\ResourceModel\Order\Status as StatusResource;

/**
 * Class AddSetupData
 */
class AddSetupDataStateProcessing implements DataPatchInterface
{
    /**
     * @var \Magento\Sales\Model\Order\StatusFactory
     */
    private $statusFactory;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Status
     */
    private $statusResource;

    /**
     * @var \Magento\Framework\Setup\ModuleDataSetupInterface
     */
    protected $moduleDataSetup;

    /**
     * @param \Magento\Sales\Model\Order\StatusFactory $statusFactory
     * @param \Magento\Sales\Model\ResourceModel\Order\Status $statusResource
     * @param \Magento\Framework\Setup\ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        StatusFactory $statusFactory,
        StatusResource $statusResource,
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->statusFactory = $statusFactory;
        $this->statusResource = $statusResource;
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * @inheritDoc
     *
     *  It will create and remove some status/states.
     *  We've rolled back the first patch, which means we're using the processing status again.
     *  Also I'm making sure we're setting processing, shipped_wallee and  pending_payment as default.
     *
     * @return $this
     */
    public function apply()
    {
        $connection = $this->moduleDataSetup->getConnection();
        $this->moduleDataSetup->getConnection()->startSetup();

        $statuses = [
            ['status' => 'pending', 'label' => 'Hold Delivery']
        ];

        foreach ($statuses as $statusData) {
            $status = $this->statusFactory->create();
            $status->setData($statusData);
            $this->statusResource->save($status);
            $status->assignState('pending', $statusData['status'], true);
        }

        $tableName = $this->moduleDataSetup->getTable('sales_order_status_state');

        $connection->update(
            $tableName,
            ['is_default' => 1, 'visible_on_front' => 0],
            ['status = ?' => 'pending_payment']
        );

        $connection->update(
            $tableName,
            ['state' => 'shipped_wallee', 'is_default' => 1],
            ['status = ?' => 'shipped_wallee']
        );

        $connection->update(
            $tableName,
            ['state' => 'processing', 'is_default' => 1],
            ['status = ?' => 'processing']
        );

        $stateToRemove = 'processing_wallee';
        $removed = $this->statusFactory->create();
        $this->statusResource->load($removed, $stateToRemove, 'status');

        if ($removed->getStatus()) {
            $this->statusResource->delete($removed);
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases()
    {
        return [];
    }
}
