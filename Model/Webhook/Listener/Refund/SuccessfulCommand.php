<?php
/**
 * wallee Magento 2
 *
 * This Magento 2 extension enables to process payments with wallee (https://www.wallee.com/).
 *
 * @package Wallee_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
namespace Wallee\Payment\Model\Webhook\Listener\Refund;

use Magento\Framework\DB\TransactionFactory as DBTransactionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Email\Sender\OrderSender as OrderEmailSender;
use Wallee\Payment\Helper\Data as Helper;
use Wallee\Payment\Model\RefundJobRepository;
use Wallee\Payment\Model\Service\LineItemReductionService;
use Wallee\Sdk\Model\LineItemType;
use Wallee\Sdk\Model\Refund;

/**
 * Webhook listener command to handle successful refunds.
 */
class SuccessfulCommand extends AbstractCommand
{

    /**
     *
     * @var CreditmemoRepositoryInterface
     */
    protected $_creditmemoRepository;

    /**
     *
     * @var CreditmemoFactory
     */
    protected $_creditmemoFactory;

    /**
     *
     * @var CreditmemoManagementInterface
     */
    protected $_creditmemoManagement;

    /**
     *
     * @var InvoiceRepositoryInterface
     */
    protected $_invoiceRepository;

    /**
     *
     * @var LineItemReductionService
     */
    protected $_lineItemReductionService;

    /**
     *
     * @var Helper
     */
    protected $_helper;

    /**
     *
     * @param DBTransactionFactory $dbTransactionFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderEmailSender $orderEmailSender
     * @param RefundJobRepository $refundJobRepository
     * @param CreditmemoRepositoryInterface $creditmemoRepository
     * @param CreditmemoFactory $creditmemoFactory
     * @param CreditmemoManagementInterface $creditmemoManagement
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param LineItemReductionService $lineItemReductionService
     * @param Helper $helper
     */
    public function __construct(DBTransactionFactory $dbTransactionFactory, OrderRepositoryInterface $orderRepository,
        OrderEmailSender $orderEmailSender, RefundJobRepository $refundJobRepository,
        CreditmemoRepositoryInterface $creditmemoRepository, CreditmemoFactory $creditmemoFactory,
        CreditmemoManagementInterface $creditmemoManagement, InvoiceRepositoryInterface $invoiceRepository,
        LineItemReductionService $lineItemReductionService, Helper $helper)
    {
        parent::__construct($dbTransactionFactory, $orderRepository, $orderEmailSender, $refundJobRepository);
        $this->_creditmemoRepository = $creditmemoRepository;
        $this->_creditmemoFactory = $creditmemoFactory;
        $this->_creditmemoManagement = $creditmemoManagement;
        $this->_invoiceRepository = $invoiceRepository;
        $this->_lineItemReductionService = $lineItemReductionService;
        $this->_helper = $helper;
    }

    /**
     *
     * @param Refund $entity
     * @param Order $order
     */
    public function execute($entity, Order $order)
    {
        /** @var \Magento\Sales\Model\Order\Creditmemo $creditmemo */
        $creditmemo = $this->_creditmemoRepository->create()->load($entity->getExternalId(),
            'wallee_external_id');
        if (! $creditmemo->getId()) {
            $this->registerRefund($entity, $order);
        }
        $this->deleteRefundJob($entity);
    }

    protected function registerRefund(Refund $refund, Order $order)
    {
        $creditmemoData = $this->collectCreditmemoData($refund, $order);
        try {
            $refundJob = $this->_refundJobRepository->getByOrderId($order->getId());
            $invoice = $this->_invoiceRepository->get($refundJob->getInvoiceId());
            $creditmemo = $this->_creditmemoFactory->createByInvoice($invoice, $creditmemoData);
        } catch (NoSuchEntityException $e) {
            $paidInvoices = $order->getInvoiceCollection()->addFieldToFilter('state', Invoice::STATE_PAID);
            if ($paidInvoices->count() == 1) {
                $creditmemo = $this->_creditmemoFactory->createByInvoice($paidInvoices->getFirstItem(), $creditmemoData);
            } else {
                $creditmemo = $this->_creditmemoFactory->createByOrder($order, $creditmemoData);
            }
        }
        $creditmemo->setPaymentRefundDisallowed(false);
        $creditmemo->setAutomaticallyCreated(true);
        $creditmemo->addComment(\__('The credit memo has been created automatically.'));
        $creditmemo->setWalleeExternalId($refund->getExternalId());
        $this->_creditmemoManagement->refund($creditmemo);
    }

    protected function collectCreditmemoData(Refund $refund, Order $order)
    {
        $orderItemMap = array();
        foreach ($order->getAllItems() as $orderItem) {
            $orderItemMap[$orderItem->getQuoteItemId()] = $orderItem;
        }

        $lineItems = array();
        foreach ($refund->getTransaction()->getLineItems() as $lineItem) {
            $lineItems[$lineItem->getUniqueId()] = $lineItem;
        }

        $baseLineItems = array();
        foreach ($this->_lineItemReductionService->getBaseLineItems($order->getWalleeSpaceId(),
            $refund->getTransaction()
                ->getId(), $refund) as $lineItem) {
            $baseLineItems[$lineItem->getUniqueId()] = $lineItem;
        }

        $refundQuantities = array();
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

                    if ($shippingAmount <= $order->getShippingInclTax() - $order->getShippingRefunded()) {
                        $creditmemoAmount += $shippingAmount;
                    } else {
                        $shippingAmount = 0;
                    }

                    if ($order->getShippingDiscountAmount() > 0) {
                        $shippingAmount += ($shippingAmount / $order->getShippingAmount()) * $order->getShippingDiscountAmount();
                    }
                    break;
            }
        }

        $roundedCreditmemoAmount = $this->_helper->roundAmount($creditmemoAmount, $refund->getTransaction()->getCurrency());

        $positiveAdjustment = 0;
        $negativeAdjustment = 0;
        if ($roundedCreditmemoAmount > $refund->getAmount()) {
            $negativeAdjustment = $roundedCreditmemoAmount - $refund->getAmount();
        } elseif ($roundedCreditmemoAmount < $refund->getAmount()) {
            $positiveAdjustment = $refund->getAmount() - $roundedCreditmemoAmount;
        }

        return array(
            'qtys' => $refundQuantities,
            'shipping_amount' => $shippingAmount,
            'adjustment_positive' => $positiveAdjustment,
            'adjustment_negative' => $negativeAdjustment
        );
    }
}