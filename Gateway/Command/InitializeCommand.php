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
namespace Wallee\Payment\Gateway\Command;

use Magento\Framework\Math\Random;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Wallee\Payment\Api\TokenInfoRepositoryInterface;
use Wallee\Payment\Helper\Data as Helper;
use Wallee\Payment\Model\Service\Order\TransactionService;
use Wallee\Sdk\Model\Token;

/**
 * Payment gateway command to initialize a payment.
 */
class InitializeCommand implements CommandInterface
{

    /**
     *
     * @var CartRepositoryInterface
     */
    protected $_quoteRepository;

    /**
     *
     * @var Random
     */
    protected $_random;

    /**
     *
     * @var Helper
     */
    protected $_helper;

    /**
     *
     * @var TransactionService
     */
    protected $_transactionService;

    /**
     *
     * @var TokenInfoRepositoryInterface
     */
    protected $_tokenInfoRepository;

    /**
     *
     * @param CartRepositoryInterface $quoteRepository
     * @param Random $random
     * @param Helper $helper
     * @param TransactionService $transactionService
     * @param TokenInfoRepositoryInterface $tokenInfoRepository
     */
    public function __construct(CartRepositoryInterface $quoteRepository, Random $random, Helper $helper,
        TransactionService $transactionService, TokenInfoRepositoryInterface $tokenInfoRepository)
    {
        $this->_quoteRepository = $quoteRepository;
        $this->_random = $random;
        $this->_helper = $helper;
        $this->_transactionService = $transactionService;
        $this->_tokenInfoRepository = $tokenInfoRepository;
    }

    /**
     * An invoice is created and the transaction updated to match the order and confirmed.
     * The order state is set to {@link Order::STATE_PENDING_PAYMENT}.
     *
     * @see CommandInterface::execute()
     */
    public function execute(array $commandSubject)
    {
        $stateObject = SubjectReader::readStateObject($commandSubject);

        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = SubjectReader::readPayment($commandSubject)->getPayment();

        /** @var Order $order */
        $order = $payment->getOrder();

        $order->setCanSendNewEmailFlag(false);
        $payment->setAmountAuthorized($order->getTotalDue());
        $payment->setBaseAmountAuthorized($order->getBaseTotalDue());

        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->_quoteRepository->get($order->getQuoteId());

        $order->setWalleeSpaceId($quote->getWalleeSpaceId());
        $order->setWalleeTransactionId($quote->getWalleeTransactionId());
        $order->setWalleeSecurityToken($this->_random->getUniqueHash());

        $stateObject->setState(Order::STATE_PENDING_PAYMENT);
        $stateObject->setStatus('pending_payment');
        $stateObject->setIsNotified(false);

        if ($this->_helper->isAdminArea()) {
            // Tell the order to apply the charge flow after it is saved.
            $order->setWalleeChargeFlow(true);
            $order->setWalleeToken($this->getToken($quote));
        }
    }

    protected function getToken(Quote $quote)
    {
        if ($this->_helper->isAdminArea()) {
            $tokenInfoId = $quote->getPayment()->getData('wallee_token');
            if ($tokenInfoId) {
                $tokenInfo = $this->_tokenInfoRepository->get($tokenInfoId);
                $token = new Token();
                $token->setId($tokenInfo->getTokenId());
                return $token;
            }
        }
    }
}