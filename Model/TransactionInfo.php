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
namespace Wallee\Payment\Model;

use Wallee\Payment\Api\Data\TransactionInfoInterface;
use Wallee\Payment\Model\ResourceModel\TransactionInfo as ResourceModel;

/**
 * Transaction info model.
 */
class TransactionInfo extends \Magento\Framework\Model\AbstractModel implements TransactionInfoInterface
{

    /**
     * Event prefix
     *
     * @var string
     */
    protected $_eventPrefix = 'wallee_payment_transaction_info';

    /**
     * Event object
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

    public function getAuthorizationAmount()
    {
        return $this->getData(TransactionInfoInterface::AUTHORIZATION_AMOUNT);
    }

    public function getConnectorId()
    {
        return $this->getData(TransactionInfoInterface::CONNECTOR_ID);
    }

    public function getCreatedAt()
    {
        return $this->getData(TransactionInfoInterface::CREATED_AT);
    }

    public function getCurrency()
    {
        return $this->getData(TransactionInfoInterface::CURRENCY);
    }

    public function getFailureReason()
    {
        return $this->getData(TransactionInfoInterface::FAILURE_REASON);
    }

    public function getImage()
    {
        return $this->getData(TransactionInfoInterface::IMAGE);
    }

    public function getLabels()
    {
        return $this->getData(TransactionInfoInterface::LABELS);
    }

    public function getLanguage()
    {
        return $this->getData(TransactionInfoInterface::LANGUAGE);
    }

    public function getOrderId()
    {
        return $this->getData(TransactionInfoInterface::ORDER_ID);
    }

    public function getPaymentMethodId()
    {
        return $this->getData(TransactionInfoInterface::PAYMENT_METHOD_ID);
    }

    public function getSpaceId()
    {
        return $this->getData(TransactionInfoInterface::SPACE_ID);
    }

    public function getSpaceViewId()
    {
        return $this->getData(TransactionInfoInterface::SPACE_VIEW_ID);
    }

    public function getState()
    {
        return $this->getData(TransactionInfoInterface::STATE);
    }

    public function getTransactionId()
    {
        return $this->getData(TransactionInfoInterface::TRANSACTION_ID);
    }

	public function getSuccessUrl()
	{
		return $this->getData(TransactionInfoInterface::SUCCESS_URL);
	}

	public function getFailureUrl()
	{
		return $this->getData(TransactionInfoInterface::FAILURE_URL);
	}

	public function isExternalPaymentUrl()
	{
		return !empty($this->getSuccessUrl()) && !empty($this->getFailureUrl());
	}
}
