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

use Magento\Framework\App\Area as AppArea;
use Magento\Framework\App\State as AppState;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Wallee\Payment\Model\Provider\CurrencyProvider;
use Wallee\Sdk\Model\CriteriaOperator;
use Wallee\Sdk\Model\EntityQueryFilter;
use Wallee\Sdk\Model\EntityQueryFilterType;
use Wallee\Sdk\Model\EntityQueryOrderBy;
use Wallee\Sdk\Model\EntityQueryOrderByType;

/**
 * Basic helper.
 */
class Data extends AbstractHelper
{

    /**
     *
     * @var AppState
     */
    private $appState;

    /**
     *
     * @var CurrencyProvider
     */
    private $currencyProvider;

    /**
     *
     * @param Context $context
     * @param AppState $appState
     * @param CurrencyProvider $currencyProvider
     */
    public function __construct(Context $context, AppState $appState, CurrencyProvider $currencyProvider)
    {
        parent::__construct($context);
        $this->appState = $appState;
        $this->currencyProvider = $currencyProvider;
    }

    /**
     * Gets whether the user is in admin area.
     *
     * @return boolean
     */
    public function isAdminArea()
    {
        return $this->appState->getAreaCode() == AppArea::AREA_ADMINHTML;
    }

    /**
     * Gets the fraction digits of the given currency.
     *
     * @param string $currencyCode
     * @return float
     */
    public function getCurrencyFractionDigits($currencyCode)
    {
        $currency = $this->currencyProvider->find($currencyCode);
        if ($currency) {
            return $currency->getFractionDigits();
        } else {
            return 2;
        }
    }

    /**
     * Rounds the given amount to the currency's format.
     *
     * @param float $amount
     * @param string $currencyCode
     * @return float
     */
    public function roundAmount($amount, $currencyCode)
    {
        return \round($amount, $this->getCurrencyFractionDigits($currencyCode));
    }

    /**
     * Compares the given amounts.
     *
     * @param float $amount1
     * @param float $amount2
     * @param string $currencyCode
     * @return float
     */
    public function compareAmounts($amount1, $amount2, $currencyCode)
    {
        return $this->roundAmount($amount1, $currencyCode) - $this->roundAmount($amount2, $currencyCode);
    }

    /**
     * Creates and returns a new entity filter.
     *
     * @param string $fieldName
     * @param mixed $value
     * @param string $operator
     * @return EntityQueryFilter
     */
    public function createEntityFilter($fieldName, $value, $operator = CriteriaOperator::EQUALS)
    {
        $filter = new EntityQueryFilter();
        $filter->setType(EntityQueryFilterType::LEAF);
        $filter->setOperator($operator);
        $filter->setFieldName($fieldName);
        $filter->setValue($value);
        return $filter;
    }

    /**
     * Creates and returns a new entity order by.
     *
     * @param string $fieldName
     * @param string $sortOrder
     * @return EntityQueryOrderBy
     */
    public function createEntityOrderBy($fieldName, $sortOrder = EntityQueryOrderByType::DESC)
    {
        $orderBy = new EntityQueryOrderBy();
        $orderBy->setFieldName($fieldName);
        $orderBy->setSorting($sortOrder);
        return $orderBy;
    }

    /**
     * Changes the given string to have no more characters as specified.
     *
     * @param string $string
     * @param int $maxLength
     * @return string
     */
    public function fixLength($string, $maxLength)
    {
        return \mb_substr($string, 0, $maxLength, 'UTF-8');
    }

    /**
     * Removes all line breaks in the given string and replaces them with a whitespace character.
     *
     * @param string $string
     * @return string
     */
    public function removeLinebreaks($string)
    {
        if($string == NULL) //PHP8.1 does not support NULL on preg_replace
        {
            $string='';
        }
        return \preg_replace("/\r|\n/", ' ', $string);
    }

    /**
     * Gets the first line of the given string only.
     *
     * @param string $string
     * @return string
     */
    public function getFirstLine($string)
    {
        if (\is_array($string)) {
            $string = \implode(', ', $string);
        }
        return \rtrim(\strtok((string) $string, "\n"));
    }

    /**
     * Gets the URL to a resource on wallee in the given context (space, space view, language).
     *
     * @param string $path
     * @param string $language
     * @param int $spaceId
     * @param int $spaceViewId
     * @return string
     */
    public function getResourceUrl($path, $language = null, $spaceId = null, $spaceViewId = null)
    {
        $url = \rtrim($this->scopeConfig->getValue('wallee_payment/general/base_gateway_url'), '/');
        if (! empty($language)) {
            $url .= '/' . \str_replace('_', '-', $language);
        }
        if (! empty($spaceId)) {
            $url .= '/s/' . $spaceId;
        }
        if (! empty($spaceViewId)) {
            $url .= '/' . $spaceViewId;
        }
        $url .= '/resource/' . $path;
        return $url;
    }

    /**
     * Generates and returns a unique ID.
     *
     * @return string the unique ID
     */
    public function generateUUID() {
        $data = \openssl_random_pseudo_bytes(16);
        $data[6] = \chr(\ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = \chr(\ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        return \vsprintf('%s%s-%s-%s-%s-%s%s%s', \str_split(\bin2hex($data), 4));
    }
}