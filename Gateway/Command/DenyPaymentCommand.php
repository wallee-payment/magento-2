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
namespace Wallee\Payment\Gateway\Command;

use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Wallee\Payment\Model\Service\Order\TransactionService;
use Wallee\PluginCore\Log\LoggerInterface;

/**
 * Payment gateway command to deny a payment.
 */
class DenyPaymentCommand implements CommandInterface
{

    /**
     *
     * @var TransactionService
     */
    private $orderTransactionService;

    /**
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     *
     * @param TransactionService $orderTransactionService
     * @param LoggerInterface $logger
     */
    public function __construct(TransactionService $orderTransactionService, LoggerInterface $logger)
    {
        $this->orderTransactionService = $orderTransactionService;
        $this->logger = $logger;
    }

    /**
     * Deny the order transaction for the given payment command.
     *
     * @param array $commandSubject
     * @return void
     */
    public function execute(array $commandSubject)
    {
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = SubjectReader::readPayment($commandSubject)->getPayment();
        $order = $payment->getOrder();

        try {
            $this->orderTransactionService->deny($order);
        } catch (\Exception $e) {
            $this->logger->error('Deny payment failed on the gateway.', [
                'orderId' => $order->getIncrementId(),
                'exception' => $e,
            ]);
            throw $e;
        }

        $order->setWalleeInvoiceAllowManipulation(true);

        $this->logger->info('Deny payment completed.', ['orderId' => $order->getIncrementId()]);
    }
}
