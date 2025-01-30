<?php
declare(strict_types=1);

namespace Puravita\WalleePayment\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;

interface GeneralConfigInterface
{
    public const XPATH_TOGGLE_WALLEE_CONFIG_AT_CERTAIN_PAGES =
        'puravita_wallee_payment/general/toggle_wallee_config_at_certain_pages';

    /**
     * Return whether is toggle Wallee Config at a checkout cart page
     *
     * @param string $scopeType
     * @param string|null $scopeCode
     * @return bool
     */
    public function isToggleWalleeConfigAtCertainPages(
        string $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        ?string $scopeCode = null
    ): bool;
}
