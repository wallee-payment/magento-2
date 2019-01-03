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
namespace Wallee\Payment\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Wallee\Payment\Model\TransactionInfo;
use Wallee\Sdk\Model\TransactionState;

/**
 * Helper to provide document related functionality.
 */
class Document extends AbstractHelper
{

    /**
     *
     * @var Data
     */
    private $helper;

    /**
     *
     * @param Context $context
     * @param Data $helper
     */
    public function __construct(Context $context, Data $helper)
    {
        parent::__construct($context);
        $this->helper = $helper;
    }

    /**
     * Gets whether the user is allowed to download the transaction's invoice document.
     *
     * @param TransactionInfo $transaction
     * @param int $storeId
     * @return boolean
     */
    public function isInvoiceDownloadAllowed(TransactionInfo $transaction, $storeId = null)
    {
        if (! \in_array($transaction->getState(),
            [
                TransactionState::COMPLETED,
                TransactionState::FULFILL,
                TransactionState::DECLINE
            ])) {
            return false;
        }

        if (! $this->helper->isAdminArea() && ! $this->scopeConfig->getValue(
            'wallee_payment/document/customer_download_invoice', ScopeInterface::SCOPE_STORE, $storeId)) {
            return false;
        }

        return true;
    }

    /**
     * Gets whether the user is allowed to download the transaction's packing slip.
     *
     * @param TransactionInfo $transaction
     * @param int $storeId
     * @return boolean
     */
    public function isPackingSlipDownloadAllowed(TransactionInfo $transaction, $storeId = null)
    {
        if ($transaction->getState() != TransactionState::FULFILL) {
            return false;
        }

        if (! $this->helper->isAdminArea() && ! $this->scopeConfig->getValue(
            'wallee_payment/document/customer_download_packing_slip', ScopeInterface::SCOPE_STORE,
            $storeId)) {
            return false;
        }

        return true;
    }

    /**
     * Gets whether the user is allowed to download the transaction's refund document.
     *
     * @param TransactionInfo $transaction
     * @param int $storeId
     * @return boolean
     */
    public function isRefundDownloadAllowed(TransactionInfo $transaction, $storeId = null)
    {
        if (! $this->helper->isAdminArea() && ! $this->scopeConfig->getValue(
            'wallee_payment/document/customer_download_refund', ScopeInterface::SCOPE_STORE, $storeId)) {
            return false;
        }

        return true;
    }
}