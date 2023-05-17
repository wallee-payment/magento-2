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


	public function __construct(
		Session                            $customerSession,
		CheckoutSession                    $checkoutSession,
		GetCustomer                        $getCustomer,
		TransactionService                 $transactionQuoteService,
		TransactionInfoManagementInterface $transactionInfoManagement,
		LoggerInterface                    $logger
	) {
		$this->customerSession = $customerSession;
		$this->checkoutSession = $checkoutSession;
		$this->getCustomer = $getCustomer;
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
			if (!empty($this->customerSession) && $customer->getId() !== $this->customerSession->getCustomer()->getId()) {
				throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
			}
		}

		try {
			$successUrl = $args['input']['success_url'];
			$failureUrl = $args['input']['failure_url'];
			$cartId = $args['input']['cart_id'];
			return $this->setTransactionUrls($cartId, $successUrl, $failureUrl);
		} catch (NoSuchEntityException $e) {
			$this->logger->critical($e);
			throw new GraphQlNoSuchEntityException(__($e->getMessage()));
		}
	}

	/**
	 * Update transaction urls to redirect the customer after placing the order
	 *
	 * @param $cartId
	 * @param $successUrl
	 * @param $failureUrl
	 * @return array
	 * @throws LocalizedException
	 */
	private function setTransactionUrls($cartId, $successUrl, $failureUrl)
	{
		try {
			$quote = $this->checkoutSession->getQuote();
			/** @var \Wallee\Payment\Model\ResourceModel\TransactionInfo $transactionInfo */
			$transactionInfo = $this->transactionQuoteService->getTransactionByQuote($quote);

			$this->transactionInfoManagement->setRedirectUrls($transactionInfo, $successUrl, $failureUrl);
			return ['result' => 'OK'];
		} catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
			return ['result' => 'KO. '.$e->getMessage()];
		}
	}
}
