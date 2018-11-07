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
namespace Wallee\Payment\Model\Checkout;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Wallee\Payment\Api\PaymentMethodConfigurationRepositoryInterface;
use Wallee\Payment\Api\Data\PaymentMethodConfigurationInterface;
use Wallee\Payment\Model\PaymentMethodConfiguration;
use Wallee\Payment\Model\Service\Quote\TransactionService;

/**
 * Class to provide information that allow to checkout using the wallee payment methods.
 */
class ConfigProvider implements ConfigProviderInterface
{

    /**
     *
     * @var PaymentMethodConfigurationRepositoryInterface
     */
    protected $_paymentMethodConfigurationRepository;

    /**
     *
     * @var TransactionService
     */
    protected $_transactionService;

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
     * @var CheckoutSession
     */
    protected $_checkoutSession;

    /**
     *
     * @param PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository
     * @param TransactionService $transactionService
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository,
        TransactionService $transactionService, SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder, FilterGroupBuilder $filterGroupBuilder, CheckoutSession $checkoutSession)
    {
        $this->_paymentMethodConfigurationRepository = $paymentMethodConfigurationRepository;
        $this->_transactionService = $transactionService;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_filterBuilder = $filterBuilder;
        $this->_filterGroupBuilder = $filterGroupBuilder;
        $this->_checkoutSession = $checkoutSession;
    }

    public function getConfig()
    {
        $config = [
            'payment' => [],
            'wallee' => []
        ];

        try {
            $config['wallee']['javascriptUrl'] = $this->_transactionService->getJavaScriptUrl(
                $this->_checkoutSession->getQuote());
        } catch (\Exception $e) {}

        try {
            $config['wallee']['paymentPageUrl'] = $this->_transactionService->getPaymentPageUrl(
                $this->_checkoutSession->getQuote());
        } catch (\Exception $e) {}

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
            $config['payment']['wallee_payment_' . $configuration->getEntityId()] = [
                'isActive' => true,
                'configurationId' => $configuration->getConfigurationId()
            ];
        }

        return $config;
    }
}