<?php
declare(strict_types=1);

namespace Puravita\WalleePayment\Plugin;

use Closure;
use Magento\Framework\App\Request\Http as HttpRequest;
use Puravita\WalleePayment\Model\Config\GeneralConfigInterface;
use Wallee\Payment\Model\Checkout\ConfigProvider;

class DisableWalleeConfigAtCheckoutCartPage
{
    private const CHECKOUT_CART_INDEX = 'checkout_cart_index';

    /**
     * @param GeneralConfigInterface $generalConfig
     * @param HttpRequest $request
     */
    public function __construct(
        private readonly GeneralConfigInterface $generalConfig,
        private readonly HttpRequest $request
    ) {
    }

    /**
     * Toggle Wallee-Payment configuration at the Checkout Cart Page.
     *
     * By default, the extension generates unnecessary network traffic and increases the page loading time.
     *
     * @param ConfigProvider $subject
     * @param Closure $proceed
     * @return array[] either a full Wallee checkout config or a mockup structure
     * @see ConfigProvider::getConfig()
     */
    public function aroundGetConfig(ConfigProvider $subject, Closure $proceed): array
    {
        if ($this->generalConfig->isToggleWalleeConfigAtCheckoutCartPage() && $this->isCheckoutCartPage()) {
            return [
                'payment' => [],
                'wallee' => []
            ];
        } else {
            return $proceed();
        }
    }

    /**
     * Return whether a current page is a checkout cart page
     *
     * @return bool
     */
    private function isCheckoutCartPage(): bool
    {
        return $this->request->getFullActionName() == self::CHECKOUT_CART_INDEX;
    }
}
