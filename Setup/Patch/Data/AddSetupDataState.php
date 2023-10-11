<?php
namespace Wallee\Payment\Setup\Patch\Data;

use \Magento\Framework\Setup\Patch\DataPatchInterface;
use \Magento\Framework\Setup\Patch\PatchVersionInterface;
use \Magento\Framework\Module\Setup\Migration;
use \Magento\Framework\Setup\ModuleDataSetupInterface;
use \Magento\Sales\Model\Order\Status;

/**
 * Class AddSetupData
 * @package Wallee\Payment\Setup\Patch\Data
 */

class AddSetupDataState implements DataPatchInterface
{
    /**
     *
     * @var Status
     */
    private $status;

    /**
     *
     * @param Status $status
     */
    public function __construct(Status $status) {
        $this->status = $status;
    }

    /**
     * @details: It will create each status/state
     * @inheritDoc
     */
    public function apply(){

        $statuses = array(array ('status'=>'processing_wallee','label'=>'Hold Delivery'),
                          array('status'=>'shipped_wallee','label'=>'Shipped'));

        foreach ($statuses as $statusData) {
            $this->status->addData($statusData);
            /** @todo change this get resource model */
            $this->status->getResource()->save($this->status);
            /** @todo this function expected a boolean as a third parameter */
            $this->status->assignState('processing', 'processing', true);
        }

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
