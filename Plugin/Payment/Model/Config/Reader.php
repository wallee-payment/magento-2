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
namespace Wallee\Payment\Plugin\Payment\Model\Config;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Wallee\Payment\Api\PaymentMethodConfigurationRepositoryInterface;
use Wallee\Payment\Api\Data\PaymentMethodConfigurationInterface;
use Wallee\Payment\Model\PaymentMethodConfiguration;

/**
 * Interceptor to dynamically extend the payment configuration with the wallee payment method data.
 */
class Reader
{

    /**
     *
     * @var PaymentMethodConfigurationRepositoryInterface
     */
    protected $_paymentMethodConfigurationRepository;

    /**
     *
     * @var SearchCriteriaBuilder
     */
    protected $_searchCriteriaBuilder;

    /**
     *
     * @var FilterBuilder
     */
    protected $_filterBuilder;

    /**
     *
     * @var FilterGroupBuilder
     */
    protected $_filterGroupBuilder;

    /**
     *
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     *
     * @var ResourceConnection
     */
    protected $_resourceConnection;

    /**
     *
     * @param PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     * @param StoreManagerInterface $storeManager
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder, FilterBuilder $filterBuilder,
        FilterGroupBuilder $filterGroupBuilder, StoreManagerInterface $storeManager,
        ResourceConnection $resourceConnection)
    {
        $this->_paymentMethodConfigurationRepository = $paymentMethodConfigurationRepository;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_storeManager = $storeManager;
        $this->_filterBuilder = $filterBuilder;
        $this->_filterGroupBuilder = $filterGroupBuilder;
        $this->_resourceConnection = $resourceConnection;
    }

    public function afterRead(\Magento\Payment\Model\Config\Reader $subject, $result)
    {
        if (! $this->isTableExists()) {
            return $result;
        }

        if (isset($result['methods'])) {
            $stateFilter = $this->_filterBuilder->setConditionType('in')
                ->setField(PaymentMethodConfigurationInterface::STATE)
                ->setValue(
                [
                    PaymentMethodConfiguration::STATE_ACTIVE,
                    PaymentMethodConfiguration::STATE_INACTIVE
                ])
                ->create();
            $filterGroup = $this->_filterGroupBuilder->setFilters([
                $stateFilter
            ])->create();
            $searchCriteria = $this->_searchCriteriaBuilder->setFilterGroups(
                [
                    $filterGroup
                ])->create();

            $configurations = $this->_paymentMethodConfigurationRepository->getList($searchCriteria)->getItems();
            foreach ($configurations as $configuration) {
                $result['methods'][$this->getPaymentMethodId($configuration)] = $this->generateConfig($configuration);
            }
        }
        return $result;
    }

    protected function getPaymentMethodId(PaymentMethodConfigurationInterface $configuration)
    {
        return 'wallee_payment_' . $configuration->getEntityId();
    }

    protected function generateConfig(PaymentMethodConfigurationInterface $configuration)
    {
        return [
            'allow_multiple_address' => '1'
        ];
    }

    /**
     * Gets whether the payment method configuration database table exists.
     *
     * @return boolean
     */
    protected function isTableExists()
    {
        return $this->_resourceConnection->getConnection()->isTableExists(
            $this->_resourceConnection->getTableName('wallee_payment_method_configuration'));
    }
}