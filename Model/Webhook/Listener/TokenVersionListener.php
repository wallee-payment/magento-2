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
namespace Wallee\Payment\Model\Webhook\Listener;

use Wallee\Payment\Api\TokenInfoManagementInterface;
use Wallee\Payment\Model\Webhook\ListenerInterface;
use Wallee\Payment\Model\Webhook\Request;

/**
 * Webhook listener to handle token versions.
 */
class TokenVersionListener implements ListenerInterface
{

    /**
     *
     * @var TokenInfoManagementInterface
     */
    private $tokenInfoManagement;

    /**
     *
     * @param TokenInfoManagementInterface $tokenInfoManagement
     */
    public function __construct(TokenInfoManagementInterface $tokenInfoManagement)
    {
        $this->tokenInfoManagement = $tokenInfoManagement;
    }

    public function execute(Request $request)
    {
        $this->tokenInfoManagement->updateTokenVersion($request->getSpaceId(), $request->getEntityId());
    }
}