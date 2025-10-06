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
use Wallee\Payment\Model\Service\Order\TransactionService;
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
     *
     * @param Context $context
     * @param CheckoutSession $checkoutSession
     * @param ScopeConfigInterface $scopeConfig
     * @param TransactionService $transactionService
     */
    public function __construct(Context $context, CheckoutSession $checkoutSession, 
    ScopeConfigInterface $scopeConfig, TransactionService $transactionService)
    {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->scopeConfig = $scopeConfig;
        $this->transactionService = $transactionService;
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        $order = $this->checkoutSession->getLastRealOrder();

        if (!$order) {
            $this->messageManager->addErrorMessage(__('No order found. Please try again.'));
            return $redirect->setPath('checkout/cart');
        }

        try {
            $integrationMethod = $this->scopeConfig->getValue('wallee_payment/checkout/integration_method', ScopeInterface::SCOPE_STORE, $order->getStoreId());
            $url = $this->transactionService->getTransactionPaymentUrl($order, $integrationMethod);
            $configurationId = $order->getPayment()
                ->getMethodInstance()
                ->getPaymentMethodConfiguration()
                ->getConfigurationId();
            return $redirect->setPath($url . '&paymentMethodConfigurationId=' . (string)$configurationId);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred while trying to redirect to payment page. Please try again.'));
            return $redirect->setPath('checkout/cart');
        }
    }
}