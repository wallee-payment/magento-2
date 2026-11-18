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
namespace Wallee\Payment\Compat;

use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\Quote;

/**
 * Stub base used when Hyvä Checkout module is not present.
 * Wallee\Payment\Compat\PlaceOrderServiceBase is aliased
 * to this class so that PlaceOrderService can be declared and reflected
 * during DI compilation without a fatal errors when AbstractPlaceOrderService
 * from Hyvä Checkout is not isntalled.
 */
class PlaceOrderServiceFallback
{
    /**
     * @var CartManagementInterface
     */
    protected $cartManagement;

    /**
     * @var mixed
     */
    protected $orderData;

    /**
     * Initialize the fallback service.
     *
     * @param CartManagementInterface $cartManagement
     * @param mixed $orderData
     */
    public function __construct(CartManagementInterface $cartManagement, $orderData = null)
    {
        $this->cartManagement = $cartManagement;
        $this->orderData = $orderData;
    }

    /**
     * Reject redirects when Hyvä Checkout is unavailable.
     *
     * @param Quote $quote
     * @param int|null $orderId
     * @return string
     * @throws \LogicException
     */
    public function getRedirectUrl(Quote $quote, ?int $orderId = null): string
    {
        throw new \LogicException('Hyvä Checkout is required to resolve the order redirect URL.');
    }
}
