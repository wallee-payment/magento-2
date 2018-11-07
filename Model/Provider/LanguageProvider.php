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
namespace Wallee\Payment\Model\Provider;

use Magento\Framework\Cache\FrontendInterface;
use Wallee\Payment\Model\ApiClient;
use Wallee\Sdk\Service\LanguageService;

/**
 * Provider of language information from the gateway.
 */
class LanguageProvider extends AbstractProvider
{

    /**
     *
     * @param FrontendInterface $cache
     * @param ApiClient $apiClient
     */
    public function __construct(FrontendInterface $cache, ApiClient $apiClient)
    {
        parent::__construct($cache, $apiClient, 'wallee_payment_languages');
    }

    /**
     * Gets the language by the given code.
     *
     * @param string $code
     * @return \Wallee\Sdk\Model\RestLanguage
     */
    public function find($code)
    {
        return parent::find($code);
    }

    /**
     * Gets the primary language in the given group.
     *
     * @param string $code
     * @return \Wallee\Sdk\Model\RestLanguage
     */
    public function findPrimary($code)
    {
        $code = \substr($code, 0, 2);
        foreach ($this->getAll() as $language) {
            if ($language->getIso2Code() == $code && $language->getPrimaryOfGroup()) {
                return $language;
            }
        }

        return false;
    }

    /**
     * Gets a list of languages.
     *
     * @return \Wallee\Sdk\Model\RestLanguage[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        return $this->_apiClient->getService(LanguageService::class)->all();
    }

    protected function getId($entry)
    {
        /** @var \Wallee\Sdk\Model\RestLanguage $entry */
        return $entry->getIetfCode();
    }
}