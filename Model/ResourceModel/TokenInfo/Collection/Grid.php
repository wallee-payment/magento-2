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
namespace Wallee\Payment\Model\ResourceModel\TokenInfo\Collection;

use Magento\Customer\Controller\RegistryConstants;
use Magento\Framework\Registry;
use Magento\Framework\DB\Adapter\AdapterInterface as DBAdapter;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb as ResourceModel;
use Psr\Log\LoggerInterface;

/**
 * Token info resource collection for displaying as grid.
 */
class Grid extends \Wallee\Payment\Model\ResourceModel\TokenInfo\Collection
{

    /**
     *
     * @var Registry
     */
    protected $_registry;

    /**
     *
     * @param EntityFactoryInterface $entityFactory
     * @param LoggerInterface $logger
     * @param FetchStrategyInterface $fetchStrategy
     * @param EventManager $eventManager
     * @param ResourceModel $resource
     * @param Registry $registry
     * @param DBAdapter $connection
     */
    public function __construct(EntityFactoryInterface $entityFactory, LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy, EventManager $eventManager, ResourceModel $resource, Registry $registry,
        DBAdapter $connection = null)
    {
        $this->_registry = $registry;
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $connection, $resource);
    }

    /**
     * Initialize db select
     *
     * @return $this
     */
    protected function _initSelect()
    {
        parent::_initSelect();
        $this->addCustomerIdFilter($this->_registry->registry(RegistryConstants::CURRENT_CUSTOMER_ID))
            ->resetSortOrder();
        $this->join([
            'payment_method' => 'wallee_payment_method_configuration'
        ], 'main_table.payment_method_id = payment_method.entity_id',
            [
                'payment_method_name' => 'payment_method.configuration_name'
            ]);
        return $this;
    }
}