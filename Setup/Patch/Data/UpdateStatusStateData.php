<?php
namespace Wallee\Payment\Setup\Patch\Data;
use \Magento\Framework\Setup\Patch\DataPatchInterface;
use \Magento\Framework\Setup\Patch\PatchVersionInterface;
use \Magento\Framework\Module\Setup\Migration;
use \Magento\Framework\Setup\ModuleDataSetupInterface;


/**
 * Class AddSetupData
 * @package Wallee\Payment\Setup\Patch\Data
 */

class UpdateStatusStateData implements DataPatchInterface, PatchVersionInterface
{
    private $status;

    /**
     *
     * @param \Wallee\Payment\Model\Author $status
     */

    public function __construct(
        \Magento\Sales\Model\Order\Status $status
    ) {
        $this->status = $status;
    }

    /**
     * @details: This updates the status to the correct status
     * @return:none
     */
    public function apply(){
        $object_Manager = \Magento\Framework\App\ObjectManager::getInstance();
        $get_resource   = $object_Manager->get('Magento\Framework\App\ResourceConnection');
        $connection     = $get_resource->getConnection(); // get connection
        
        $update_sql = "UPDATE sales_order_status_state SET is_default = 1 WHERE status = 'processing_wallee'";
        $connection->query($update_sql);
    }

    /**
     * @return array:
     */

    public static function getDependencies(){
        return [];
    }

    /**
     * @description: Under the version number, it will run (if new version is 1.3.5, put 1.3.6)
     * @return int:
     */

    public static function getVersion(){
        return '1.3.20';
    }

    /**
     * @return array:
     */

    public function getAliases(){
        return [];
    }
}
