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
use Wallee\Payment\Api\PaymentMethodConfigurationRepositoryInterface;
use Wallee\Payment\Block\Method\Form;
use Wallee\Payment\Block\Method\Info;
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
    protected $_logger;

    /**
     *
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     *
     * @var PaymentMethodConfigurationRepositoryInterface
     */
    protected $_paymentMethodConfigurationRepository;

    /**
     *
     * @var ApiClient
     */
    protected $_apiClient;

    /**
     *
     * @var TransactionService
     */
    protected $_transactionService;

    /**
     *
     * @var int
     */
    private $paymentMethodConfigurationId;

    /**
     *
     * @var \Wallee\Payment\Model\PaymentMethodConfiguration
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
        TransactionService $transactionService, $code, $paymentMethodConfigurationId,
        CommandPoolInterface $commandPool = null, ValidatorPoolInterface $validatorPool = null,
        CommandManagerInterface $commandExecutor = null)
    {
        parent::__construct($eventManager, $valueHandlerPool, $paymentDataObjectFactory, $code, Form::class, Info::class,
            $commandPool, $validatorPool, $commandExecutor, $logger);
        $this->_logger = $logger;
        $this->_scopeConfig = $scopeConfig;
        $this->_paymentMethodConfigurationRepository = $paymentMethodConfigurationRepository;
        $this->_apiClient = $apiClient;
        $this->_transactionService = $transactionService;
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
            $this->paymentMethodConfiguration = $this->_paymentMethodConfigurationRepository->get(
                $this->paymentMethodConfigurationId);
        }
        return $this->paymentMethodConfiguration;
    }

    public function isAvailable(CartInterface $quote = null)
    {
        if ($quote != null && $quote->getGrandTotal() < 0.0001) {
            return false;
        }

        if (! parent::isAvailable($quote)) {
            return false;
        }

        if ($quote != null && $this->_apiClient->checkApiClientData()) {
            $spaceId = $this->_scopeConfig->getValue('wallee_payment/general/space_id',
                ScopeInterface::SCOPE_STORE, $quote->getStoreId());
            if (! empty($spaceId)) {
                try {
                    $possiblePaymentMethods = $this->_transactionService->getPossiblePaymentMethods($quote);
                    if (! $this->isPaymentMethodPossible($possiblePaymentMethods)) {
                        return false;
                    }
                } catch (\Exception $e) {
                    $this->_logger->critical($e);
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }

        return true;
    }

    /**
     * Gets whether the selected payment method can be used.
     *
     * @param \Wallee\Sdk\Model\PaymentMethodConfiguration[] $possiblePaymentMethods
     * @return boolean
     */
    protected function isPaymentMethodPossible(array $possiblePaymentMethods)
    {
        foreach ($possiblePaymentMethods as $possiblePaymentMethod) {
            if ($possiblePaymentMethod->getId() == $this->getPaymentMethodConfiguration()->getConfigurationId()) {
                return true;
            }
        }
        return false;
    }
}