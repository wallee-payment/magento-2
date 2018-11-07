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
     * @var bool
     */
    protected $isAjaxLoaded = true;

    /**
     *
     * @var Registry
     */
    protected $_registry = null;

    /**
     *
     * @param Context $context
     * @param Registry $registry
     * @param array $data
     */
    public function __construct(Context $context, Registry $registry, array $data = [])
    {
        parent::__construct($context, $data);
        $this->_registry = $registry;
    }

    public function canShowTab()
    {
        return $this->_registry->registry(RegistryConstants::CURRENT_CUSTOMER_ID);
    }

    /**
     * Return Tab label
     *
     * @return \Magento\Framework\Phrase
     */
    public function getTabLabel()
    {
        return \__('wallee Payment Tokens');
    }

    /**
     * Return URL link to Tab content
     *
     * @return string
     */
    public function getTabUrl()
    {
        return $this->getUrl('wallee_payment/customer/token',
            [
                '_current' => true
            ]);
    }
}