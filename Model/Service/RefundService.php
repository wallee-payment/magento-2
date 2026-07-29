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
namespace Wallee\Payment\Model\Service;

use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Wallee\Payment\Api\RefundJobRepositoryInterface;
use Wallee\Payment\Api\Data\RefundJobInterface;
use Wallee\Payment\Model\RefundJobFactory;
use Wallee\PluginCore\Refund\LineItem\RefundLineItem;
use Wallee\PluginCore\Refund\LineItem\RefundLineItemCollection;
use Wallee\PluginCore\Refund\RefundContext;
use Wallee\PluginCore\Refund\Type as CoreType;

/**
 * Service to build refund payloads for creditmemos.
 *
 * This is strictly a payload builder: it does not call the Wallee API.
 * The actual submission happens in Gateway\Command\RefundCommand and Cron\Refund, which
 * execute the RefundContext built here via PluginCore\Refund\RefundService::createRefund().
 */
class RefundService
{

    /**
     *
     * @var LineItemReductionService
     */
    private $lineItemReductionService;

    /**
     *
     * @var RefundJobFactory
     */
    private $refundJobFactory;

    /**
     *
     * @var RefundJobRepositoryInterface
     */
    private $refundJobRepository;

    /**
     *
     * @param LineItemReductionService $lineItemReductionService
     * @param RefundJobFactory $refundJobFactory
     * @param RefundJobRepositoryInterface $refundJobRepository
     */
    public function __construct(
        LineItemReductionService $lineItemReductionService,
        RefundJobFactory $refundJobFactory,
        RefundJobRepositoryInterface $refundJobRepository
    ) {
        $this->lineItemReductionService = $lineItemReductionService;
        $this->refundJobFactory = $refundJobFactory;
        $this->refundJobRepository = $refundJobRepository;
    }

    /**
     * Creates a new refund job for the given invoice and refund context.
     *
     * The job persists the not-yet-submitted RefundContext so that the actual submission
     * (in RefundCommand or the Cron\Refund retry worker) can be retried without redoing the
     * creditmemo-to-line-item mapping.
     *
     * @param Invoice $invoice
     * @param RefundContext $refund
     * @return \Wallee\Payment\Model\RefundJob
     */
    public function createRefundJob(Invoice $invoice, RefundContext $refund)
    {
        $entity = $this->refundJobFactory->create();
        $entity->setData(RefundJobInterface::ORDER_ID, $invoice->getOrderId());
        $entity->setData(RefundJobInterface::INVOICE_ID, $invoice->getId());
        $entity->setData(RefundJobInterface::SPACE_ID, $invoice->getOrder()
            ->getWalleeSpaceId());
        $entity->setData(RefundJobInterface::EXTERNAL_ID, $refund->externalId ?? '');
        $entity->setData(RefundJobInterface::REFUND, $refund);
        return $this->refundJobRepository->save($entity);
    }

    /**
     * Builds the refund context for the given creditmemo.
     *
     * This only builds the payload; it does not call the Wallee API.
     *
     * @param Creditmemo $creditmemo
     * @return RefundContext
     */
    public function createRefund(Creditmemo $creditmemo): RefundContext
    {
        $order = $creditmemo->getOrder();

        try {
            $reductions = $this->lineItemReductionService->convertCreditmemo($creditmemo);
        } catch (LineItemReductionException $e) {
            $reductions = [];
        }

        $lineItems = new RefundLineItemCollection(
            ...array_map(
                static fn (array $reduction): RefundLineItem => new RefundLineItem(
                    uniqueId: (string) $reduction['uniqueId'],
                    returnedQuantity: (float) $reduction['quantity'],
                    unitPriceReduction: (float) $reduction['amount'],
                ),
                $reductions
            )
        );

        return new RefundContext(
            transactionId: (int) $order->getWalleeTransactionId(),
            amount: (float) $creditmemo->getGrandTotal(),
            merchantReference: (string) $order->getIncrementId(),
            type: CoreType::MERCHANT_INITIATED_ONLINE,
            lineItems: $lineItems,
        );
    }
}
