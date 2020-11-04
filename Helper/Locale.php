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
namespace Wallee\Payment\Helper;

use Magento\Backend\Model\Locale\Manager as LocaleManager;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Wallee\Payment\Model\Provider\LanguageProvider;

/**
 * Helper to provide localization related functionality.
 */
class Locale extends AbstractHelper
{

    /**
     * Default language code.
     *
     * @var string
     */
    const DEFAULT_LANGUAGE = 'en-US';

    /**
     *
     * @var LocaleManager
     */
    private $backendLocaleManager;

    /**
     *
     * @var Data
     */
    private $helper;

    /**
     *
     * @var LanguageProvider
     */
    private $languageProvider;

    /**
     *
     * @param Context $context
     * @param LocaleManager $backendLocaleManager
     * @param Data $helper
     * @param LanguageProvider $languageProvider
     */
    public function __construct(Context $context, LocaleManager $backendLocaleManager, Data $helper,
        LanguageProvider $languageProvider)
    {
        parent::__construct($context);
        $this->backendLocaleManager = $backendLocaleManager;
        $this->helper = $helper;
        $this->languageProvider = $languageProvider;
    }

    /**
     * Gets the translation in the given language.
     *
     * @param mixed $translatedString
     * @param string $language
     * @return string|NULL
     */
    public function translate($translatedString, $language = null)
    {
        if (! \is_array($translatedString)) {
            return $translatedString;
        }

        if ($language == null) {
            if ($this->helper->isAdminArea()) {
                $language = $this->backendLocaleManager->getUserInterfaceLocale();
            } else {
                $language = $this->scopeConfig->getValue('general/locale/code',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            }
        }

        $language = \str_replace('_', '-', $language);
        if (isset($translatedString[$language])) {
            return $translatedString[$language];
        }

        try {
            $primaryLanguage = $this->languageProvider->findPrimary($language);
            if ($primaryLanguage !== false && isset($translatedString[$primaryLanguage->getIetfCode()])) {
                return $translatedString[$primaryLanguage->getIetfCode()];
            }
        } catch (\Exception $e) {}

        if (isset($translatedString[self::DEFAULT_LANGUAGE])) {
            return $translatedString[self::DEFAULT_LANGUAGE];
        }

        return null;
    }
}