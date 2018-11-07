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
namespace Wallee\Payment\Gateway\Command;

use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Wallee\Payment\Helper\Locale as LocaleHelper;
use Wallee\Payment\Model\Service\Order\TransactionService;
use Wallee\Sdk\Model\TransactionVoidState;

/**
 * Payment gateway command to void a payment.
 */
class VoidCommand implements CommandInterface
{

    /**
     *
     * @var LocaleHelper
     */
    protected $_localeHelper;

    /**
     *
     * @var TransactionService
     */
    protected $_orderTransactionService;

    /**
     *
     * @param LocaleHelper $localeHelper
     * @param TransactionService $orderTransactionService
     */
    public function __construct(LocaleHelper $localeHelper, TransactionService $orderTransactionService)
    {
        $this->_localeHelper = $localeHelper;
        $this->_orderTransactionService = $orderTransactionService;
    }

    public function execute(array $commandSubject)
    {
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = SubjectReader::readPayment($commandSubject)->getPayment();

        $void = $this->_orderTransactionService->void($payment->getOrder());
        if ($void->getState() == TransactionVoidState::FAILED) {
            throw new \Magento\Framework\Exception\LocalizedException(
                \__('The void of the payment failed on the gateway: %1',
                    $this->_localeHelper->translate(
                        $void->getFailureReason()
                            ->getDescription())));
        }
    }
}