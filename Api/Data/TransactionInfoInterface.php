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
 * Transaction info interface.
 *
 * A transaction info is an entity that holds information about a wallee transaction.
 *
 * @api
 */
interface TransactionInfoInterface extends ExtensibleDataInterface
{

    /**
     * Constants for keys of data array.
     * Identical to the name of the getter in snake case.
     */
    /**
     * Authorization amount.
     */
    const AUTHORIZATION_AMOUNT = 'authorization_amount';

    /**
     * Connector ID.
     */
    const CONNECTOR_ID = 'connector_id';

    /**
     * Create-at timestamp.
     */
    const CREATED_AT = 'created_at';

    /**
     * Currency.
     */
    const CURRENCY = 'currency';

    /**
     * Entity ID.
     */
    const ENTITY_ID = 'entity_id';

    /**
     * Failure reason.
     */
    const FAILURE_REASON = 'failure_reason';

    /**
     * Image.
     */
    const IMAGE = 'image';

    /**
     * Labels.
     */
    const LABELS = 'labels';

    /**
     * Language.
     */
    const LANGUAGE = 'language';

    /**
     * Order ID.
     */
    const ORDER_ID = 'order_id';

    /**
     * Payment method ID.
     */
    const PAYMENT_METHOD_ID = 'payment_method_id';

    /**
     * Space ID.
     */
    const SPACE_ID = 'space_id';

    /**
     * Space view ID.
     */
    const SPACE_VIEW_ID = 'space_view_id';

    /**
     * State.
     */
    const STATE = 'state';

    /**
     * Transaction ID.
     */
    const TRANSACTION_ID = 'transaction_id';

    /**
     * Success URL to redirect the customer after placing the order.
     */
	const SUCCESS_URL = 'success_url';

	/**
	 * Failure URL to redirect the customer after placing the order.
	 */
	const FAILURE_URL = 'failure_url';

    /**
     * Gets the authorization amount of the transaction info.
     *
     * @return float Authorization amount.
     */
    public function getAuthorizationAmount();

    /**
     * Gets the connector ID of the transaction info.
     *
     * @return int Connector ID.
     */
    public function getConnectorId();

    /**
     * Gets the created-at timestamp of the transaction info.
     *
     * @return string|null Created-at timestamp.
     */
    public function getCreatedAt();

    /**
     * Gets the currency of the transaction info.
     *
     * @return string Currency.
     */
    public function getCurrency();

    /**
     * Gets the ID of the transaction info.
     *
     * @return int Transaction info ID.
     */
    public function getEntityId();

    /**
     * Gets the failure reason of the transaction info.
     *
     * @return string Failure reason.
     */
    public function getFailureReason();

    /**
     * Gets the image of the transaction info.
     *
     * @return string Image.
     */
    public function getImage();

    /**
     * Gets the labels of the transaction info.
     *
     * @return array Labels.
     */
    public function getLabels();

    /**
     * Gets the language of the transaction info.
     *
     * @return string Language.
     */
    public function getLanguage();

    /**
     * Gets the ID of the order of the transaction info.
     *
     * @return int Order ID.
     */
    public function getOrderId();

    /**
     * Gets the ID of the payment method of the transaction info.
     *
     * @return int Payment method ID.
     */
    public function getPaymentMethodId();

    /**
     * Gets the space ID of the transaction info.
     *
     * @return int Space ID.
     */
    public function getSpaceId();

    /**
     * Gets the ID of the space view of the transaction info.
     *
     * @return int Space view ID.
     */
    public function getSpaceViewId();

    /**
     * Gets the state of the transaction info.
     *
     * @return int State.
     */
    public function getState();

    /**
     * Gets the ID of the transaction of the transaction info.
     *
     * @return int Transaction ID.
     */
    public function getTransactionId();

    /**
     * Gets the success URL to redirection of the transaction info.
     *
     * @return int Transaction ID.
     */
    public function getSuccessUrl();

    /**
	 * Gets the failure URL to redirection of the transaction info.
     *
     * @return int Transaction ID.
     */
    public function getFailureUrl();

	/**
	 * Check if the transaction is an external payment.
	 *
	 * @return bool
	 */
	public function isExternalPaymentUrl();
}