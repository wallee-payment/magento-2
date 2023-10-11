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
namespace Wallee\Payment\Model\Webhook\Listener\Refund;

use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Invoice;
use Wallee\Payment\Api\RefundJobRepositoryInterface;
use Wallee\Payment\Helper\Data as Helper;
use Wallee\Payment\Model\Service\LineItemReductionService;
use Wallee\Payment\Model\Service\Order\TransactionService;
use Wallee\Sdk\Model\LineItemType;
use Wallee\Sdk\Model\Refund;
use Wallee\Sdk\Model\TransactionInvoiceState;

/**
 * Webhook listener command to handle successful refunds.
 */
class SuccessfulCommand extends AbstractCommand
{

    /**
     *
     * @var RefundJobRepositoryInterface
     */
    private $refundJobRepository;

    /**
     *
     * @var CreditmemoRepositoryInterface
     */
    private $creditmemoRepository;

    /**
     *
     * @var CreditmemoFactory
     */
    private $creditmemoFactory;

    /**
     *
     * @var CreditmemoManagementInterface
     */
    private $creditmemoManagement;

    /**
     *
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     *
     * @var InvoiceRepositoryInterface
     */
    private $invoiceRepository;

    /**
     *
     * @var StockConfigurationInterface
     */
    private $stockConfiguration;

    /**
     *
     * @var LineItemReductionService
     */
    private $lineItemReductionService;

    /**
     *
     * @var TransactionService
     */
    private $transactionService;

    /**
     *
     * @var Helper
     */
    private $helper;

    /**
     *
     * @param RefundJobRepositoryInterface $refundJobRepository
     * @param CreditmemoRepositoryInterface $creditmemoRepository
     * @param CreditmemoFactory $creditmemoFactory
     * @param CreditmemoManagementInterface $creditmemoManagement
     * @param OrderRepositoryInterface $orderRepository
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param StockConfigurationInterface $stockConfiguration
     * @param LineItemReductionService $lineItemReductionService
     * @param TransactionService $transactionService
     * @param Helper $helper
     */
    public function __construct(RefundJobRepositoryInterface $refundJobRepository,
        CreditmemoRepositoryInterface $creditmemoRepository, CreditmemoFactory $creditmemoFactory,
        CreditmemoManagementInterface $creditmemoManagement, OrderRepositoryInterface $orderRepository,
        InvoiceRepositoryInterface $invoiceRepository, StockConfigurationInterface $stockConfiguration,
        LineItemReductionService $lineItemReductionService, TransactionService $transactionService, Helper $helper)
    {
        parent::__construct($refundJobRepository);
        $this->refundJobRepository = $refundJobRepository;
        $this->creditmemoRepository = $creditmemoRepository;
        $this->creditmemoFactory = $creditmemoFactory;
        $this->creditmemoManagement = $creditmemoManagement;
        $this->orderRepository = $orderRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->stockConfiguration = $stockConfiguration;
        $this->lineItemReductionService = $lineItemReductionService;
        $this->transactionService = $transactionService;
        $this->helper = $helper;
    }

    /**
     *
     * @param Refund $entity
     * @param Order $order
     */
    public function execute($entity, Order $order)
    {
        if ($this->isDerecognizedInvoice($entity, $order)) {
            $invoice = $this->getInvoiceForTransaction($entity->getTransaction(), $order);
            if (! ($invoice instanceof InvoiceInterface) || $invoice->getState() == Invoice::STATE_OPEN) {
                if (! ($invoice instanceof InvoiceInterface)) {
                    $order->setWalleeInvoiceAllowManipulation(true);
                }

                if (! ($invoice instanceof InvoiceInterface) || $invoice->getState() == Invoice::STATE_OPEN) {
                    /** @var \Magento\Sales\Model\Order\Payment $payment */
                    $payment = $order->getPayment();
                    $payment->registerCaptureNotification($entity->getAmount());
                    if (! ($invoice instanceof InvoiceInterface)) {
                        $invoice = $payment->getCreatedInvoice();
                        $order->addRelatedObject($invoice);
                    }
                }
                $this->orderRepository->save($order);
            }
        }

        /** @var \Magento\Sales\Model\Order\Creditmemo $creditmemo */
        $creditmemo = $this->creditmemoRepository->create()->load($entity->getExternalId(),
            'wallee_external_id');
        if (! $creditmemo->getId()) {
            $this->registerRefund($entity, $order);
        }
        $this->deleteRefundJob($entity);
    }

    /**
     * @param Refund $refund
     * @param Order $order
     * @return bool
     * @throws \Exception
     */
    private function isDerecognizedInvoice(Refund $refund, Order $order)
    {
        $transactionInvoice = $this->transactionService->getTransactionInvoice($order);
        if ($transactionInvoice->getState() == TransactionInvoiceState::DERECOGNIZED) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param Refund $refund
     * @param Order $order
     * @return void
     */
    private function registerRefund(Refund $refund, Order $order)
    {
        $creditmemoData = $this->collectCreditmemoData($refund, $order);
        try {
            $refundJob = $this->refundJobRepository->getByOrderId($order->getId());
            $invoice = $this->invoiceRepository->get($refundJob->getInvoiceId());
            $creditmemo = $this->creditmemoFactory->createByInvoice($invoice, $creditmemoData);
        } catch (NoSuchEntityException $e) {
            /** @todo this function expects array|string|null as a second parameter */
            $paidInvoices = $order->getInvoiceCollection()->addFieldToFilter('state', Invoice::STATE_PAID);
            if ($paidInvoices->count() == 1) {
                $creditmemo = $this->creditmemoFactory->createByInvoice($paidInvoices->getFirstItem(), $creditmemoData);
            } else {
                $creditmemo = $this->creditmemoFactory->createByOrder($order, $creditmemoData);
            }
        }
        $creditmemo->setPaymentRefundDisallowed(false);
        $creditmemo->setAutomaticallyCreated(true);
        $creditmemo->addComment(\__('The credit memo has been created automatically.'));
        $creditmemo->setWalleeExternalId($refund->getExternalId());

        foreach ($creditmemo->getAllItems() as $creditmemoItem) {
            $creditmemoItem->setBackToStock($this->stockConfiguration->isAutoReturnEnabled());
        }

        $this->creditmemoManagement->refund($creditmemo);
    }

    /**
     * @param Refund $refund
     * @param Order $order
     * @return array<mixed>
     */
    private function collectCreditmemoData(Refund $refund, Order $order)
    {
        $orderItemMap = [];
        foreach ($order->getAllItems() as $orderItem) {
            $orderItemMap[$orderItem->getQuoteItemId()] = $orderItem;
        }

        $lineItems = [];
        foreach ($refund->getTransaction()->getLineItems() as $lineItem) {
            $lineItems[$lineItem->getUniqueId()] = $lineItem;
        }

        $baseLineItems = [];
        foreach ($this->lineItemReductionService->getBaseLineItems($order->getWalleeSpaceId(),
            $refund->getTransaction()
                ->getId(), $refund) as $lineItem) {
            $baseLineItems[$lineItem->getUniqueId()] = $lineItem;
        }

        $refundQuantities = [];
        foreach ($order->getAllItems() as $orderItem) {
            $refundQuantities[$orderItem->getQuoteItemId()] = 0;
        }

        $creditmemoAmount = 0;
        $shippingAmount = 0;
        foreach ($refund->getReductions() as $reduction) {
            $lineItem = $lineItems[$reduction->getLineItemUniqueId()];
            switch ($lineItem->getType()) {
                case LineItemType::PRODUCT:
                    if ($reduction->getQuantityReduction() > 0) {
                        $refundQuantities[$orderItemMap[$reduction->getLineItemUniqueId()]->getId()] = $reduction->getQuantityReduction();
                        $creditmemoAmount += $reduction->getQuantityReduction() *
                            ($orderItemMap[$reduction->getLineItemUniqueId()]->getRowTotal() +
                            $orderItemMap[$reduction->getLineItemUniqueId()]->getTaxAmount() -
                            $orderItemMap[$reduction->getLineItemUniqueId()]->getDiscountAmount() +
                            $orderItemMap[$reduction->getLineItemUniqueId()]->getDiscountTaxCompensationAmount()) /
                            $orderItemMap[$reduction->getLineItemUniqueId()]->getQtyOrdered();
                    }
                    break;
                case LineItemType::FEE:
                case LineItemType::DISCOUNT:
                    break;
                case LineItemType::SHIPPING:
                    if ($reduction->getQuantityReduction() > 0) {
                        $shippingAmount = $baseLineItems[$reduction->getLineItemUniqueId()]->getAmountIncludingTax();
                    } elseif ($reduction->getUnitPriceReduction() > 0) {
                        $shippingAmount = $reduction->getUnitPriceReduction();
                    } else {
                        $shippingAmount = 0;
                    }

                    if ($shippingAmount == $order->getShippingInclTax()) {
                        $creditmemoAmount += $shippingAmount;
                    } elseif ($shippingAmount <= $order->getShippingInclTax() - $order->getShippingRefunded()) {
                        $creditmemoAmount += $shippingAmount;
                    } else {
                        $shippingAmount = 0;
                    }

                    if ($order->getShippingDiscountAmount() > 0) {
                        $shippingAmount += ($shippingAmount / $order->getShippingAmount()) *
                            $order->getShippingDiscountAmount();
                    }
                    break;
            }
        }

        $roundedCreditmemoAmount = $this->helper->roundAmount($creditmemoAmount,
            $refund->getTransaction()
                ->getCurrency());

        $positiveAdjustment = 0;
        $negativeAdjustment = 0;
        if ($roundedCreditmemoAmount > $refund->getAmount()) {
            $negativeAdjustment = $roundedCreditmemoAmount - $refund->getAmount();
        } elseif ($roundedCreditmemoAmount < $refund->getAmount()) {
            $positiveAdjustment = $refund->getAmount() - $roundedCreditmemoAmount;
        }

        return [
            'qtys' => $refundQuantities,
            'shipping_amount' => $shippingAmount,
            'adjustment_positive' => $positiveAdjustment,
            'adjustment_negative' => $negativeAdjustment
        ];
    }
}