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
namespace Wallee\Payment\Controller\Checkout;

use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\Cookie\PublicCookieMetadata;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Wallee\Payment\Model\Service\Order\TransactionService;
use Wallee\Payment\Observer\RestoreCartOnCartPage;
use Magento\Store\Model\ScopeInterface;

/**
 * Frontend controller action to handle payment page url.
 */
class PaymentPageUrl extends \Wallee\Payment\Controller\Checkout
{

    /**
     *
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     *
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     *
     * @var TransactionService
     */
    private $transactionService;

    /**
     * @var CookieManagerInterface
     */
    private $cookieManager;

    /**
     * @var CookieMetadataFactory
     */
    private $cookieMetadataFactory;

    /**
     *
     * @param Context $context
     * @param CheckoutSession $checkoutSession
     * @param ScopeConfigInterface $scopeConfig
     * @param TransactionService $transactionService
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     */
    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        ScopeConfigInterface $scopeConfig,
        TransactionService $transactionService,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->scopeConfig = $scopeConfig;
        $this->transactionService = $transactionService;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
    }

    /**
     * Redirect customer to the payment page for the last placed order.
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        $order = $this->checkoutSession->getLastRealOrder();

        if (!$order) {
            $this->messageManager->addErrorMessage(__('No order found. Please try again.'));
            return $redirect->setPath('checkout/cart');
        }

        try {
            $integrationMethod = $this->scopeConfig->getValue(
                'wallee_payment/checkout/integration_method',
                ScopeInterface::SCOPE_STORE,
                $order->getStoreId()
            );
            $url = $this->transactionService->getTransactionPaymentUrl($order, $integrationMethod);
            $configurationId = $order->getPayment()
                ->getMethodInstance()
                ->getPaymentMethodConfiguration()
                ->getConfigurationId();

            // Set the restore-pending cookie so that RestoreCartOnCartPage can reactivate the
            // quote if the customer presses back from the external payment page. This mirrors the
            // cookie set by the Luma JS renderer; the Hyva server-side redirect bypasses that JS.
            /** @var PublicCookieMetadata $cookieMeta */
            $cookieMeta = $this->cookieMetadataFactory->createPublicCookieMetadata()
                ->setPath('/')
                ->setSameSite('Lax');
            $this->cookieManager->setPublicCookie(RestoreCartOnCartPage::COOKIE_NAME, '1', $cookieMeta);

            return $redirect->setPath($url . '&paymentMethodConfigurationId=' . (string)$configurationId);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('An error occurred while trying to redirect to payment page. Please try again.')
            );
            return $redirect->setPath('checkout/cart');
        }
    }
}
