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
namespace Wallee\Payment\Model\Service;

use Magento\Customer\Model\CustomerRegistry;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Wallee\Payment\Api\PaymentMethodConfigurationManagementInterface;
use Wallee\Payment\Helper\Data as Helper;
use Wallee\Payment\Model\ApiClient;
use Wallee\Sdk\Model\Gender;
use Wallee\Sdk\Model\Transaction;
use Wallee\Sdk\Service\TransactionService;

/**
 * Abstract service to handle transactions.
 */
abstract class AbstractTransactionService
{

    /**
     *
     * @var Helper
     */
    protected $_helper;

    /**
     *
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     *
     * @var CustomerRegistry
     */
    protected $_customerRegistry;

    /**
     *
     * @var CartRepositoryInterface
     */
    protected $_quoteRepository;

    /**
     *
     * @var PaymentMethodConfigurationManagementInterface
     */
    protected $_paymentMethodConfigurationManagement;

    /**
     *
     * @var ApiClient
     */
    protected $_apiClient;

    /**
     *
     * @param Helper $helper
     * @param ScopeConfigInterface $scopeConfig
     * @param CustomerRegistry $customerRegistry
     * @param CartRepositoryInterface $quoteRepository
     * @param PaymentMethodConfigurationManagementInterface $paymentMethodConfigurationManagement
     * @param ApiClient $apiClient
     */
    public function __construct(Helper $helper, ScopeConfigInterface $scopeConfig, CustomerRegistry $customerRegistry,
        CartRepositoryInterface $quoteRepository,
        PaymentMethodConfigurationManagementInterface $paymentMethodConfigurationManagement, ApiClient $apiClient)
    {
        $this->_helper = $helper;
        $this->_scopeConfig = $scopeConfig;
        $this->_customerRegistry = $customerRegistry;
        $this->_quoteRepository = $quoteRepository;
        $this->_paymentMethodConfigurationManagement = $paymentMethodConfigurationManagement;
        $this->_apiClient = $apiClient;
    }

    /**
     * Updates the payment method configurations with the given data.
     *
     * @param \Wallee\Sdk\Model\PaymentMethodConfiguration[] $paymentMethods
     */
    protected function updatePaymentMethodConfigurations($paymentMethods)
    {
        foreach ($paymentMethods as $paymentMethod) {
            $this->_paymentMethodConfigurationManagement->update($paymentMethod);
        }
    }

    /**
     * Gets the transaction by its ID.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @return Transaction
     */
    public function getTransaction($spaceId, $transactionId)
    {
        return $this->_apiClient->getService(TransactionService::class)->read($spaceId, $transactionId);
    }

    /**
     * Updates the transaction information on the quote.
     *
     * @param Quote $quote
     * @param Transaction $transaction
     */
    protected function updateQuote(Quote $quote, Transaction $transaction)
    {
        $quote->setWalleeSpaceId($transaction->getLinkedSpaceId());
        $quote->setWalleeTransactionId($transaction->getId());
        $this->_quoteRepository->save($quote);
    }

    /**
     * Gets the customer's tax number.
     *
     * @param string $taxNumber
     * @param int $customerId
     * @return string
     */
    protected function getTaxNumber($taxNumber, $customerId)
    {
        if ($taxNumber !== null) {
            return $taxNumber;
        } elseif (! empty($customerId)) {
            return $this->_customerRegistry->retrieve($customerId)->getTaxvat();
        } else {
            return null;
        }
    }

    /**
     * Gets the customer's gender.
     *
     * @param string $gender
     * @param int $customerId
     * @return string
     */
    protected function getGender($gender, $customerId)
    {
        if ($gender == null && ! empty($customerId)) {
            $gender = $this->_customerRegistry->retrieve($customerId)->getGender();
        }

        if ($gender == 1) {
            return Gender::FEMALE;
        } elseif ($gender == 1) {
            return Gender::MALE;
        } else {
            return null;
        }
    }

    /**
     * Gets the customer's email address.
     *
     * @param string $customerEmailAddress
     * @param int $customerId
     * @return string
     */
    protected function getCustomerEmailAddress($customerEmailAddress, $customerId)
    {
        if ($customerEmailAddress != null) {
            return $customerEmailAddress;
        } elseif (! empty($customerId)) {
            $customer = $this->_customerRegistry->retrieve($customerId);
            $customerMail = $customer->getEmail();
            if (! empty($customerMail)) {
                return $customerMail;
            } else {
                return null;
            }
        }
    }

    /**
     * Gets the customer's date of birth.
     *
     * @param string $dateOfBirth
     * @param int $customerId
     * @return string
     */
    protected function getDateOfBirth($dateOfBirth, $customerId)
    {
        if ($dateOfBirth === null && ! empty($customerId)) {
            $customer = $this->_customerRegistry->retrieve($customerId);
            $dateOfBirth = $customer->getDob();
        }

        if ($dateOfBirth !== null) {
            $date = new \DateTime($dateOfBirth);
            return $date->format(\DateTime::W3C);
        }
    }
}