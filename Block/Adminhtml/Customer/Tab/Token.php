<?php
namespace Wallee\Payment\Block\Adminhtml\Customer\Tab;

use Magento\Backend\Block\Template\Context;
use Magento\Customer\Controller\RegistryConstants;
use Magento\Framework\Registry;
use Magento\Ui\Component\Layout\Tabs\TabInterface;
use Magento\Ui\Component\Layout\Tabs\TabWrapper;

/**
 * Block to render the wallee token tab in the backend customer view.
 */
class Token extends TabWrapper implements TabInterface
{

    /**
     *
     * @var Registry
     */
    private $registry = null;

    /**
     *
     * @param Context $context
     * @param Registry $registry
     * @param array $data
     */
    public function __construct(Context $context, Registry $registry, array $data = [])
    {
        parent::__construct($context, $data);
        $this->registry = $registry;
    }

    /**
     * Check whether the tab can be displayed.
     *
     * @return bool
     */
    public function canShowTab()
    {
        return $this->registry->registry(RegistryConstants::CURRENT_CUSTOMER_ID);
    }

    /**
     * Determine whether the tab content is loaded via AJAX.
     *
     * @return bool
     */
    public function isAjaxLoaded()
    {
        $flag = $this->getData('is_ajax_loaded');
        return $flag !== null ? (bool) $flag : true;
    }

    /**
     * Get tab label.
     *
     * @return \Magento\Framework\Phrase
     */
    public function getTabLabel()
    {
        return \__('wallee Payment Tokens');
    }

    /**
     * Get tab URL.
     *
     * @return string
     */
    public function getTabUrl()
    {
        return $this->getUrl('wallee_payment/customer/token', [
            '_current' => true
        ]);
    }
}
