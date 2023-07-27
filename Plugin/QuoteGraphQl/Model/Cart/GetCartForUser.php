<?php

namespace Wallee\Payment\Plugin\QuoteGraphQl\Model\Cart;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser as OriginalGetCartForUser;
use Psr\Log\LoggerInterface;

class GetCartForUser
{
	/**
	 * @var UserContextInterface
	 */
	private $userContext;

	/**
	 *
	 * @var CartManagementInterface
	 */
	private $cartManagement;

	/**
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	public function __construct(
		UserContextInterface $userContext,
        CartManagementInterface $cartManagement,
		LoggerInterface $logger
	) {
		$this->userContext = $userContext;
		$this->cartManagement = $cartManagement;
		$this->logger = $logger;
	}

	public function aroundExecute(OriginalGetCartForUser $subject, callable $proceed, $userId)
	{
		try {
			//call the original method using $proceed to get the result.
			$result = $proceed($userId);
		} catch (NoSuchEntityException $e) {
			//handle any exceptions occurring in the main class
			$cartId = $result = $this->cartManagement->createEmptyCartForCustomer($userId);
			$this->logger->debug("GET-CART-FOR-USER-INTERCEPTOR::aroundExecute - Cart was created: customer id:" . $userId);
			$this->logger->debug("GET-CART-FOR-USER-INTERCEPTOR::aroundExecute - Cart was created: cart id:" . $cartId);
		}

		return $result;
	}
}
