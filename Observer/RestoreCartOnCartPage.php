<?php
/**
 * wallee Magento 2
 *
 * This Magento 2 extension enables to process payments with wallee (https://www.wallee.com).
 *
 * @package Wallee_Payment
 * @author wallee AG (https://www.wallee.com)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)

 */
namespace Wallee\Payment\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Psr\Log\LoggerInterface;

/**
 * On the cart page, if the customer is returning from an abandoned
 * wallee payment (back-button from 3DS, etc.), reactivate the quote
 * inline so the cart renders with items immediately — no client-side
 * redirect, no flash.
 */
class RestoreCartOnCartPage implements ObserverInterface
{
    public const COOKIE_NAME = 'wallee_restore_pending';

    /**
     * @var CookieManagerInterface
     */
    private $cookieManager;

    /**
     * @var CookieMetadataFactory
     */
    private $cookieMetadataFactory;

    /**
     * @var EventManager
     */
    private $eventManager;

    /**
     * @var MessageManager
     */
    private $messageManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     *
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param EventManager $eventManager
     * @param MessageManager $messageManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory,
        EventManager $eventManager,
        MessageManager $messageManager,
        LoggerInterface $logger
    ) {
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->eventManager = $eventManager;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
    }

    /**
     * Handles post-redirect quote restoration after payment processing.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        if (!$this->cookieManager->getCookie(self::COOKIE_NAME)) {
            return;
        }

        try {
            $this->eventManager->dispatch('wallee_validate_and_restore_quote');
        } catch (LocalizedException $e) {
            // Surface validation reason (e.g. already paid) to the customer.
            $this->messageManager->addNoticeMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }

        $metadata = $this->cookieMetadataFactory->createCookieMetadata();
        $metadata->setPath('/');
        $this->cookieManager->deleteCookie(self::COOKIE_NAME, $metadata);
    }
}
