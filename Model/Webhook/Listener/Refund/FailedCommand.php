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
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender as OrderEmailSender;
use Wallee\Payment\Helper\Locale as LocaleHelper;
use Wallee\Payment\Model\RefundJobRepository;

/**
 * Webhook listener command to handle failed refunds.
 */
class FailedCommand extends AbstractCommand
{

    /**
     *
     * @var LocaleHelper
     */
    protected $_localeHelper;

    /**
     *
     * @param DBTransactionFactory $dbTransactionFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderEmailSender $orderEmailSender
     * @param RefundJobRepository $refundJobRepository
     * @param LocaleHelper $localeHelper
     */
    public function __construct(DBTransactionFactory $dbTransactionFactory, OrderRepositoryInterface $orderRepository,
        OrderEmailSender $orderEmailSender, RefundJobRepository $refundJobRepository, LocaleHelper $localeHelper)
    {
        parent::__construct($dbTransactionFactory, $orderRepository, $orderEmailSender, $refundJobRepository);
        $this->_localeHelper = $localeHelper;
    }

    /**
     *
     * @param \Wallee\Sdk\Model\Refund $entity
     * @param Order $order
     */
    public function execute($entity, Order $order)
    {
        $order->addStatusHistoryComment(
            \__('The refund of %1 failed on the gateway: %2',
                $order->getBaseCurrency()
                    ->formatTxt($entity->getAmount()),
                $this->_localeHelper->translate($entity->getFailureReason()
                    ->getDescription())));
        $this->_orderRepository->save($order);
        $this->deleteRefundJob($entity);
    }
}