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
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use Wallee\Payment\Api\PaymentMethodConfigurationRepositoryInterface;
use Wallee\Payment\Api\Data\PaymentMethodConfigurationInterface;
use Wallee\Payment\Model\PaymentMethodConfiguration;
use Wallee\Payment\Model\Config\Source\IntegrationMethod;
use Wallee\Payment\Model\Payment\Method\Adapter;
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
    private $paymentMethodConfigurationRepository;

    /**
     *
     * @var TransactionService
     */
    private $transactionService;

    /**
     *
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     *
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     *
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     *
     * @var PaymentHelper
     */
    private $paymentHelper;

    /**
     *
     * @param PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository
     * @param TransactionService $transactionService
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param CheckoutSession $checkoutSession
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     * @param PaymentHelper $paymentHelper
     */
    public function __construct(PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository,
        TransactionService $transactionService, SearchCriteriaBuilder $searchCriteriaBuilder,
        CheckoutSession $checkoutSession, ScopeConfigInterface $scopeConfig, LoggerInterface $logger, PaymentHelper $paymentHelper)
    {
        $this->paymentMethodConfigurationRepository = $paymentMethodConfigurationRepository;
        $this->transactionService = $transactionService;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->checkoutSession = $checkoutSession;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->paymentHelper = $paymentHelper;
    }

    public function getConfig()
    {
        $config = [
            'payment' => [],
            'wallee' => []
        ];

        /* @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->checkoutSession->getQuote();
        // Make sure that the quote's totals are collected before generating javascript and payment page URLs.
        $quote->collectTotals();

        $integrationMethod = $this->scopeConfig->getValue('wallee_payment/checkout/integration_method',
            ScopeInterface::SCOPE_STORE, $quote->getStoreId());
        $config['wallee']['integrationMethod'] = $integrationMethod;

        $config['wallee']['restoreCartUrl'] = $quote->getStore()->getUrl('wallee_payment/checkout/restoreCart', [
            '_secure' => true
        ]);

        if ($integrationMethod == IntegrationMethod::IFRAME) {
            try {
                $config['wallee']['javascriptUrl'] = $this->transactionService->getJavaScriptUrl($quote);
            } catch (\Exception $e) {
                $this->logger->critical($e);
            }
        } else {
            try {
                $config['wallee']['lightboxUrl'] = $this->transactionService->getLightboxUrl($quote);
            } catch (\Exception $e) {
                $this->logger->critical($e);
            }
        }

        try {
            $config['wallee']['paymentPageUrl'] = $this->transactionService->getPaymentPageUrl($quote);
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }

        $searchCriteria = $this->searchCriteriaBuilder->addFilter(PaymentMethodConfigurationInterface::STATE,
            [
                PaymentMethodConfiguration::STATE_ACTIVE,
                PaymentMethodConfiguration::STATE_INACTIVE
            ], 'in')->create();

        $configurations = $this->paymentMethodConfigurationRepository->getList($searchCriteria)->getItems();
        foreach ($configurations as $configuration) {
            $methodCode = 'wallee_payment_' . $configuration->getEntityId();
            $methodInstance = $this->getPaymentMethodInstance($methodCode);
            if ($methodInstance != null) {
                $config['payment'][$methodCode] = [
                    'isActive' => true,
                    'configurationId' => $configuration->getConfigurationId(),
                    'description' => $methodInstance->getDescription(),
                    'showDescription' => $methodInstance->isShowDescription(),
                    'imageUrl' => $methodInstance->getImageUrl(),
                    'showImage' => $methodInstance->isShowImage()
                ];
            }
        }

        return $config;
    }

    /**
     *
     * @param string $methodCode
     * @return Adapter
     */
    private function getPaymentMethodInstance($methodCode)
    {
        try {
            $instance = $this->paymentHelper->getMethodInstance($methodCode);
            if ($instance instanceof Adapter) {
                return $instance;
            } else {
                return null;
            }
        } catch (\UnexpectedValueException $e) {
            return null;
        }
    }
}