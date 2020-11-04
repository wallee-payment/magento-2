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
namespace Wallee\Payment\Block\Method;

use Wallee\Sdk\Model\LabelDescriptorGroup;

/**
 * Holds information about a label group that are needed to render the labels in the backend.
 */
class LabelGroup
{

    /**
     *
     * @var LabelDescriptorGroup
     */
    private $descriptor;

    /**
     *
     * @var Label[]
     */
    private $labels = [];

    /**
     *
     * @param LabelDescriptorGroup $descriptor
     * @param Label[] $labels
     */
    public function __construct(LabelDescriptorGroup $descriptor, array $labels)
    {
        $this->descriptor = $descriptor;
        $this->labels = $labels;
    }

    /**
     * Gets the group descriptor's ID.
     *
     * @return int
     */
    public function getId()
    {
        return $this->descriptor->getId();
    }

    /**
     * Gets the group descriptor's name.
     *
     * @return array
     */
    public function getName()
    {
        return $this->descriptor->getName();
    }

    /**
     * Gets the group descriptor's weight.
     *
     * @return int
     */
    public function getWeight()
    {
        return $this->descriptor->getWeight();
    }

    /**
     *
     * @return Label[]
     */
    public function getLabels()
    {
        return $this->labels;
    }
}