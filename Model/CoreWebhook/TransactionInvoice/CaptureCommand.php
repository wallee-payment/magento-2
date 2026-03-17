<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\CoreWebhook\TransactionInvoice;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Email\Sender\OrderSender as OrderEmailSender;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Wallee\Sdk\Model\TransactionState;
use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Wallee\Payment\Model\CoreWebhook\OrderInvoiceTrait;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Webhook\Command\WebhookCommand;
use Wallee\PluginCore\Webhook\WebhookContext;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Magento\Sales\Model\OrderFactory;

class CaptureCommand extends WebhookCommand
{
    use TransactionInvoiceCommandTrait;
    use OrderInvoiceTrait;

    /**
     *
     * @param WebhookContext $context
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderEmailSender $orderEmailSender
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SdkProvider $sdkProvider
     * @param OrderResourceModel $orderResourceModel
     * @param OrderFactory $orderFactory
     */
    public function __construct(
        WebhookContext $context,
        LoggerInterface $logger,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderEmailSender $orderEmailSender,
        private readonly TransactionInfoRepositoryInterface $transactionInfoRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SdkProvider $sdkProvider,
        private readonly OrderResourceModel $orderResourceModel,
        private readonly OrderFactory $orderFactory
    ) {
        parent::__construct($context, $logger);
    }

    /**
     * Execute capture command for the current webhook context.
     *
     * @return mixed
     */
    public function execute(): mixed
    {
        $this->logger->info("Running TransactionInvoice/CaptureCommand for entity: " . $this->context->entityId);

        $invoiceEntity = $this->loadTransactionInvoice();
        if (!$invoiceEntity) {
            $this->logger->warning(
                "TransactionInvoice/CaptureCommand: Could not load SDK Invoice entity for ID " .
                $this->context->entityId
            );
            return null;
        }

        $order = $this->findOrderFromInvoice($invoiceEntity);
        if (!$order) {
            $this->logger->warning(
                "TransactionInvoice/CaptureCommand: Could not find Magento Order for Invoice entity ID " .
                $this->context->entityId
            );
            return null;
        }

        // 1. Load FRESH state from DB (Bypassing cache) to detect race conditions
        $freshOrder = $this->orderFactory->create();
        $this->orderResourceModel->load($freshOrder, $order->getId());

        // Detect if we are currently in Payment Review (e.g. set by DeliveryIndication)
        $isPaymentReview = ($freshOrder->getState() === Order::STATE_PAYMENT_REVIEW);

        $transaction = $invoiceEntity->getCompletion()->getLineItemVersion()->getTransaction();
        $txState = $transaction->getState();

        // Set payment review state if needed (Legacy logic for async payments)
        if (!in_array($txState, [TransactionState::FULFILL, TransactionState::COMPLETED], true)) {
            if ($order->getState() !== Order::STATE_PAYMENT_REVIEW) {
                $order->setState(Order::STATE_PAYMENT_REVIEW);
                $order->addStatusToHistory(true, __('Payment is under review.')->render());
                $isPaymentReview = true;
            }
        }

        // 2. Find existing invoice
        $existingInvoice = $this->getInvoiceForTransaction(
            $transaction->getId(),
            $transaction->getLinkedSpaceId(),
            $order
        );

        // 3. Capture Logic
        // We determine if we need to run the capture logic (create new or update existing)
        $needsCapture = !($existingInvoice instanceof InvoiceInterface)
            || $existingInvoice->getState() == Invoice::STATE_OPEN;

        $finalInvoice = $existingInvoice; // Default to existing

        if ($needsCapture) {
            // WARNING: captureInvoice() implicitly sets Order State to PROCESSING in memory!
            $finalInvoice = $this->captureInvoice($order, $invoiceEntity->getAmount(), $existingInvoice);

            // 4. The Revert Fix
            // If we were in Payment Review, we must force it back immediately because
            // captureInvoice() just overwrote it to PROCESSING.
            if ($isPaymentReview && $finalInvoice) {
                $this->logger->info("CaptureCommand: Restoring Payment Review state after capture.");
                $order->setState(Order::STATE_PAYMENT_REVIEW);
                $order->setStatus($freshOrder->getStatus());
            }
        }

        if (!$finalInvoice) {
            $this->logger->warning(
                "No invoice could be found or created for TransactionInvoice {$invoiceEntity->getId()}."
            );
            return $order;
        }

        // 5. Final State Update Logic
        // If the transaction is DONE, we ensure the order is PROCESSING.
        if ($transaction->getState() == TransactionState::FULFILL) {

            // We use canHold() to check if the order is "Editable".
            // It returns FALSE if order is Canceled, Closed, Complete, or Payment Review.
            if ($freshOrder->canHold()) {
                // Safe to update. Ensure state/status are correct.
                if ($order->getState() !== Order::STATE_PROCESSING
                    || $order->getStatus() !== Order::STATE_PROCESSING
                ) {
                    $this->logger->info("CaptureCommand: Updating order state to Processing.");

                    $order->setState(Order::STATE_PROCESSING);
                    $order->setStatus(Order::STATE_PROCESSING);
                    $order->addStatusToHistory(
                        Order::STATE_PROCESSING,
                        __('Invoice captured. Updating status.')->render(),
                        false
                    );
                }
            } else {
                // The order is in a protected state (Review, Complete, Closed). We do NOT touch it.
                $this->logger->debug(sprintf(
                    'CaptureCommand: Skipping state update to PROCESSING' .
                    'because order %s is in a protected state (%s).',
                    $order->getIncrementId(),
                    $freshOrder->getState()
                ));
            }
        }

        $order->setWalleeAuthorized(true);
        $this->orderRepository->save($order);

        return $order;
    }

    /**
     * Ported private helper method
     *
     * @param Order $order
     * @param float $amount
     * @param InvoiceInterface|null $invoice
     * @return InvoiceInterface|null
     */
    private function captureInvoice(Order $order, float $amount, ?InvoiceInterface $invoice): ?InvoiceInterface
    {
        $payment = $order->getPayment();
        $payment->setTransactionId(null);
        $payment->setParentTransactionId($payment->getTransactionId());
        $payment->setIsTransactionClosed(true);
        $payment->registerCaptureNotification($amount, true);

        $invoice = $payment->getCreatedInvoice() ?: $invoice;

        if ($invoice instanceof InvoiceInterface) {
            $invoice->pay();
            $invoice->setWalleeCapturePending(false);
            $order->addRelatedObject($invoice);
            return $invoice;
        }

        foreach ($order->getRelatedObjects() as $object) {
            if ($object instanceof InvoiceInterface) {
                return $object;
            }
        }
        return null;
    }
}
