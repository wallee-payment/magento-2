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

use Magento\Framework\Model\AbstractModel;
use Wallee\Payment\Api\Data\PaymentMethodConfigurationInterface;
use Wallee\Payment\Model\ResourceModel\PaymentMethodConfiguration as ResourceModel;

/**
 * Payment method configuration model.
 */
class PaymentMethodConfiguration extends AbstractModel implements PaymentMethodConfigurationInterface
{

    /**
     * Payment method configuration states
     */
    const STATE_ACTIVE = 1;

    const STATE_INACTIVE = 2;

    const STATE_HIDDEN = 3;

    /**
     * Event prefix
     *
     * @var string
     */
    protected $_eventPrefix = 'wallee_payment_method_configuration';

    /**
     * Event object
     *
     * @var string
     */
    protected $_eventObject = 'configuration';

    /**
     * Initialize model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ResourceModel::class);
    }

    public function getConfigurationId()
    {
        return $this->getData(PaymentMethodConfigurationInterface::CONFIGURATION_ID);
    }

    public function getConfigurationName()
    {
        return $this->getData(PaymentMethodConfigurationInterface::CONFIGURATION_NAME);
    }

    public function getCreatedAt()
    {
        return $this->getData(PaymentMethodConfigurationInterface::CREATED_AT);
    }

    public function getDescription()
    {
        return $this->getData(PaymentMethodConfigurationInterface::DESCRIPTION);
    }

    public function getImage()
    {
        return $this->getData(PaymentMethodConfigurationInterface::IMAGE);
    }

    public function getSortOrder()
    {
        return $this->getData(PaymentMethodConfigurationInterface::SORT_ORDER);
    }

    public function getSpaceId()
    {
        return $this->getData(PaymentMethodConfigurationInterface::SPACE_ID);
    }

    public function getState()
    {
        return $this->getData(PaymentMethodConfigurationInterface::STATE);
    }

    public function getTitle()
    {
        return $this->getData(PaymentMethodConfigurationInterface::TITLE);
    }

    public function getUpdatedAt()
    {
        return $this->getData(PaymentMethodConfigurationInterface::UPDATED_AT);
    }
}