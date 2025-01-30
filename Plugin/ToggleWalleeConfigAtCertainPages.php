<?php
declare(strict_types=1);

namespace Puravita\WalleePayment\Plugin;

use Closure;
use Magento\Framework\App\Request\Http as HttpRequest;
use Puravita\WalleePayment\Model\Config\GeneralConfigInterface;
use Wallee\Payment\Model\Checkout\ConfigProvider;

class ToggleWalleeConfigAtCertainPages
{
    /**
     * @param GeneralConfigInterface $generalConfig
     * @param HttpRequest $request
     * @param array $certainPages
     */
    public function __construct(
        private readonly GeneralConfigInterface $generalConfig,
        private readonly HttpRequest $request,
        private readonly array $certainPages
    ) {
    }

    /**
     * Toggle Wallee-Payment configuration at certain pages.
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
        if ($this->generalConfig->isToggleWalleeConfigAtCertainPages() && $this->isCertainPage()) {
            return [];
        } else {
            return $proceed();
        }
    }

    /**
     * Return whether a current page is a certain page
     *
     * @return bool
     */
    private function isCertainPage(): bool
    {
        return in_array($this->request->getFullActionName(), $this->certainPages);
    }
}
