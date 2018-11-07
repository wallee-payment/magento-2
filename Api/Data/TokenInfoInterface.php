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
namespace Wallee\Payment\Api\Data;

use Magento\Framework\Api\ExtensibleDataInterface;

/**
 * Token info interface.
 *
 * A token info is an entity that holds information about a wallee token.
 *
 * @api
 */
interface TokenInfoInterface extends ExtensibleDataInterface
{

    /**
     * Constants for keys of data array.
     * Identical to the name of the getter in snake case.
     */
    /**
     * Connector ID.
     */
    const CONNECTOR_ID = 'connector_id';

    /**
     * Create-at timestamp.
     */
    const CREATED_AT = 'created_at';

    /**
     * Customer ID.
     */
    const CUSTOMER_ID = 'customer_id';

    /**
     * Entity ID.
     */
    const ENTITY_ID = 'entity_id';

    /**
     * Name.
     */
    const NAME = 'name';

    /**
     * Payment method ID.
     */
    const PAYMENT_METHOD_ID = 'payment_method_id';

    /**
     * Space ID.
     */
    const SPACE_ID = 'space_id';

    /**
     * State.
     */
    const STATE = 'state';

    /**
     * Token ID.
     */
    const TOKEN_ID = 'token_id';

    /**
     * Gets the connector ID of the token info.
     *
     * @return int Connector ID.
     */
    public function getConnectorId();

    /**
     * Gets the created-at timestamp of the token info.
     *
     * @return string|null Created-at timestamp.
     */
    public function getCreatedAt();

    /**
     * Gets the customer ID of the token info.
     *
     * @return int
     */
    public function getCustomerId();

    /**
     * Gets the ID of the token info.
     *
     * @return int Token info ID.
     */
    public function getEntityId();

    /**
     * Gets the name of the token info.
     *
     * @return string
     */
    public function getName();

    /**
     * Gets the ID of the payment method of the token info.
     *
     * @return int Payment method ID.
     */
    public function getPaymentMethodId();

    /**
     * Gets the space ID of the token info.
     *
     * @return int Space ID.
     */
    public function getSpaceId();

    /**
     * Gets the state of the token info.
     *
     * @return int State.
     */
    public function getState();

    /**
     * Gets the token ID of the token info.
     *
     * @return int Token ID.
     */
    public function getTokenId();
}