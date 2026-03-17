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
namespace Wallee\Payment\Model\Webhook\Listener\Refund;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Wallee\Payment\Api\RefundJobRepositoryInterface;
use Wallee\Payment\Helper\Locale as LocaleHelper;
use Psr\Log\LoggerInterface;

/**
 * Webhook listener command to handle failed refunds.
 */
class FailedCommand extends AbstractCommand
{

    /**
     *
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     *
     * @var LocaleHelper
     */
    private $localeHelper;

    /**
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     *
     * @param RefundJobRepositoryInterface $refundJobRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param LocaleHelper $localeHelper
     * @param LoggerInterface $logger
     */
    public function __construct(
        RefundJobRepositoryInterface $refundJobRepository,
        OrderRepositoryInterface $orderRepository,
        LocaleHelper $localeHelper,
        LoggerInterface $logger
    ) {
        parent::__construct($refundJobRepository, $logger);
        $this->orderRepository = $orderRepository;
        $this->localeHelper = $localeHelper;
        $this->logger = $logger;
    }

    /**
     * Execute failed refund flow.
     *
     * @param \Wallee\Sdk\Model\Refund $entity
     * @param Order $order
     */
    public function execute($entity, Order $order)
    {
        $order->addCommentToStatusHistory(
            \__(
                'The refund of %1 failed on the gateway: %2',
                $order->getBaseCurrency()
                    ->formatTxt($entity->getAmount()),
                $this->localeHelper->translate($entity->getFailureReason()
                ->getDescription())
            )
        );
        $this->orderRepository->save($order);
        $this->deleteRefundJob($entity);
    }
}
