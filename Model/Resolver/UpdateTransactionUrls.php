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
namespace Wallee\Payment\Model\Resolver;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\CustomerGraphQl\Model\Customer\GetCustomer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Customer\Model\Session;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Psr\Log\LoggerInterface;
use Wallee\Payment\Model\Service\Quote\TransactionService;
use Wallee\Payment\Api\TransactionInfoManagementInterface;

class UpdateTransactionUrls implements ResolverInterface
{
    /**
     *
     * @var Session
     */
    private $customerSession;

    /**
     *
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     *
     * @var GetCustomer
     */
    private $getCustomer;

    /**
     *
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     *
     * @var MaskedQuoteIdToQuoteIdInterface
     */
    private $maskedQuoteIdToQuoteIdService;

    /**
     *
     * @var TransactionService
     */
    private $transactionQuoteService;

    /**
     *
     * @var TransactionInfoManagementInterface
     */
    private $transactionInfoManagement;

    /**
     *
     * @var LoggerInterface
     */
    private $logger;


    public function __construct(Session $customerSession, CheckoutSession $checkoutSession, GetCustomer $getCustomer,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteIdService, CartRepositoryInterface $cartRepository,
        TransactionService $transactionQuoteService, TransactionInfoManagementInterface $transactionInfoManagement, LoggerInterface $logger)
        {
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->getCustomer = $getCustomer;
        $this->cartRepository = $cartRepository;
        $this->maskedQuoteIdToQuoteIdService = $maskedQuoteIdToQuoteIdService;
        $this->logger = $logger;
        $this->transactionQuoteService = $transactionQuoteService;
        $this->transactionInfoManagement = $transactionInfoManagement;
    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        //only perform validations if the user is anonymous.
        if ($this->checkoutSession->getQuote()->getCustomerId()) {
            /** @var ContextInterface $context */
            if (false === $context->getExtensionAttributes()->getIsCustomer()) {
                throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
            }

            $customer = $this->getCustomer->execute($context);
            if ($this->customerSession === null && $customer->getId() !== $this->customerSession->getCustomer()->getId()) {
                throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
            }
        }

        try {
            $successUrl = $args['input']['success_url'];
            $failureUrl = $args['input']['failure_url'];
            $cartIdMasked = $args['input']['cart_id'];
            return $this->setTransactionUrls($cartIdMasked, $successUrl, $failureUrl);
        } catch (NoSuchEntityException $e) {
            $this->logger->critical($e);
            throw new GraphQlNoSuchEntityException(__($e->getMessage()));
        }
    }

    /**
     * Update transaction urls to redirect the customer after placing the order
     *
     * @param string $cartIdMasked
     * @param string $successUrl
     * @param string $failureUrl
     * @return array<mixed>
     * @throws LocalizedException
     */
    private function setTransactionUrls($cartIdMasked, $successUrl, $failureUrl)
    {
        try {
            // Convert the masked ID to the real quote ID
            $quoteId = $this->maskedQuoteIdToQuoteIdService->execute($cartIdMasked);

            // Get the quote using the actual ID
            /** @var Quote $quote */
            $quote = $this->cartRepository->get($quoteId);

            //$quoteSession = $this->checkoutSession->getQuote();
            /** @var \Wallee\Payment\Model\ResourceModel\TransactionInfo $transactionInfo */
            $transactionInfo = $this->transactionQuoteService->getTransaction(
                $quote->getWalleeSpaceId(),
                $quote->getWalleeTransactionId()
            );

            // Gets the ID reserved for the order from the quotation
            $orderId = $quote->getReservedOrderId();
            
            // Checks if the quote does not have an ID reserved for the order
            if (!$orderId && !$quote->hasReservedOrderId()) {
                $orderId = $quote->getId();
            }

            $this->transactionInfoManagement->setRedirectUrls($transactionInfo, $orderId, $successUrl, $failureUrl);
            return ['result' => 'OK'];
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return ['result' => 'KO. ' . $e->getMessage()];
        }
    }
}
