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
namespace Wallee\Payment\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Wallee\Payment\Model\TransactionInfo;
use Wallee\PluginCore\Transaction\State as CoreTransactionState;

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
    public function isInvoiceDownloadAllowed(TransactionInfo $transaction, $storeId = null): bool
    {
        if (! (CoreTransactionState::tryFrom($transaction->getState())?->isInvoiceDownloadAllowed() ?? false)) {
            return false;
        }

        return $this->isCustomerDownloadAllowed(
            'wallee_payment/document/customer_download_invoice',
            $storeId
        );
    }

    /**
     * Gets whether the user is allowed to download the transaction's packing slip.
     *
     * @param TransactionInfo $transaction
     * @param int $storeId
     * @return boolean
     */
    public function isPackingSlipDownloadAllowed(TransactionInfo $transaction, $storeId = null): bool
    {
        if (! (CoreTransactionState::tryFrom($transaction->getState())?->isPackingSlipDownloadAllowed() ?? false)) {
            return false;
        }

        return $this->isCustomerDownloadAllowed(
            'wallee_payment/document/customer_download_packing_slip',
            $storeId
        );
    }

    /**
     * Gets whether the user is allowed to download the transaction's refund document.
     *
     * @param TransactionInfo $transaction
     * @param int $storeId
     * @return boolean
     */
    public function isRefundDownloadAllowed(TransactionInfo $transaction, $storeId = null): bool
    {
        return $this->isCustomerDownloadAllowed(
            'wallee_payment/document/customer_download_refund',
            $storeId
        );
    }

    /**
     * Checks whether user is admin or if document's download is allowed.
     *
     * @param string $configPath
     * @param int $storeId
     * @return boolean
     */
    private function isCustomerDownloadAllowed(string $configPath, $storeId = null): bool
    {
        if ($this->helper->isAdminArea()
            || $this->scopeConfig->getValue($configPath, ScopeInterface::SCOPE_STORE, $storeId)
        ) {
            return true;
        }
        return false;
    }
}
