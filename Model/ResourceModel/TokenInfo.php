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
namespace Wallee\Payment\Model\ResourceModel;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Token Info Resource Model
 */
class TokenInfo extends AbstractDb
{

    /**
     * Event prefix
     *
     * @var string
     */
    protected $_eventPrefix = 'wallee_payment_token_info_resource';

    /**
     * Serializable fields
     *
     * @var array
     */
    protected $_serializableFields = [
        'failure_reason' => [
            null,
            null
        ],
        'labels' => [
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
        $this->_init('wallee_payment_token_info', 'entity_id');
    }

    /**
     * Load the token info by space and token.
     *
     * @param AbstractModel $object
     * @param int $spaceId
     * @param int $tokenId
     * @return $this
     */
    public function loadByToken(AbstractModel $object, $spaceId, $tokenId)
    {
        $connection = $this->getConnection();
        if ($connection) {
            $select = $connection->select()
                ->from($this->getMainTable())
                ->where('space_id=:space_id')
                ->where('token_id=:token_id');
            $binds = [
                'space_id' => $spaceId,
                'token_id' => $tokenId
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