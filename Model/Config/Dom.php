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
namespace Wallee\Payment\Model\Config;

use Magento\Framework\Config\ValidationStateInterface;

/**
 * Class to parse and merge configuration XML files.
 */
class Dom extends \Magento\Framework\Config\Dom
{

    const SYSTEM_INITIAL_CONTENT = '<?xml version="1.0"?><config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Config:etc/system_file.xsd"><system></system></config>';

    const CONFIG_INITIAL_CONTENT = '<?xml version="1.0"?><config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Store:etc/config.xsd"></config>';

    /**
     * Build DOM with initial XML contents and specifying identifier attributes for merging
     *
     * @param string $xml
     * @param ValidationStateInterface $validationState
     * @param array $idAttributes
     * @param string $typeAttributeName
     * @param string $schemaFile
     * @param string $errorFormat
     */
    public function __construct($xml, ValidationStateInterface $validationState, array $idAttributes = [],
        $typeAttributeName = null, $schemaFile = null, $errorFormat = self::ERROR_FORMAT_DEFAULT)
    {
        parent::__construct($xml, $validationState, $idAttributes, $typeAttributeName, $schemaFile, $errorFormat);
    }

    /**
     * Sets the DOM document.
     *
     * @param \DOMDocument $dom
     */
    public function setDom(\DOMDocument $dom)
    {
        $this->dom = $dom;
    }
}