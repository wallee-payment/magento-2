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

declare(strict_types=1);

namespace Wallee\Payment\Model\Hyva\Checkout\Payment;

use Hyva\Checkout\Model\Magewire\Payment\AbstractPlaceOrderService;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;
use Wallee\Payment\Model\Config\Source\IntegrationMethod;
use Wallee\Payment\Compat\PlaceOrderServiceBase;

/**
 * Service class for handling order placement in Hyva Checkout.
 *
 * This class extends PlaceOrderServiceBase, which is aliased during registration.php
 * to AbstractPlaceOrderService when Hyvä Checkout is installed, and to an empty stub
 * (PlaceOrderServiceFallback) otherwise.
 *
 * The AbstractPlaceOrderService is provided by Hyva Checkout to integrate
 * the Wallee payment logic into the Magewire-based checkout flow.
 */
class PlaceOrderService extends PlaceOrderServiceBase
{
    /**
     * The scope config is used to retrieve plugin settings like the integration method.
     *
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * Initialize the service with required dependencies.
     *
     * @param CartManagementInterface $cartManagement The core cart management service.
     * @param ScopeConfigInterface $scopeConfig The configuration service for settings.
     * @param mixed $orderData Optional additional order data.
     */
    public function __construct(
        CartManagementInterface $cartManagement,
        ScopeConfigInterface $scopeConfig,
        mixed $orderData = null,
    ) {
        parent::__construct($cartManagement, $orderData);

        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Check if this service can handle the given payment method code.
     * We verify if the code starts with our vendor prefix to ensure we only
     * intercept our own payment methods.
     *
     * @param string $code The payment method code to verify.
     * @return bool True if handles, false otherwise.
     */
    public function canHandle(string $code): bool
    {
        return \str_starts_with($code, 'wallee_payment_');
    }

    /**
     * Determine if a redirect should be issued by Magewire after order placement.
     *
     * For 'Payment Page' integration, we want the browser to follow the redirect
     * to the external portal. For other methods like Iframe or Lightbox, we stay
     * on the checkout page to let the Wallee SDK take over.
     *
     * @return bool True if redirect is needed, false otherwise.
     */
    public function canRedirect(): bool
    {
        $integrationMethod = $this->scopeConfig->getValue(
            'wallee_payment/checkout/integration_method',
            ScopeInterface::SCOPE_STORE,
        );

        if ($integrationMethod === IntegrationMethod::PAYMENT_PAGE) {
            return true;
        }

        return false;
    }

    /**
     * Get the URL to redirect the user to after the order is placed.
     *
     * In Hyva, the Quote object state might not reflect recent changes from observers
     * during the placeOrder call. Thus, we manually calculate the portal URL for
     * the 'Payment Page' method to ensure accuracy.
     *
     * @param Quote $quote The active quote.
     * @param int|null $orderId The created order ID.
     * @return string The destination redirect URL.
     */
    public function getRedirectUrl(Quote $quote, ?int $orderId = null): string
    {
        $integrationMethod = $this->scopeConfig->getValue(
            'wallee_payment/checkout/integration_method',
            ScopeInterface::SCOPE_STORE,
            $quote->getStoreId(),
        );

        if ($integrationMethod === IntegrationMethod::PAYMENT_PAGE) {
            return $quote->getStore()->getUrl(
                'wallee_payment/checkout/paymentPageUrl',
                [
                    '_secure' => true,
                ],
            );
        }

        return parent::getRedirectUrl($quote, $orderId);
    }
}
