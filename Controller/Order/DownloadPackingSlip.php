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
namespace Wallee\Payment\Controller\Order;

use Wallee\Payment\Api\Data\TransactionInfoInterface;
use Wallee\Payment\Controller\Order\AbstractDownloadDocument;
use Wallee\PluginCore\Document\RenderedDocument;

/**
 * Frontend controller action to download a packing slip.
 */
class DownloadPackingSlip extends AbstractDownloadDocument
{
    /**
     * @inheritDoc
     */
    protected function isDocumentDownloadAllowed(TransactionInfoInterface $transaction, $storeId): bool
    {
        return $this->documentHelper->isPackingSlipDownloadAllowed($transaction, $storeId);
    }

    /**
     * @inheritDoc
     */
    protected function getDocument(int $spaceId, int $transactionId): RenderedDocument
    {
        return $this->documentService->getPackingSlip($spaceId, $transactionId);
    }
}
