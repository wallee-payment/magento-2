<?php
namespace Wallee\Payment\Setup\Patch\Data;
use \Magento\Framework\Setup\Patch\DataPatchInterface;
use \Magento\Framework\Setup\Patch\PatchVersionInterface;
use \Magento\Framework\Module\Setup\Migration;
use \Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\App\ResourceConnection;



/**
 * Class AddSetupData
 * @package Wallee\Payment\Setup\Patch\Data
 */

class UpdateStatusState implements DataPatchInterface
{
    /**
     * @var \Magento\Framework\Setup\ModuleDataSetupInterface
    */
    protected $moduleDataSetup;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * @details: This updates the status to the correct status
     * @inheritDoc
     */
    public function apply(){
        $tableName  = $this->moduleDataSetup->getTable('sales_order_status_state');
        $updateSql = "UPDATE " . $tableName . " SET is_default = 1 WHERE status = 'processing_wallee'";
        $this->moduleDataSetup->getConnection()->query($updateSql);
		return $this;
	}

    /**
     * @inheritDoc
     */

    public static function getDependencies(){
        return [];
    }

    /**
     * @inheritDoc
     */

    public function getAliases(){
        return [];
    }
}
