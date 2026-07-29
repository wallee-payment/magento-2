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
namespace Wallee\Payment\Controller;

use Magento\Framework\App\Filesystem\DirectoryList;
use Wallee\PluginCore\Document\RenderedDocument;

/**
 * Shared logic to build a file-download HTTP response from a rendered document.
 *
 * Assumes the class using this trait has a $fileFactory property exposing a
 * create($fileName, $content, $baseDir, $contentType) method — either
 * Magento\Backend\App\Response\Http\FileFactory (admin) or
 * Magento\Framework\App\Response\Http\FileFactory (storefront).
 */
trait DocumentDownloadResponseTrait
{
    /**
     * Builds the file-download response for the given rendered document.
     *
     * @param RenderedDocument $document
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    protected function createDocumentDownloadResponse(RenderedDocument $document)
    {
        return $this->fileFactory->create(
            $document->title . '.pdf',
            $document->data,
            DirectoryList::VAR_DIR,
            $document->mimeType
        );
    }
}
