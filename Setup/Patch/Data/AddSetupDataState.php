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
class AddSetupDataState implements DataPatchInterface
{
    /**
     * @var \Magento\Framework\Setup\ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var \Magento\Sales\Model\Order\StatusFactory
     */
    private $statusFactory;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Status
     */
    private $statusResource;

    /**
     * @param \Magento\Framework\Setup\ModuleDataSetupInterface $moduleDataSetup
     * @param \Magento\Sales\Model\Order\StatusFactory $statusFactory
     * @param \Magento\Sales\Model\ResourceModel\Order\Status $statusResource
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        StatusFactory $statusFactory,
        StatusResource $statusResource,
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->statusFactory = $statusFactory;
        $this->statusResource = $statusResource;
    }

    /**
     * @inheritDoc
     *
     * Create custom order statuses and assign them to the processing state.
     *
     * @return $this
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $statuses = [
            [
                'status' => 'processing_wallee',
                'label' => 'Hold Delivery'
            ],
            [
                'status' => 'shipped_wallee',
                'label' => 'Shipped'
            ]
        ];

        foreach ($statuses as $statusData) {
            $status = $this->statusFactory->create();
            $status->setData($statusData);
            $this->statusResource->save($status);
            $status->assignState('processing', $statusData['status'], true);
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
