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
namespace Wallee\Payment\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Cache\TypeListInterface as CacheTypeList;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface as StorageWriter;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Wallee\Payment\Api\PaymentMethodConfigurationManagementInterface;
use Wallee\Payment\Api\PaymentMethodConfigurationRepositoryInterface;
use Wallee\Payment\Api\Data\PaymentMethodConfigurationInterface;
use Wallee\Payment\Helper\Locale as LocaleHelper;
use Wallee\Sdk\Model\CreationEntityState;
use Wallee\Sdk\Model\EntityQuery;
use Wallee\Sdk\Service\PaymentMethodConfigurationService;

/**
 * Payment method configuration management service.
 */
class PaymentMethodConfigurationManagement implements PaymentMethodConfigurationManagementInterface
{

    /**
     *
     * @var PaymentMethodConfigurationFactory
     */
    protected $_paymentMethodConfigurationFactory;

    /**
     *
     * @var PaymentMethodConfigurationRepositoryInterface
     */
    protected $_paymentMethodConfigurationRepository;

    /**
     *
     * @var LocaleHelper
     */
    protected $_localeHelper;

    /**
     *
     * @var ApiClient
     */
    protected $_apiClient;

    /**
     *
     * @var SearchCriteriaBuilder
     */
    protected $_searchCriteriaBuilder;

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
     * @var StorageWriter
     */
    protected $_configWriter;

    /**
     *
     * @var CacheTypeList
     */
    protected $_cacheTypeList;

    /**
     *
     * @param PaymentMethodConfigurationFactory $paymentMethodConfigurationFactory
     * @param PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository
     * @param LocaleHelper $localeHelper
     * @param ApiClient $apiClient
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param StorageWriter $configWriter
     * @param CacheTypeList $cacheTypeList
     */
    public function __construct(PaymentMethodConfigurationFactory $paymentMethodConfigurationFactory,
        PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository, LocaleHelper $localeHelper,
        ApiClient $apiClient, SearchCriteriaBuilder $searchCriteriaBuilder, StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig, StorageWriter $configWriter, CacheTypeList $cacheTypeList)
    {
        $this->_paymentMethodConfigurationFactory = $paymentMethodConfigurationFactory;
        $this->_paymentMethodConfigurationRepository = $paymentMethodConfigurationRepository;
        $this->_localeHelper = $localeHelper;
        $this->_apiClient = $apiClient;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_storeManager = $storeManager;
        $this->_scopeConfig = $scopeConfig;
        $this->_configWriter = $configWriter;
        $this->_cacheTypeList = $cacheTypeList;
    }

    public function synchronize()
    {
        $existingConfigurations = $this->_paymentMethodConfigurationRepository->getList(
            $this->_searchCriteriaBuilder->create())
            ->getItems();
        foreach ($existingConfigurations as $existingConfiguration) {
            /** @var PaymentMethodConfiguration $existingConfiguration */
            $existingConfiguration->setData(PaymentMethodConfigurationInterface::STATE,
                PaymentMethodConfiguration::STATE_HIDDEN);
        }

        $spaceIds = [];
        $existingFound = [];
        $createdEntities = [];
        foreach ($this->_storeManager->getWebsites() as $website) {
            $spaceId = $this->_scopeConfig->getValue('wallee_payment/general/space_id',
                ScopeInterface::SCOPE_WEBSITE, $website->getId());
            if ($spaceId && ! in_array($spaceId, $spaceIds)) {
                $configurations = $this->_apiClient->getService(PaymentMethodConfigurationService::class)->search(
                    $spaceId, new EntityQuery());
                foreach ($configurations as $configuration) {
                    /** @var PaymentMethodConfiguration $entity */
                    $entity = null;
                    foreach ($existingConfigurations as $existingConfiguration) {
                        /** @var PaymentMethodConfiguration $existingConfiguration */
                        if ($existingConfiguration->getSpaceId() == $spaceId &&
                            $existingConfiguration->getConfigurationId() == $configuration->getId()) {
                            $entity = $existingConfiguration;
                            $existingFound[] = $entity->getId();
                            break;
                        }
                    }

                    if ($entity == null) {
                        $entity = $this->_paymentMethodConfigurationFactory->create();
                        $createdEntities[] = $entity;
                    }

                    $entity->setData(PaymentMethodConfigurationInterface::SPACE_ID, $spaceId);
                    $entity->setData(PaymentMethodConfigurationInterface::STATE,
                        $this->toConfigurationState($configuration->getState()));
                    $entity->setData(PaymentMethodConfigurationInterface::CONFIGURATION_ID, $configuration->getId());
                    $entity->setData(PaymentMethodConfigurationInterface::CONFIGURATION_NAME, $configuration->getName());
                    $entity->setData(PaymentMethodConfigurationInterface::TITLE, $configuration->getResolvedTitle());
                    $entity->setData(PaymentMethodConfigurationInterface::DESCRIPTION,
                        $configuration->getResolvedDescription());
                    $entity->setData(PaymentMethodConfigurationInterface::IMAGE,
                        $this->extractImagePath($configuration->getResolvedImageUrl()));
                    $entity->setData(PaymentMethodConfigurationInterface::SORT_ORDER, $configuration->getSortOrder());
                    $this->_paymentMethodConfigurationRepository->save($entity);
                }
            }
        }

        foreach ($createdEntities as $entity) {
            $this->storeConfigValues($entity);
        }

        foreach ($existingConfigurations as $existingConfiguration) {
            if (! in_array($existingConfiguration->getId(), $existingFound)) {
                $existingConfiguration->setData(PaymentMethodConfigurationInterface::STATE,
                    PaymentMethodConfiguration::STATE_HIDDEN);
                $this->_paymentMethodConfigurationRepository->save($existingConfiguration);
            }
        }

        $this->clearCache();
    }

    protected function clearCache()
    {
        $this->_cacheTypeList->cleanType(\Magento\Framework\App\Cache\Type\Config::TYPE_IDENTIFIER);
    }

    protected function storeConfigValues(PaymentMethodConfigurationInterface $configuration)
    {
        $defaultLocale = $this->_scopeConfig->getValue('general/locale/code');

        $this->storeConfigValue($configuration, 'title', $this->getTranslatedTitle($configuration, $defaultLocale));
        $this->storeConfigValue($configuration, 'description',
            $this->_localeHelper->translate($configuration->getDescription(), $defaultLocale));

        $stores = $this->_storeManager->getStores();
        foreach ($this->_storeManager->getWebsites() as $website) {
            $websiteLocale = $this->_scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_WEBSITES,
                $website->getId());
            if ($websiteLocale != $defaultLocale) {
                $this->storeConfigValue($configuration, 'title',
                    $this->getTranslatedTitle($configuration, $websiteLocale), ScopeInterface::SCOPE_WEBSITES,
                    $website->getId());
                $this->storeConfigValue($configuration, 'description',
                    $this->_localeHelper->translate($configuration->getDescription(), $websiteLocale),
                    ScopeInterface::SCOPE_WEBSITES, $website->getId());
            }

            foreach ($stores as $store) {
                if ($store->getWebsiteId() == $website->getId()) {
                    $storeLocale = $this->_scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORES,
                        $store->getId());
                    if ($storeLocale != $websiteLocale) {
                        $this->storeConfigValue($configuration, 'title',
                            $this->getTranslatedTitle($configuration, $storeLocale), ScopeInterface::SCOPE_STORES,
                            $store->getId());
                        $this->storeConfigValue($configuration, 'description',
                            $this->_localeHelper->translate($configuration->getDescription(), $storeLocale),
                            ScopeInterface::SCOPE_STORES, $store->getId());
                    }
                }
            }
        }
    }

    protected function storeConfigValue(PaymentMethodConfigurationInterface $configuration, $key, $value,
        $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeId = 0)
    {
        $this->_configWriter->save(
            'payment/wallee_payment_' . $configuration->getEntityId() . '/' . $key, $value, $scope,
            $scopeId);
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
        $translatedTitle = $this->_localeHelper->translate($configuration->getTitle(), $language);
        if (! empty($translatedTitle)) {
            return $translatedTitle;
        } else {
            return $configuration->getConfigurationName();
        }
    }

    public function update(\Wallee\Sdk\Model\PaymentMethodConfiguration $configuration)
    {
        try {
            $entity = $this->_paymentMethodConfigurationRepository->getByConfigurationId($configuration->getSpaceId(),
                $configuration->getId());
            if ($this->hasConfigurationChanged($configuration, $entity)) {
                $entity->setData(PaymentMethodConfigurationInterface::CONFIGURATION_NAME, $configuration->getName());
                $entity->setData(PaymentMethodConfigurationInterface::TITLE, $configuration->getResolvedTitle());
                $entity->setData(PaymentMethodConfigurationInterface::DESCRIPTION,
                    $configuration->getResolvedDescription());
                $entity->setData(PaymentMethodConfigurationInterface::IMAGE,
                    $this->extractImagePath($configuration->getResolvedImageUrl()));
                $entity->setData(PaymentMethodConfigurationInterface::SORT_ORDER, $configuration->getSortOrder());
                $this->_paymentMethodConfigurationRepository->save($entity);
            }
        } catch (NoSuchEntityException $e) {}
    }

    protected function hasConfigurationChanged(
        \Wallee\Sdk\Model\PaymentMethodConfiguration $configuration,
        PaymentMethodConfigurationInterface $entity)
    {
        if ($configuration->getName() != $entity->getConfigurationName()) {
            return true;
        }

        if ($configuration->getResolvedTitle() != $entity->getTitle()) {
            return true;
        }

        if ($configuration->getResolvedDescription() != $entity->getDescription()) {
            return true;
        }

        if ($this->extractImagePath($configuration->getResolvedImageUrl()) != $entity->getImage()) {
            return true;
        }

        if ($configuration->getSortOrder() != $entity->getSortOrder()) {
            return true;
        }

        return false;
    }

    /**
     * Extracts the image path from the URL.
     *
     * @param string $resolvedImageUrl
     * @return string
     */
    protected function extractImagePath($resolvedImageUrl)
    {
        $index = \strpos($resolvedImageUrl, 'resource/');
        return \substr($resolvedImageUrl, $index + \strlen('resource/'));
    }

    /**
     * Gets the state for the payment method configuration.
     *
     * @param string $state
     * @return number
     */
    protected function toConfigurationState($state)
    {
        switch ($state) {
            case CreationEntityState::ACTIVE:
                return PaymentMethodConfiguration::STATE_ACTIVE;
            case CreationEntityState::INACTIVE:
                return PaymentMethodConfiguration::STATE_INACTIVE;
            default:
                return PaymentMethodConfiguration::STATE_HIDDEN;
        }
    }
}