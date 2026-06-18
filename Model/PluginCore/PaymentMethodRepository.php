<?php
/**
 * wallee Magento 2
 *
 * This Magento 2 extension enables to process payments with wallee (https://www.wallee.com).
 *
 * @package Wallee_Payment
 * @author wallee AG (https://www.wallee.com)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)

 */
declare(strict_types=1);

namespace Wallee\Payment\Model\PluginCore;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface as StorageWriter;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Wallee\Payment\Api\Data\PaymentMethodConfigurationInterface;
use Wallee\Payment\Api\PaymentMethodConfigurationRepositoryInterface;
use Wallee\Payment\Model\PaymentMethodConfiguration;
use Wallee\Payment\Model\PaymentMethodConfigurationFactory;
use Wallee\PluginCore\PaymentMethod\PaymentMethod as PluginCorePaymentMethod;
use Wallee\PluginCore\PaymentMethod\PaymentMethodRepositoryInterface;
use Wallee\PluginCore\PaymentMethod\State as PluginCoreState;

/**
 * Magento-side adapter for plugin-core payment method synchronization.
 *
 * Owns the diffing logic against local Magento entities: the incoming list
 * from the gateway is merged into local rows by configuration ID, and any
 * local rows not present in the incoming list are hidden.
 */
class PaymentMethodRepository implements PaymentMethodRepositoryInterface
{

    /**
     * @param PaymentMethodConfigurationFactory $configurationFactory
     * @param PaymentMethodConfigurationRepositoryInterface $configurationRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param StorageWriter $configWriter
     */
    public function __construct(
        private readonly PaymentMethodConfigurationFactory $configurationFactory,
        private readonly PaymentMethodConfigurationRepositoryInterface $configurationRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StorageWriter $configWriter,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function sync(int $spaceId, array $paymentMethods): void
    {
        $existing = $this->loadExistingByConfigurationId($spaceId);
        $incomingIds = [];

        foreach ($paymentMethods as $method) {
            $incomingIds[$method->id] = true;
            /** @var PaymentMethodConfiguration $entity */
            $entity = $existing[$method->id] ?? $this->configurationFactory->create();
            $this->applyPaymentMethod($entity, $spaceId, $method);
            $this->configurationRepository->save($entity);
            $this->storeConfigValues($entity, $method);
        }

        foreach ($existing as $configurationId => $entity) {
            if (isset($incomingIds[$configurationId])) {
                continue;
            }
            $entity->setData(
                PaymentMethodConfigurationInterface::STATE,
                PaymentMethodConfiguration::STATE_HIDDEN
            );
            $this->configurationRepository->save($entity);
        }
    }

    /**
     * @inheritDoc
     */
    public function getExistingExternalIds(int $spaceId): array
    {
        return array_keys($this->loadExistingByConfigurationId($spaceId));
    }

    /**
     * @inheritDoc
     */
    public function create(PluginCorePaymentMethod $method, int $spaceId): void
    {
        /** @var PaymentMethodConfiguration $entity */
        $entity = $this->configurationFactory->create();
        $this->applyPaymentMethod($entity, $spaceId, $method);
        $this->configurationRepository->save($entity);
        $this->storeConfigValues($entity, $method);
    }

    /**
     * @inheritDoc
     */
    public function update(PluginCorePaymentMethod $method, int $spaceId): void
    {
        try {
            $entity = $this->configurationRepository->getByConfigurationId($spaceId, $method->id);
        } catch (NoSuchEntityException $e) {
            return;
        }
        $this->applyPaymentMethod($entity, $spaceId, $method);
        $this->configurationRepository->save($entity);
        $this->storeConfigValues($entity, $method);
    }

    /**
     * @inheritDoc
     */
    public function deactivateByExternalId(int $externalId, int $spaceId): void
    {
        try {
            $entity = $this->configurationRepository->getByConfigurationId($spaceId, $externalId);
        } catch (NoSuchEntityException $e) {
            return;
        }
        $entity->setData(
            PaymentMethodConfigurationInterface::STATE,
            PaymentMethodConfiguration::STATE_HIDDEN
        );
        $this->configurationRepository->save($entity);
    }

    /**
     * Fetches payment method configurations for a given space.
     *
     * @param int $spaceId
     * @return array<int, PaymentMethodConfiguration>
     */
    private function loadExistingByConfigurationId(int $spaceId): array
    {
        $criteria = $this->searchCriteriaBuilder
            ->addFilter(PaymentMethodConfigurationInterface::SPACE_ID, $spaceId)
            ->addFilter(
                PaymentMethodConfigurationInterface::STATE,
                [
                    PaymentMethodConfiguration::STATE_ACTIVE,
                    PaymentMethodConfiguration::STATE_INACTIVE,
                    PaymentMethodConfiguration::STATE_HIDDEN,
                ],
                'in'
            )
            ->create();

        $existing = [];
        foreach ($this->configurationRepository->getList($criteria)->getItems() as $entity) {
            $existing[(int)$entity->getConfigurationId()] = $entity;
        }
        return $existing;
    }

    /**
     * Populates a configuration entity with data from a source payment method object.
     *
     * @param PaymentMethodConfiguration $entity
     * @param int $spaceId
     * @param PluginCorePaymentMethod $method
     * @return void
     */
    private function applyPaymentMethod(
        PaymentMethodConfiguration $entity,
        int $spaceId,
        PluginCorePaymentMethod $method
    ): void {
        $state = match ($method->state) {
            PluginCoreState::ACTIVE => PaymentMethodConfiguration::STATE_ACTIVE,
            PluginCoreState::INACTIVE => PaymentMethodConfiguration::STATE_INACTIVE,
            default => PaymentMethodConfiguration::STATE_HIDDEN,
        };

        $entity->setData(PaymentMethodConfigurationInterface::SPACE_ID, $spaceId);
        $entity->setData(PaymentMethodConfigurationInterface::STATE, $state);
        $entity->setData(PaymentMethodConfigurationInterface::CONFIGURATION_ID, $method->id);
        $entity->setData(
            PaymentMethodConfigurationInterface::CONFIGURATION_NAME,
            $method->title->getDefault()
        );
        $entity->setData(PaymentMethodConfigurationInterface::TITLE, $method->title->jsonSerialize());
        $entity->setData(
            PaymentMethodConfigurationInterface::DESCRIPTION,
            $method->description->jsonSerialize()
        );
        $entity->setData(PaymentMethodConfigurationInterface::IMAGE, $method->getRelativeImagePath());
        $entity->setData(PaymentMethodConfigurationInterface::SORT_ORDER, $method->sortOrder);
    }

    /**
     * Store the localized title and description.
     *
     * Localized title and description are stored per configuration scope,
     * mirroring Magento's legacy per-store payment configuration layout.
     *
     * @param PaymentMethodConfigurationInterface $configuration
     * @param PluginCorePaymentMethod $method
     * @return void
     */
    private function storeConfigValues(
        PaymentMethodConfigurationInterface $configuration,
        PluginCorePaymentMethod $method
    ): void {
        $defaultLocale = $this->scopeConfig->getValue('general/locale/code');

        $this->writeLocalizedValues($configuration, $method, $defaultLocale);

        $stores = $this->storeManager->getStores();
        foreach ($this->storeManager->getWebsites() as $website) {
            $websiteLocale = $this->scopeConfig->getValue(
                'general/locale/code',
                ScopeInterface::SCOPE_WEBSITES,
                $website->getId()
            );
            if ($websiteLocale !== $defaultLocale) {
                $this->writeLocalizedValues(
                    $configuration,
                    $method,
                    $websiteLocale,
                    ScopeInterface::SCOPE_WEBSITES,
                    (int)$website->getId()
                );
            }

            foreach ($stores as $store) {
                if ((int)$store->getWebsiteId() !== (int)$website->getId()) {
                    continue;
                }
                $storeLocale = $this->scopeConfig->getValue(
                    'general/locale/code',
                    ScopeInterface::SCOPE_STORES,
                    $store->getId()
                );
                if ($storeLocale !== $websiteLocale) {
                    $this->writeLocalizedValues(
                        $configuration,
                        $method,
                        $storeLocale,
                        ScopeInterface::SCOPE_STORES,
                        (int)$store->getId()
                    );
                }
            }
        }
    }

    /**
     * Resolves localized values and persists for the given scope.
     *
     * @param PaymentMethodConfigurationInterface $configuration
     * @param PluginCorePaymentMethod $method
     * @param string $locale
     * @param string $scope
     * @param int $scopeId
     * @return void
     */
    private function writeLocalizedValues(
        PaymentMethodConfigurationInterface $configuration,
        PluginCorePaymentMethod $method,
        string $locale,
        string $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        int $scopeId = 0
    ): void {
        $title = $method->title->localize($locale) ?? (string)$configuration->getConfigurationName();
        $description = $method->description->localize($locale) ?? '';

        $this->writeConfigValue($configuration, 'title', $title, $scope, $scopeId);
        $this->writeConfigValue($configuration, 'description', $description, $scope, $scopeId);
    }

    /**
     * Saves config value to the payment method at the specified scope.
     *
     * @param PaymentMethodConfigurationInterface $configuration
     * @param string $key
     * @param string $value
     * @param string $scope
     * @param int $scopeId
     * @return void
     */
    private function writeConfigValue(
        PaymentMethodConfigurationInterface $configuration,
        string $key,
        $value,
        string $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        int $scopeId = 0
    ): void {
        $this->configWriter->save(
            'payment/wallee_payment_' . $configuration->getEntityId() . '/' . $key,
            $value,
            $scope,
            $scopeId
        );
    }
}
