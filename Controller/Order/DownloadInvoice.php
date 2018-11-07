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
namespace Wallee\Payment\Controller\Order;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Controller\ResultInterface;
use Wallee\Sdk\Service\TransactionService;

/**
 * Frontend controller action to download an invoice document.
 */
class DownloadInvoice extends \Wallee\Payment\Controller\Order
{

    public function execute()
    {
        $result = $this->_orderLoader->load($this->_request);
        if ($result instanceof ResultInterface) {
            return $result;
        }

        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->_registry->registry('current_order');
        $transaction = $this->_transactionInfoRepository->getByOrderId($order->getId());
        if ($this->_documentHelper->isInvoiceDownloadAllowed($transaction, $order->getStoreId())) {
            $document = $this->_apiClient->getService(TransactionService::class)->getInvoiceDocument(
                $transaction->getSpaceId(), $transaction->getTransactionId());
            return $this->_fileFactory->create($document->getTitle() . '.pdf', \base64_decode($document->getData()),
                DirectoryList::VAR_DIR, 'application/pdf');
        } else {
            return $this->_resultForwardFactory->create()->forward('noroute');
        }
    }
}