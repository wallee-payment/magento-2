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
namespace Wallee\Payment\Plugin\Config\Model\Config\Structure;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem\DriverPool;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Magento\Store\Model\StoreManagerInterface;
use Wallee\Payment\Api\PaymentMethodConfigurationRepositoryInterface;
use Wallee\Payment\Api\Data\PaymentMethodConfigurationInterface;
use Wallee\Payment\Model\PaymentMethodConfiguration;
use Wallee\Payment\Model\Config\Structure\Reader;

/**
 * Interceptor to dynamically extend config structure with the wallee payment method data.
 */
class Converter
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
     * @var Reader
     */
    protected $_reader;

    /**
     *
     * @var string
     */
    private $template;

    /**
     *
     * @param PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     * @param StoreManagerInterface $storeManager
     * @param ResourceConnection $resourceConnection
     * @param Reader $reader
     * @param ModuleDirReader $moduleReader
     * @param DriverPool $driverPool
     */
    public function __construct(PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder, FilterBuilder $filterBuilder,
        FilterGroupBuilder $filterGroupBuilder, StoreManagerInterface $storeManager,
        ResourceConnection $resourceConnection, Reader $reader, ModuleDirReader $moduleReader, DriverPool $driverPool)
    {
        $this->_paymentMethodConfigurationRepository = $paymentMethodConfigurationRepository;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_filterBuilder = $filterBuilder;
        $this->_filterGroupBuilder = $filterGroupBuilder;
        $this->_storeManager = $storeManager;
        $this->_resourceConnection = $resourceConnection;
        $this->_reader = $reader;

        $templatePath = $moduleReader->getModuleDir(\Magento\Framework\Module\Dir::MODULE_ETC_DIR,
            'Wallee_Payment') . '/adminhtml/system-method-template.xml';
        $this->template = $driverPool->getDriver(DriverPool::FILE)->fileGetContents($templatePath);
    }

    public function beforeConvert(\Magento\Config\Model\Config\Structure\Converter $subject, $source)
    {
        if (! $this->isTableExists()) {
            return [
                $source
            ];
        }

        $configMerger = $this->_reader->createConfigMerger();
        $configMerger->setDom($source);

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
        $searchCriteria = $this->_searchCriteriaBuilder->setFilterGroups([
            $filterGroup
        ])->create();

        $configurations = $this->_paymentMethodConfigurationRepository->getList($searchCriteria)->getItems();
        foreach ($configurations as $configuration) {
            $configMerger->merge($this->_reader->processDocument($this->generateXml($configuration)));
        }

        return [
            $configMerger->getDom()
        ];
    }

    protected function generateXml(PaymentMethodConfigurationInterface $configuration)
    {
        return str_replace([
            '{id}',
            '{name}'
        ], [
            $configuration->getEntityId(),
            $configuration->getConfigurationName()
        ], $this->template);
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