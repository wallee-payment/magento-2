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
namespace Wallee\Payment\Gateway\Command;

use Magento\Framework\Registry;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Sales\Model\Order\Invoice;
use Wallee\Payment\Model\Payment\Method\Adapter;
use Wallee\Payment\Model\Service\Invoice\TransactionService as InvoiceTransactionService;
use Wallee\Payment\Model\Service\Order\TransactionService as OrderTransactionService;
use Wallee\Sdk\Model\TransactionInvoiceState;

/**
 * Payment gateway command to capture a payment.
 */
class CaptureCommand implements CommandInterface
{

    /**
     *
     * @var Registry
     */
    private $registry;

    /**
     *
     * @var InvoiceTransactionService
     */
    private $invoiceTransactionService;

    /**
     *
     * @var OrderTransactionService
     */
    private $orderTransactionService;

    /**
     *
     * @param Registry $registry
     * @param InvoiceTransactionService $invoiceTransactionService
     * @param OrderTransactionService $orderTransactionService
     */
    public function __construct(Registry $registry, InvoiceTransactionService $invoiceTransactionService,
        OrderTransactionService $orderTransactionService)
    {
        $this->registry = $registry;
        $this->invoiceTransactionService = $invoiceTransactionService;
        $this->orderTransactionService = $orderTransactionService;
    }

    public function execute(array $commandSubject)
    {
        $amount = SubjectReader::readAmount($commandSubject);

        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = SubjectReader::readPayment($commandSubject)->getPayment();

        /** @var Invoice $invoice */
        $invoice = $this->registry->registry(Adapter::CAPTURE_INVOICE_REGISTRY_KEY);

        if ($invoice->getWalleeCapturePending() || $this->isTransactionInvoiceOpen($invoice)) {
            throw new \Magento\Framework\Exception\LocalizedException(
                \__(
                    'The capture has already been requested but could not be completed yet. The invoice will be updated, as soon as the capture is done.'));
        }

        $this->invoiceTransactionService->complete($payment, $invoice, $amount);
        if (! $invoice->getId()) {
            throw new \Magento\Framework\Exception\LocalizedException(
                \__('The capture has been registered. The invoice will be created, as soon as the capture is done.'));
        }
    }

    /**
     * Gets whether the transaction invoice is in an open state.
     *
     * @param Invoice $invoice
     * @return boolean
     */
    private function isTransactionInvoiceOpen(Invoice $invoice)
    {
        try {
            $invoice = $this->orderTransactionService->getTransactionInvoice($invoice->getOrder());
            return $invoice->getState() == TransactionInvoiceState::OPEN ||
                $invoice->getState() == TransactionInvoiceState::OVERDUE;
        } catch (NoSuchEntityException $e) {
            return false;
        }
    }
}