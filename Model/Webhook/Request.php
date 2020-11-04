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
namespace Wallee\Payment\Model\Webhook;

/**
 * Holds information about a webhook request.
 */
class Request
{

    private $eventId;

    private $entityId;

    private $listenerEntityId;

    private $listenerEntityTechnicalName;

    private $spaceId;

    private $webhookListenerId;

    private $timestamp;

    /**
     *
     * @param \stdClass $model
     */
    public function __construct($model)
    {
        $this->eventId = self::checkArgument($model, 'eventId');
        $this->entityId = self::checkArgument($model, 'entityId');
        $this->listenerEntityId = self::checkArgument($model, 'listenerEntityId');
        $this->listenerEntityTechnicalName = self::checkArgument($model, 'listenerEntityTechnicalName');
        $this->spaceId = self::checkArgument($model, 'spaceId');
        $this->webhookListenerId = self::checkArgument($model, 'webhookListenerId');
        $this->timestamp = self::checkArgument($model, 'timestamp');
    }

    private static function checkArgument($array, $key)
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        } else {
            throw new \InvalidArgumentException('Invalid request.');
        }
    }

    /**
     * Returns the webhook event's ID.
     *
     * @return int
     */
    public function getEventId()
    {
        return $this->eventId;
    }

    /**
     * Returns the ID of the webhook event's entity.
     *
     * @return int
     */
    public function getEntityId()
    {
        return $this->entityId;
    }

    /**
     * Returns the ID of the webhook's listener entity.
     *
     * @return int
     */
    public function getListenerEntityId()
    {
        return $this->listenerEntityId;
    }

    /**
     * Returns the technical name of the webhook's listener entity.
     *
     * @return string
     */
    public function getListenerEntityTechnicalName()
    {
        return $this->listenerEntityTechnicalName;
    }

    /**
     * Returns the space ID.
     *
     * @return int
     */
    public function getSpaceId()
    {
        return $this->spaceId;
    }

    /**
     * Returns the ID of the webhook listener.
     *
     * @return int
     */
    public function getWebhookListenerId()
    {
        return $this->webhookListenerId;
    }

    /**
     * Returns the webhook's timestamp.
     *
     * @return string
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }
}