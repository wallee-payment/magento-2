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
namespace Wallee\Payment\Block\Method;

use Magento\Backend\Model\Session\Quote as SessionQuote;
use Magento\Framework\Api\SearchCriteriaBuilder;
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
    private $backendQuoteSession;

    /**
     *
     * @var TokenInfoRepositoryInterface
     */
    private $tokenInfoRepository;

    /**
     *
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

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
     * @param array $data
     */
    public function __construct(Context $context, SessionQuote $backendQuoteSession,
        TokenInfoRepositoryInterface $tokenInfoRepository, SearchCriteriaBuilder $searchCriteriaBuilder,
        array $data = [])
    {
        parent::__construct($context, $data);
        $this->backendQuoteSession = $backendQuoteSession;
        $this->tokenInfoRepository = $tokenInfoRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;

        $this->setTransportName('wallee_token');
    }

    /**
     * Gets the list of tokens that can be applied.
     *
     * @return TokenInfoInterface[]
     */
    public function getTokens()
    {
        $quote = $this->backendQuoteSession->getQuote();
        $method = $this->getMethod();

        $searchCriteria = $this->searchCriteriaBuilder->addFilter(TokenInfoInterface::CUSTOMER_ID,
            $quote->getCustomerId())
            ->addFilter(TokenInfoInterface::PAYMENT_METHOD_ID, $method->getPaymentMethodConfigurationId())
            ->create();

        return $this->tokenInfoRepository->getList($searchCriteria)->getItems();
    }
}