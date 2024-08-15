<?php
/**
 * wallee Magento 2
 *
 * This Magento 2 extension enables to process payments with wallee (https://www.wallee.com).
 *
 * @package Wallee_Payment
 * @author wallee AG (https://www.wallee.com)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
.
 */

namespace Wallee\Payment\Observer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Wallee\Payment\Model\Service\Quote\TransactionService;

/**
 * Observer to update transaction info for declined orders.
 */
class UpdateDeclinedOrderTransaction implements ObserverInterface
{
    /**
     * @var TransactionService
     */
    private $transactionService;

    /**
     * @var TransactionInfoRepositoryInterface
     */
    private $transactionInfoRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    public function __construct(CartRepositoryInterface $quoteRepository, TransactionService $transactionService,
        TransactionInfoRepositoryInterface $transactionInfoRepository, LoggerInterface $logger, CheckoutSession $checkoutSession)
    {
        $this->quoteRepository = $quoteRepository;
        $this->transactionService = $transactionService;
        $this->transactionInfoRepository = $transactionInfoRepository;
        $this->logger = $logger;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Executes the observer to handle updates for declined order transactions.
     *
     * This method is triggered when the associated event occurs. It checks if the current transaction
     * associated with the provided quote is still available (i.e., not in a failed or declined state).
     * If the transaction is not available, it clears the transaction ID in the quote and saves the
     * updated quote to the repository.
     *
     * @param Observer $observer the observer instance containing event data
     *
     * @return void
     *
     * @throws Exception if the transaction cannot be found
     */
    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getOrder();
        /** @var Quote $quote */
        $quote = $observer->getQuote();

        try {
            $this->logger->debug("UPDATE-DECLINED-ORDER-TRANSACTION-SERVICE::execute - Update quote's transaction id");
            // clear the payment url with the old transaction
            $this->checkoutSession->unsPaymentUrl();
            // check if the current transaction is not in failed or declined state
            if (!$this->transactionService->checkTransactionIsStillAvailable($quote)) {
                $quote->setWalleeTransactionId(null);
                $this->quoteRepository->save($quote);
            }
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }
    }
}
