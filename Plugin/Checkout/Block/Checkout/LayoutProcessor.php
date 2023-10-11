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
namespace Wallee\Payment\Plugin\Checkout\Block\Checkout;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Wallee\Payment\Api\PaymentMethodConfigurationRepositoryInterface;
use Wallee\Payment\Api\Data\PaymentMethodConfigurationInterface;
use Wallee\Payment\Model\PaymentMethodConfiguration;

/**
 * Interceptor to dynamically extend the layout configuration with the wallee payment method data.
 */
class LayoutProcessor
{

    /**
     *
     * @var PaymentMethodConfigurationRepositoryInterface
     */
    private $paymentMethodConfigurationRepository;

    /**
     *
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     *
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     *
     * @param PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder, ResourceConnection $resourceConnection)
    {
        $this->paymentMethodConfigurationRepository = $paymentMethodConfigurationRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @param \Magento\Checkout\Block\Checkout\LayoutProcessor $subject
     * @param array<mixed> $jsLayout
     * @return array|array[]
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function beforeProcess(\Magento\Checkout\Block\Checkout\LayoutProcessor $subject, $jsLayout)
    {
        if (! $this->isTableExists()) {
            return [
                $jsLayout
            ];
        }

        if (isset(
            $jsLayout['components']['checkout']['children']['steps']['children']['billing-step']['children']['payment']['children']['renders']['children']['wallee_payment']['methods'])) {
            $searchCriteria = $this->searchCriteriaBuilder->addFilter(PaymentMethodConfigurationInterface::STATE,
                [
                    PaymentMethodConfiguration::STATE_ACTIVE,
                    PaymentMethodConfiguration::STATE_INACTIVE
                ], 'in')->create();

            $configurations = $this->paymentMethodConfigurationRepository->getList($searchCriteria)->getItems();
            foreach ($configurations as $configuration) {
                $jsLayout['components']['checkout']['children']['steps']['children']['billing-step']['children']['payment']['children']['renders']['children']['wallee_payment']['methods']['wallee_payment_' .
                    $configuration->getEntityId()] = $this->getMethodData();
            }
        }

        return [
            $jsLayout
        ];
    }

    /**
     * @return array<mixed>
     */
    private function getMethodData()
    {
        return [
            'isBillingAddressRequired' => true
        ];
    }

    /**
     * Gets whether the payment method configuration database table exists.
     *
     * @return boolean
     */
    private function isTableExists()
    {
        return $this->resourceConnection->getConnection()->isTableExists(
            $this->resourceConnection->getTableName('wallee_payment_method_configuration'));
    }
}