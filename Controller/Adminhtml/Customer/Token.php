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
namespace Wallee\Payment\Controller\Adminhtml\Customer;

use Wallee\Payment\Api\TokenInfoManagementInterface;
use Wallee\Payment\Api\TokenInfoRepositoryInterface;

/**
 * Backend controller action to delete wallee tokens.
 */
class Token extends \Magento\Customer\Controller\Adminhtml\Index
{

    /**
     *
     * @return \Magento\Framework\View\Result\Layout
     */
    public function execute()
    {
        $customerId = $this->initCurrentCustomer();
        $tokenId = (int) $this->getRequest()->getParam('delete');
        if ($customerId && $tokenId) {
            try {
                /** @var \Wallee\Payment\Model\TokenInfo $token */
                $token = $this->_objectManager->get(TokenInfoRepositoryInterface::class)->get($tokenId);
                $this->_objectManager->get(TokenInfoManagementInterface::class)->deleteToken($token);
            } catch (\Exception $exception) {
                $this->_objectManager->get(\Psr\Log\LoggerInterface::class)->critical($exception);
            }
        }

        $resultLayout = $this->resultLayoutFactory->create();
        return $resultLayout;
    }
}