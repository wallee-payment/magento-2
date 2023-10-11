<?php
/**
 * wallee Magento 2
 *
 * This Magento 2 extension enables to process payments with wallee (https://www.wallee.com/).
 *
 * @package Wallee_Payment
 * @author wallee AG (http://www.wallee.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
namespace Wallee\Payment\Model\ResourceModel;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Payment Method Configuration Resource Model
 */
class PaymentMethodConfiguration extends AbstractDb
{

    /**
     * Event prefix
     *
     * @var string
     */
    protected $_eventPrefix = 'wallee_payment_method_configuration_resource';

    /**
     * Serializable fields
     *
     * @var array<string, mixed>
     */
    protected $_serializableFields = [
        'title' => [
            null,
            null
        ],
        'description' => [
            null,
            null
        ]
    ];

    /**
     * Model initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('wallee_payment_method_configuration', 'entity_id');
    }

    /**
     * Load the payment method configuration by space and configuration.
     *
     * @param AbstractModel $object
     * @param int $spaceId
     * @param int $configurationId
     * @return $this
     */
    public function loadByConfigurationId(AbstractModel $object, $spaceId, $configurationId)
    {
        $connection = $this->getConnection();
        if ($connection) {
            $select = $connection->select()
                ->from($this->getMainTable())
                ->where('space_id=:space_id')
                ->where('configuration_id=:configuration_id');
            $binds = [
                'space_id' => $spaceId,
                'configuration_id' => $configurationId
            ];
            $data = $connection->fetchRow($select, $binds);
            if ($data) {
                $object->setData($data);
            }
        }

        $this->unserializeFields($object);
        $this->_afterLoad($object);
        return $this;
    }
}