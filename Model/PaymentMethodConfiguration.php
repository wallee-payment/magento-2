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

use Magento\Framework\Model\AbstractModel;
use Wallee\Payment\Api\Data\PaymentMethodConfigurationInterface;
use Wallee\Payment\Model\ResourceModel\PaymentMethodConfiguration as ResourceModel;

/**
 * Payment method configuration model.
 */
class PaymentMethodConfiguration extends AbstractModel implements PaymentMethodConfigurationInterface
{

    /**
     * @var int
     */
    public const STATE_ACTIVE = 1;

    /**
     * @var int
     */
    public const STATE_INACTIVE = 2;

    /**
     * @var int
     */
    public const STATE_HIDDEN = 3;

    /**
     *
     * @var string
     */
    protected $_eventPrefix = 'wallee_payment_method_configuration';

    /**
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

    /**
     * Get configuration ID.
     *
     * @return int
     */
    public function getConfigurationId()
    {
        return $this->getData(PaymentMethodConfigurationInterface::CONFIGURATION_ID);
    }

    /**
     * Get configuration name.
     *
     * @return string
     */
    public function getConfigurationName()
    {
        return $this->getData(PaymentMethodConfigurationInterface::CONFIGURATION_NAME);
    }

    /**
     * Get created at timestamp.
     *
     * @return string|null
     */
    public function getCreatedAt()
    {
        return $this->getData(PaymentMethodConfigurationInterface::CREATED_AT);
    }

    /**
     * Get description.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->getData(PaymentMethodConfigurationInterface::DESCRIPTION);
    }

    /**
     * Get image.
     *
     * @return string
     */
    public function getImage()
    {
        return $this->getData(PaymentMethodConfigurationInterface::IMAGE);
    }

    /**
     * Get sort order.
     *
     * @return int
     */
    public function getSortOrder()
    {
        return $this->getData(PaymentMethodConfigurationInterface::SORT_ORDER);
    }

    /**
     * Get space id.
     *
     * @return int
     */
    public function getSpaceId()
    {
        return $this->getData(PaymentMethodConfigurationInterface::SPACE_ID);
    }

    /**
     * Get state.
     *
     * @return string
     */
    public function getState()
    {
        return $this->getData(PaymentMethodConfigurationInterface::STATE);
    }

    /**
     * Get title.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->getData(PaymentMethodConfigurationInterface::TITLE);
    }

    /**
     * Get updated at timestamp.
     *
     * @return string|null
     */
    public function getUpdatedAt()
    {
        return $this->getData(PaymentMethodConfigurationInterface::UPDATED_AT);
    }
}
