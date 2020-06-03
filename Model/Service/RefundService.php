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
namespace Wallee\Payment\Model\Service;


use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Wallee\Payment\Api\RefundJobRepositoryInterface;
use Wallee\Payment\Api\Data\RefundJobInterface;
use Wallee\Payment\Model\ApiClient;
use Wallee\Payment\Model\RefundJobFactory;
use Wallee\Sdk\Model\RefundCreate;
use Wallee\Sdk\Model\RefundType;

/**
 * Service to handle creditmemos.
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
     * @param ApiClient $apiClient
     */
    public function __construct(LineItemReductionService $lineItemReductionService,
        RefundJobFactory $refundJobFactory, RefundJobRepositoryInterface $refundJobRepository)
    {
        $this->lineItemReductionService = $lineItemReductionService;
        $this->refundJobFactory = $refundJobFactory;
        $this->refundJobRepository = $refundJobRepository;
    }

    /**
     * Creates a new refund job for the given invoice and refund.
     *
     * @param Invoice $invoice
     * @param RefundCreate $refund
     * @return \Wallee\Payment\Model\RefundJob
     */
    public function createRefundJob(Invoice $invoice, RefundCreate $refund)
    {
        $entity = $this->refundJobFactory->create();
        $entity->setData(RefundJobInterface::ORDER_ID, $invoice->getOrderId());
        $entity->setData(RefundJobInterface::INVOICE_ID, $invoice->getId());
        $entity->setData(RefundJobInterface::SPACE_ID, $invoice->getOrder()
            ->getWalleeSpaceId());
        $entity->setData(RefundJobInterface::EXTERNAL_ID, $refund->getExternalId());
        $entity->setData(RefundJobInterface::REFUND, $refund);
        return $this->refundJobRepository->save($entity);
    }

    /**
     * Creates a refund creation model for the given creditmemo.
     *
     * @param Creditmemo $creditmemo
     * @return RefundCreate
     */
    public function createRefund(Creditmemo $creditmemo)
    {
        $refund = new RefundCreate();
        $refund->setExternalId(\uniqid($creditmemo->getOrderId() . '-'));

        try {
            $reductions = $this->lineItemReductionService->convertCreditmemo($creditmemo);
            $refund->setReductions($reductions);
        } catch (LineItemReductionException $e) {
            $refund->setAmount($creditmemo->getGrandTotal());
        }

        $refund->setTransaction($creditmemo->getOrder()
            ->getWalleeTransactionId());
        $refund->setType(RefundType::MERCHANT_INITIATED_ONLINE);
        return $refund;
    }

}