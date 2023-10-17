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
 * Holds information about a webhook.
 */
class Entity
{

    private $id;

    private $name;

    private $states;

    private $notifyEveryChange;

    /**
     *
     * @param int $id
     * @param string $name
     * @param array $states
     * @param boolean $notifyEveryChange
     */
    public function __construct($id, $name, array $states, $notifyEveryChange = false)
    {
        $this->id = $id;
        $this->name = $name;
        $this->states = $states;
        $this->notifyEveryChange = $notifyEveryChange;
    }

    /**
     * Gets the entity's ID.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Gets the entity's name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Gets the entity's states.
     *
     * @return array
     */
    public function getStates()
    {
        return $this->states;
    }

    /**
     * Gets whether every change should be notified.
     *
     * @return boolean
     */
    public function isNotifyEveryChange()
    {
        return $this->notifyEveryChange;
    }
}