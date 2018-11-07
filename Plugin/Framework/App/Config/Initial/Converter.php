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
namespace Wallee\Payment\Plugin\Framework\App\Config\Initial;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Initial\SchemaLocator;
use Magento\Framework\Filesystem\DriverPool;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Magento\Store\Model\StoreManagerInterface;
use Wallee\Payment\Api\PaymentMethodConfigurationRepositoryInterface;
use Wallee\Payment\Api\Data\PaymentMethodConfigurationInterface;
use Wallee\Payment\Helper\Locale as LocaleHelper;
use Wallee\Payment\Model\PaymentMethodConfiguration;
use Wallee\Payment\Model\Config\Dom;
use Wallee\Payment\Model\Config\DomFactory;

/**
 * Interceptor to dynamically extend the initial configuration with the wallee payment method data.
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
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     *
     * @var ResourceConnection
     */
    protected $_resourceConnection;

    /**
     *
     * @var DomFactory
     */
    protected $_domFactory;

    /**
     *
     * @var SchemaLocator
     */
    protected $_schemaFile;

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
     * @param ScopeConfigInterface $scopeConfig
     * @param ResourceConnection $resourceConnection
     * @param DomFactory $domFactory
     * @param SchemaLocator $schemaLocator
     * @param ModuleDirReader $moduleReader
     * @param DriverPool $driverPool
     */
    public function __construct(PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder, FilterBuilder $filterBuilder,
        FilterGroupBuilder $filterGroupBuilder, StoreManagerInterface $storeManager, ScopeConfigInterface $scopeConfig,
        ResourceConnection $resourceConnection, DomFactory $domFactory, SchemaLocator $schemaLocator,
        ModuleDirReader $moduleReader, DriverPool $driverPool)
    {
        $this->_paymentMethodConfigurationRepository = $paymentMethodConfigurationRepository;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_filterBuilder = $filterBuilder;
        $this->_filterGroupBuilder = $filterGroupBuilder;
        $this->_storeManager = $storeManager;
        $this->_scopeConfig = $scopeConfig;
        $this->_resourceConnection = $resourceConnection;
        $this->_domFactory = $domFactory;
        $this->_schemaFile = $schemaLocator->getSchema();

        $templatePath = $moduleReader->getModuleDir(\Magento\Framework\Module\Dir::MODULE_ETC_DIR,
            'Wallee_Payment') . '/config-method-template.xml';
        $this->template = $driverPool->getDriver(DriverPool::FILE)->fileGetContents($templatePath);
    }

    public function beforeConvert(\Magento\Framework\App\Config\Initial\Converter $subject, $source)
    {
        if (! $this->isTableExists()) {
            return [
                $source
            ];
        }

        $configMerger = $this->_domFactory->createDom(
            [
                'xml' => Dom::CONFIG_INITIAL_CONTENT,
                'schemaFile' => $this->_schemaFile
            ]);
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
            $configMerger->merge($this->generateXml($configuration));
        }

        return [
            $configMerger->getDom()
        ];
    }

    protected function generateXml(PaymentMethodConfigurationInterface $configuration)
    {
        return \str_replace(
            [
                '{id}',
                '{active}',
                '{title}',
                '{description}',
                '{sortOrder}',
                '{spaceId}'
            ],
            [
                $configuration->getEntityId(),
                $configuration->getState() == PaymentMethodConfiguration::STATE_ACTIVE ? 1 : 0,
                $this->getTranslatedTitle($configuration, LocaleHelper::DEFAULT_LANGUAGE),
                $this->translate($configuration->getDescription(), LocaleHelper::DEFAULT_LANGUAGE),
                1,
                $configuration->getSpaceId()
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

    /**
     * Gets the translated title of the payment method configuration.
     *
     * If the title is not set, the configuration's name will be returned instead.
     *
     * @param PaymentMethodConfiguration $configuration
     * @param string $language
     * @return string
     */
    protected function getTranslatedTitle(PaymentMethodConfiguration $configuration, $language)
    {
        $translatedTitle = $this->translate($configuration->getTitle(), $language);
        if (! empty($translatedTitle)) {
            return $translatedTitle;
        } else {
            return $configuration->getConfigurationName();
        }
    }

    protected function translate($translatedString, $language)
    {
        $language = \str_replace('_', '-', $language);
        if (isset($translatedString[$language])) {
            return $translatedString[$language];
        }

        if (isset($translatedString[LocaleHelper::DEFAULT_LANGUAGE])) {
            return $translatedString[LocaleHelper::DEFAULT_LANGUAGE];
        }

        return null;
    }
}