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

use Wallee\Payment\Api\Data\TokenInfoInterface;
use Wallee\Payment\Model\ResourceModel\TokenInfo as ResourceModel;

/**
 * Token info model.
 */
class TokenInfo extends \Magento\Framework\Model\AbstractModel implements TokenInfoInterface
{

    /**
     *
     * @var string
     */
    protected $_eventPrefix = 'wallee_payment_token_info';

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
     * Get connector id.
     *
     * @return int
     */
    public function getConnectorId()
    {
        return $this->getData(TokenInfoInterface::CONNECTOR_ID);
    }

    /**
     * Get created at timestamp.
     *
     * @return string|null
     */
    public function getCreatedAt()
    {
        return $this->getData(TokenInfoInterface::CREATED_AT);
    }

    /**
     * Get customer id.
     *
     * @return int
     */
    public function getCustomerId()
    {
        return $this->getData(TokenInfoInterface::CUSTOMER_ID);
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->getData(TokenInfoInterface::NAME);
    }

    /**
     * Get payment method id.
     *
     * @return int
     */
    public function getPaymentMethodId()
    {
        return $this->getData(TokenInfoInterface::PAYMENT_METHOD_ID);
    }

    /**
     * Get space id.
     *
     * @return int
     */
    public function getSpaceId()
    {
        return $this->getData(TokenInfoInterface::SPACE_ID);
    }

    /**
     * Get token state.
     *
     * @return string
     */
    public function getState()
    {
        return $this->getData(TokenInfoInterface::STATE);
    }

    /**
     * Get token id.
     *
     * @return int
     */
    public function getTokenId()
    {
        return $this->getData(TokenInfoInterface::TOKEN_ID);
    }
}
