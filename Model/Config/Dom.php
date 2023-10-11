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
namespace Wallee\Payment\Model\Config;

/**
 * Class to parse and merge configuration XML files.
 */
class Dom extends \Magento\Framework\Config\Dom
{

    const SYSTEM_INITIAL_CONTENT = '<?xml version="1.0"?><config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Config:etc/system_file.xsd"><system></system></config>';

    const CONFIG_INITIAL_CONTENT = '<?xml version="1.0"?><config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Store:etc/config.xsd"></config>';

    /**
     * Sets the DOM document.
     *
     * @param \DOMDocument $dom
     * @return void
     */
    public function setDom(\DOMDocument $dom)
    {
        $this->dom = $dom;
    }
}