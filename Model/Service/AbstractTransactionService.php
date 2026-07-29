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
namespace Wallee\Payment\Model\Service;

use Magento\Customer\Model\CustomerRegistry;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Wallee\Payment\Model\ApiClient;
use Wallee\Sdk\Model\Gender;
use Wallee\Sdk\Service\TransactionService;

/**
 * Abstract service to handle transactions.
 */
abstract class AbstractTransactionService
{

    /**
     *
     * @var CustomerRegistry
     */
    private $customerRegistry;

    /**
     *
     * @var ApiClient
     */
    private $apiClient;

    /**
     *
     * @var CookieManagerInterface
     */
    private $cookieManager;

    /**
     *
     * @param CustomerRegistry $customerRegistry
     * @param ApiClient $apiClient
     * @param CookieManagerInterface $cookieManager
     */
    public function __construct(
        CustomerRegistry $customerRegistry,
        ApiClient $apiClient,
        CookieManagerInterface $cookieManager
    ) {
        $this->customerRegistry = $customerRegistry;
        $this->apiClient = $apiClient;
        $this->cookieManager = $cookieManager;
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
        return $this->apiClient->getService(TransactionService::class)->read($spaceId, $transactionId);
    }

    /**
     * Gets the device session identifier from the cookie.
     *
     * @return string|NULL
     */
    protected function getDeviceSessionIdentifier()
    {
        return $this->cookieManager->getCookie('wallee_device_id');
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
            return $this->customerRegistry->retrieve($customerId)->getTaxvat();
        } else {
            return null;
        }
    }

    /**
     * Gets the customer's gender as a Magento numeric code (1 = male, 2 = female),
     * falling back to the customer registry when the quote/order does not carry
     * a value of its own.
     *
     * @param string|int|null $gender
     * @param int|null $customerId
     * @return int|null
     */
    protected function getRawGender($gender, $customerId)
    {
        if ($gender === null && !empty($customerId)) {
            $gender = $this->customerRegistry->retrieve($customerId)->getGender();
        }

        if ((int) $gender === 1) {
            return 1;
        }
        if ((int) $gender === 2) {
            return 2;
        }
        return null;
    }

    /**
     * Gets the customer's gender as an SDK Gender constant.
     *
     * @param string|int|null $gender
     * @param int|null $customerId
     * @return string|null
     */
    protected function getGender($gender, $customerId)
    {
        $raw = $this->getRawGender($gender, $customerId);
        if ($raw === 1) {
            return Gender::MALE;
        }
        if ($raw === 2) {
            return Gender::FEMALE;
        }
        return null;
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
            $customer = $this->customerRegistry->retrieve($customerId);
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
            $customer = $this->customerRegistry->retrieve($customerId);
            $dateOfBirth = $customer->getDob();
        }

        if ($dateOfBirth !== null) {
            return \substr($dateOfBirth, 0, 10);
        }
    }
}
