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
namespace Wallee\Payment\Block\Method;

use Magento\Backend\Model\Session\Quote as SessionQuote;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\View\Element\Template\Context;
use Wallee\Payment\Api\TokenInfoRepositoryInterface;
use Wallee\Payment\Api\Data\TokenInfoInterface;

/**
 * Block that renders the payment form in the backend.
 */
class Form extends \Magento\Payment\Block\Form
{

    /**
     *
     * @var SessionQuote
     */
    protected $_backendQuoteSession;

    /**
     *
     * @var TokenInfoRepositoryInterface
     */
    protected $_tokenInfoRepository;

    /**
     *
     * @var SearchCriteriaBuilder
     */
    protected $_searchCriteriaBuilder;

    /**
     *
     * @var FilterBuilder
     */
    protected $_filterBuilder;

    /**
     *
     * @var FilterGroupBuilder
     */
    protected $_filterGroupBuilder;

    /**
     *
     * @var string
     */
    protected $_template = 'Wallee_Payment::payment/method/form.phtml';

    /**
     *
     * @param Context $context
     * @param SessionQuote $backendQuoteSession
     * @param TokenInfoRepositoryInterface $tokenInfoRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     * @param array $data
     */
    public function __construct(Context $context, SessionQuote $backendQuoteSession,
        TokenInfoRepositoryInterface $tokenInfoRepository, SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder, FilterGroupBuilder $filterGroupBuilder, array $data = [])
    {
        parent::__construct($context, $data);
        $this->_backendQuoteSession = $backendQuoteSession;
        $this->_tokenInfoRepository = $tokenInfoRepository;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_filterBuilder = $filterBuilder;
        $this->_filterGroupBuilder = $filterGroupBuilder;

        $this->setTransportName('wallee_token');
    }

    /**
     * Gets the list of tokens that can be applied.
     *
     * @return TokenInfoInterface[]
     */
    public function getTokens()
    {
        $quote = $this->_backendQuoteSession->getQuote();
        $method = $this->getMethod();

        $customerFilter = $this->_filterBuilder->setConditionType('eq')
            ->setField(TokenInfoInterface::CUSTOMER_ID)
            ->setValue($quote->getCustomerId())
            ->create();
        $paymentMethodFilter = $this->_filterBuilder->setConditionType('eq')
            ->setField(TokenInfoInterface::PAYMENT_METHOD_ID)
            ->setValue($method->getPaymentMethodConfigurationId())
            ->create();
        $filterGroup = $this->_filterGroupBuilder->setFilters(
            [
                $customerFilter,
                $paymentMethodFilter
            ])->create();
        $searchCriteria = $this->_searchCriteriaBuilder->setFilterGroups([
            $filterGroup
        ])->create();

        return $this->_tokenInfoRepository->getList($searchCriteria)->getItems();
    }
}