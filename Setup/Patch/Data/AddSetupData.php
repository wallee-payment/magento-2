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

class AddSetupData implements DataPatchInterface, PatchVersionInterface
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
     * @details: It will create each status/state
     * @return:none
     */

    public function apply(){

        $statuses = array(array ('status'=>'processing_wallee','label'=>'Hold Delivery'),
                          array('status'=>'shipped_wallee','label'=>'Shipped'));

        foreach ($statuses as $statusData) {
            $this->status->addData($statusData);
            $this->status->getResource()->save($this->status);
            $this->status->assignState('processing', 'processing', true);
        }
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
