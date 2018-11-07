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
 * Refund job interface.
 *
 * A refund job is an entity that holds data about a pending refund to achieve reliability.
 *
 * @api
 */
interface RefundJobInterface extends ExtensibleDataInterface
{

    /**
     * Constants for keys of data array.
     * Identical to the name of the getter in snake case.
     */
    /**
     * Create-at timestamp.
     */
    const CREATED_AT = 'created_at';

    /**
     * Entity ID.
     */
    const ENTITY_ID = 'entity_id';

    /**
     * External ID.
     */
    const EXTERNAL_ID = 'external_id';

    /**
     * Order ID.
     */
    const ORDER_ID = 'order_id';

    /**
     * Invoice ID.
     */
    const INVOICE_ID = 'invoice_id';

    /**
     * Payment method ID.
     */
    const REFUND = 'refund';

    /**
     * Space ID.
     */
    const SPACE_ID = 'space_id';

    /**
     * Gets the created-at timestamp of the refund job.
     *
     * @return string|null Created-at timestamp.
     */
    public function getCreatedAt();

    /**
     * Gets the ID of the refund job.
     *
     * @return int Refund job ID.
     */
    public function getEntityId();

    /**
     * Gets the external ID of the refund job.
     *
     * @return string External ID.
     */
    public function getExternalId();

    /**
     * Gets the ID of the order of the refund job.
     *
     * @return int Order ID.
     */
    public function getOrderId();

    /**
     * Gets the ID of the invoice of the refund job.
     *
     * @return int Invoice ID.
     */
    public function getInvoiceId();

    /**
     * Gets the refund of the refund job.
     *
     * @return string Refund.
     */
    public function getRefund();

    /**
     * Gets the space ID of the refund job.
     *
     * @return int Space ID.
     */
    public function getSpaceId();
}