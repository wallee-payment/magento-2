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
namespace Wallee\Payment\Controller;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Abstract controller action to handle transaction related requests.
 */
abstract class Transaction extends \Magento\Framework\App\Action\Action
{

    /**
     *
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     *
     * @param Context $context
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(Context $context, OrderRepositoryInterface $orderRepository)
    {
        parent::__construct($context);
        $this->orderRepository = $orderRepository;
    }

    /**
     * Gets the order from the request.
     *
     * @throws \Exception
     * @return \Magento\Sales\Api\Data\OrderInterface
     */
    protected function getOrder()
    {
        $orderId = $this->getRequest()->getParam('order_id');
        if (empty($orderId)) {
            throw new LocalizedException(\__('The order ID has been provided.'));
        }
        $order = $this->orderRepository->get($orderId);

        $token = $order->getWalleeSecurityToken();
        if (empty($token) || $token != $this->getRequest()->getParam('token')) {
            throw new LocalizedException(\__('The wallee security token is invalid.'));
        }

        return $order;
    }
}