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
namespace Wallee\Payment\Model;

use Wallee\Payment\Api\Data\TransactionInfoInterface;
use Wallee\Payment\Model\ResourceModel\TransactionInfo as ResourceModel;

/**
 * Transaction info model.
 */
class TransactionInfo extends \Magento\Framework\Model\AbstractModel implements TransactionInfoInterface
{

    /**
     *
     * @var string
     */
    protected $_eventPrefix = 'wallee_payment_transaction_info';

    /**
     *
     * @var string
     */
    protected $_eventObject = 'info';

    /**
     * Initialize model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ResourceModel::class);
    }

    /**
     * Get authorization amount.
     *
     * @return float.
     */
    public function getAuthorizationAmount()
    {
        return $this->getData(TransactionInfoInterface::AUTHORIZATION_AMOUNT);
    }

    /**
     * Get connector id.
     *
     * @return int.
     */
    public function getConnectorId()
    {
        return $this->getData(TransactionInfoInterface::CONNECTOR_ID);
    }

    /**
     * Get created at timestamp.
     *
     * @return string|null
     */
    public function getCreatedAt()
    {
        return $this->getData(TransactionInfoInterface::CREATED_AT);
    }

    /**
     * Get currency.
     *
     * @return string
     */
    public function getCurrency()
    {
        return $this->getData(TransactionInfoInterface::CURRENCY);
    }

    /**
     * Get failure reason.
     *
     * @return string
     */
    public function getFailureReason()
    {
        return $this->getData(TransactionInfoInterface::FAILURE_REASON);
    }

    /**
     * Get image.
     *
     * @return string
     */
    public function getImage()
    {
        return $this->getData(TransactionInfoInterface::IMAGE);
    }

    /**
     * Get labels.
     *
     * @return array
     */
    public function getLabels()
    {
        return $this->getData(TransactionInfoInterface::LABELS);
    }

    /**
     * Get language.
     *
     * @return string
     */
    public function getLanguage()
    {
        return $this->getData(TransactionInfoInterface::LANGUAGE);
    }

    /**
     * Get order id.
     *
     * @return string
     */
    public function getOrderId()
    {
        return $this->getData(TransactionInfoInterface::ORDER_ID);
    }

    /**
     * Get payment method id.
     *
     * @return string
     */
    public function getPaymentMethodId()
    {
        return $this->getData(TransactionInfoInterface::PAYMENT_METHOD_ID);
    }

    /**
     * Get space id.
     *
     * @return int
     */
    public function getSpaceId()
    {
        return $this->getData(TransactionInfoInterface::SPACE_ID);
    }

    /**
     * Get space view id.
     *
     * @return int
     */
    public function getSpaceViewId()
    {
        return $this->getData(TransactionInfoInterface::SPACE_VIEW_ID);
    }

    /**
     * Get transaction state.
     *
     * @return string
     */
    public function getState()
    {
        return $this->getData(TransactionInfoInterface::STATE);
    }

    /**
     * Get transaction id.
     *
     * @return int
     */
    public function getTransactionId()
    {
        return $this->getData(TransactionInfoInterface::TRANSACTION_ID);
    }

    /**
     * Get success URL.
     *
     * @return string
     */
    public function getSuccessUrl()
    {
        return $this->getData(TransactionInfoInterface::SUCCESS_URL);
    }

    /**
     * Get failure URL.
     *
     * @return string|null
     */
    public function getFailureUrl()
    {
        return $this->getData(TransactionInfoInterface::FAILURE_URL);
    }

    /**
     * Check whether external payment URLs are set.
     *
     * @return bool
     */
    public function isExternalPaymentUrl()
    {
        return !empty($this->getSuccessUrl()) && !empty($this->getFailureUrl());
    }
}
