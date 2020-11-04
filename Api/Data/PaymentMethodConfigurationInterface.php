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
namespace Wallee\Payment\Api\Data;

use Magento\Framework\Api\ExtensibleDataInterface;

/**
 * Payment method configuration is an entity that holds information about a wallee payment method.
 *
 * @api
 */
interface PaymentMethodConfigurationInterface extends ExtensibleDataInterface
{

    /**
     * Constants for keys of data array.
     * Identical to the name of the getter in snake case.
     */
    /**
     * Configuration ID.
     */
    const CONFIGURATION_ID = 'configuration_id';

    /**
     * Configuration name.
     */
    const CONFIGURATION_NAME = 'configuration_name';

    /**
     * Create-at timestamp.
     */
    const CREATED_AT = 'created_at';

    /**
     * Description.
     */
    const DESCRIPTION = 'description';

    /**
     * Entity ID.
     */
    const ENTITY_ID = 'entity_id';

    /**
     * Image.
     */
    const IMAGE = 'image';

    /**
     * Sort order.
     */
    const SORT_ORDER = 'sort_order';

    /**
     * Space ID.
     */
    const SPACE_ID = 'space_id';

    /**
     * State.
     */
    const STATE = 'state';

    /**
     * Title.
     */
    const TITLE = 'title';

    /**
     * Updated-at timestamp.
     */
    const UPDATED_AT = 'updated_at';

    /**
     * Gets the ID of the payment method configuration.
     *
     * @return int Configuration ID.
     */
    public function getConfigurationId();

    /**
     * Gets the name of the payment method configuration.
     *
     * @return string Configuration name.
     */
    public function getConfigurationName();

    /**
     * Gets the created-at timestamp of the payment method configuration.
     *
     * @return string|null Created-at timestamp.
     */
    public function getCreatedAt();

    /**
     * Gets the translated description of the payment method configuration.
     *
     * @return array Description.
     */
    public function getDescription();

    /**
     * Gets the ID of the payment method configuration.
     *
     * @return int Payment method configuration ID.
     */
    public function getEntityId();

    /**
     * Gets the image of the payment method configuration.
     *
     * @return string Image.
     */
    public function getImage();

    /**
     * Gets the sort order of the payment method configuration.
     *
     * @return int Sort order.
     */
    public function getSortOrder();

    /**
     * Gets the space ID of the payment method configuration.
     *
     * @return int Space ID.
     */
    public function getSpaceId();

    /**
     * Gets the state of the payment method configuration.
     *
     * @return int State.
     */
    public function getState();

    /**
     * Gets the translated title of the payment method configuration.
     *
     * @return array Title.
     */
    public function getTitle();

    /**
     * Gets the updated-at timestamp of the payment method configuration
     *
     * @return string Updated-at timestamp.
     */
    public function getUpdatedAt();
}