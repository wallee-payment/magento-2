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
namespace Wallee\Payment\Block\Adminhtml\Customer\Tab\Renderer;

use Magento\Backend\Block\Widget\Grid\Column\Renderer\AbstractRenderer;
use Magento\Framework\DataObject;
use Wallee\PluginCore\Token\State as CoreTokenState;

/**
 * Block to render the state grid column of the token grid.
 */
class State extends AbstractRenderer
{
    /**
     * Render human-readable state label for the given row.
     *
     * @param \Magento\Framework\DataObject $row
     * @return \Magento\Framework\Phrase
     */
    public function render(DataObject $row)
    {
        switch ($row->getData($this->getColumn()
            ->getIndex())) {
            case CoreTokenState::ACTIVE->value:
                return \__('Active');
            case CoreTokenState::INACTIVE->value:
                return \__('Inactive');
            default:
                return \__('Unknown State');
        }
    }
}
