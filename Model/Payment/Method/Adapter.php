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
namespace Wallee\Payment\Model\Payment\Method;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Payment\Gateway\Command\CommandManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Config\ValueHandlerPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Validator\ValidatorPoolInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use Wallee\Payment\Api\Data\PaymentMethodConfigurationInterface;
use Wallee\Payment\Api\PaymentMethodConfigurationRepositoryInterface;
use Wallee\Payment\Block\Method\Form;
use Wallee\Payment\Block\Method\Info;
use Wallee\Payment\Helper\Data as Helper;
use Wallee\Payment\Model\ApiClient;
use Wallee\Payment\Model\Service\Quote\TransactionService;

/**
 * wallee payment method adapter.
 */
class Adapter extends \Magento\Payment\Model\Method\Adapter
{

    const CAPTURE_INVOICE_REGISTRY_KEY = 'wallee_payment_capture_invoice';

    /**
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     *
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     *
     * @var PaymentMethodConfigurationRepositoryInterface
     */
    private $paymentMethodConfigurationRepository;

    /**
     *
     * @var ApiClient
     */
    private $apiClient;

    /**
     *
     * @var TransactionService
     */
    private $transactionService;

    /**
     *
     * @var Helper
     */
    private $helper;

    /**
     *
     * @var int
     */
    private $paymentMethodConfigurationId;

    /**
     *
     * @var PaymentMethodConfigurationInterface
     */
    private $paymentMethodConfiguration;

    /**
     *
     * @param LoggerInterface $logger
     * @param ManagerInterface $eventManager
     * @param ValueHandlerPoolInterface $valueHandlerPool
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository
     * @param ApiClient $apiClient
     * @param TransactionService $transactionService
     * @param Helper $helper
     * @param string $code
     * @param int $paymentMethodConfigurationId
     * @param CommandPoolInterface $commandPool
     * @param ValidatorPoolInterface $validatorPool
     * @param CommandManagerInterface $commandExecutor
     */
    public function __construct(LoggerInterface $logger, ManagerInterface $eventManager,
        ValueHandlerPoolInterface $valueHandlerPool, PaymentDataObjectFactory $paymentDataObjectFactory,
        ScopeConfigInterface $scopeConfig,
        PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository, ApiClient $apiClient,
        TransactionService $transactionService, Helper $helper, $code, $paymentMethodConfigurationId,
        CommandPoolInterface $commandPool = null, ValidatorPoolInterface $validatorPool = null,
        CommandManagerInterface $commandExecutor = null)
    {
        parent::__construct($eventManager, $valueHandlerPool, $paymentDataObjectFactory, $code, Form::class, Info::class,
            $commandPool, $validatorPool, $commandExecutor, $logger);
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->paymentMethodConfigurationRepository = $paymentMethodConfigurationRepository;
        $this->apiClient = $apiClient;
        $this->transactionService = $transactionService;
        $this->helper = $helper;
        $this->paymentMethodConfigurationId = $paymentMethodConfigurationId;
    }

    /**
     * Gets the ID of the payment method configuration.
     *
     * @return number
     */
    public function getPaymentMethodConfigurationId()
    {
        return $this->paymentMethodConfigurationId;
    }

    /**
     * Gets the payment method configuration.
     *
     * @return \Wallee\Payment\Model\PaymentMethodConfiguration
     */
    public function getPaymentMethodConfiguration()
    {
        if ($this->paymentMethodConfiguration == null) {
            $this->paymentMethodConfiguration = $this->paymentMethodConfigurationRepository->get(
                $this->paymentMethodConfigurationId);
        }
        return $this->paymentMethodConfiguration;
    }

    /**
     * Gets whether the description of the payment method should be displayed in the checkout.
     *
     * @return boolean
     */
    public function isShowDescription()
    {
        return (bool) $this->getConfigData('show_description');
    }

    /**
     * Gets whether the image of the payment method should be displayed in the checkout.
     *
     * @return boolean
     */
    public function isShowImage()
    {
        return (bool) $this->getConfigData('show_image');
    }

    /**
     * Gets the description of the payment method.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->getConfigData('description');
    }

    /**
     * Gets the URL to the payment method's image.
     *
     * @return string
     */
    public function getImageUrl()
    {
        $spaceViewId = $this->scopeConfig->getValue('wallee_payment/general/space_view_id');
        $language = $this->scopeConfig->getValue('general/locale/code', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        return $this->helper->getResourceUrl($this->getPaymentMethodConfiguration()
            ->getImage(), $language, $this->getPaymentMethodConfiguration()
            ->getSpaceId(), $spaceViewId);
    }

    public function isAvailable(CartInterface $quote = null)
    {
        $this->logger->debug("ADAPTER::isAvailable - INIT");
        if ($quote != null && $quote->getGrandTotal() < 0.0001) {
            $this->logger->debug("ADAPTER::isAvailable - FINISH");
            return false;
        }

        if (!parent::isAvailable($quote)) {
            $this->logger->debug("ADAPTER::isAvailable - FINISH");
            return false;
        }

        if ($quote == null && !$this->apiClient->checkApiClientData()) {
            $this->logger->debug("ADAPTER::isAvailable - FINISH");
            return false;
        }

        $spaceId = $this->scopeConfig->getValue(
            'wallee_payment/general/space_id',
            ScopeInterface::SCOPE_STORE,
            $quote->getStoreId()
        );
        $paymentMethodConfiguration = $this->getPaymentMethodConfiguration();

        if (empty($spaceId)) {
            $this->logger->debug("ADAPTER::isAvailable - FINISH");
            return false;
        }

        try {
            if (!$quote->getData('wallee_payment_payment_options_response')
             || ($quote->getData('wallee_payment_payment_tmp_currency') != $quote->getQuoteCurrencyCode())) {
                $payment_options_response = $this->transactionService->getPossiblePaymentMethods($quote);
                $quote->setData('wallee_payment_payment_options_response', $payment_options_response);
                $quote->setData('wallee_payment_payment_tmp_currency', $quote->getQuoteCurrencyCode());
                $quote->save();
            }

            $payment_options_response = $quote->getData('wallee_payment_payment_options_response');

            foreach ($payment_options_response as $pay) {
                if ($pay->getId() == $paymentMethodConfiguration->getConfigurationId()) {
                    return true;
                }

            }
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $this->logger->debug("ADAPTER::isAvailable - FINISH");
            return false;
        }
        $this->logger->debug("ADAPTER::isAvailable - FINISH");
        return false;
    }
}