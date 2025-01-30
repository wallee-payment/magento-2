<?php
declare(strict_types=1);

namespace Puravita\WalleePayment\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;

class GeneralConfig implements GeneralConfigInterface
{
    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @inheritDoc
     */
    public function isToggleWalleeConfigAtCertainPages(
        string $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        ?string $scopeCode = null
    ): bool {
        return $this->scopeConfig->isSetFlag(
            self::XPATH_TOGGLE_WALLEE_CONFIG_AT_CERTAIN_PAGES,
            $scopeType,
            $scopeCode
        );
    }
}
