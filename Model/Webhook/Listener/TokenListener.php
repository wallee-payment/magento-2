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
namespace Wallee\Payment\Model\Webhook\Listener;

use Wallee\Payment\Api\TokenInfoManagementInterface;
use Wallee\Payment\Model\Webhook\ListenerInterface;
use Wallee\Payment\Model\Webhook\Request;

/**
 * Webhook listener to handle tokens.
 */
class TokenListener implements ListenerInterface
{

    /**
     *
     * @var TokenInfoManagementInterface
     */
    protected $_tokenInfoManagement;

    /**
     *
     * @param TokenInfoManagementInterface $tokenInfoManagement
     */
    public function __construct(TokenInfoManagementInterface $tokenInfoManagement)
    {
        $this->_tokenInfoManagement = $tokenInfoManagement;
    }

    public function execute(Request $request)
    {
        $this->_tokenInfoManagement->updateToken($request->getSpaceId(), $request->getEntityId());
    }
}