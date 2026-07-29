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

use Magento\Framework\Registry;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Sales\Controller\AbstractController\OrderLoaderInterface;
use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Wallee\Payment\Api\Data\TransactionInfoInterface;
use Wallee\Payment\Controller\DocumentDownloadResponseTrait;
use Wallee\Payment\Helper\Document as DocumentHelper;
use Wallee\PluginCore\Document\DocumentService;
use Wallee\PluginCore\Document\RenderedDocument;
use Wallee\PluginCore\Log\LoggerInterface;

/**
 * Base frontend controller that resolves the document for download.
 */
abstract class AbstractDownloadDocument extends \Wallee\Payment\Controller\Order
{
    use DocumentDownloadResponseTrait;

    /**
     *
     * @var ForwardFactory
     */
    private $resultForwardFactory;

    /**
     *
     * @var FileFactory
     */
    private $fileFactory;

    /**
     *
     * @var Registry
     */
    private $registry;

    /**
     *
     * @var DocumentHelper
     */
    protected $documentHelper;

    /**
     *
     * @var OrderLoaderInterface
     */
    private $orderLoader;

    /**
     *
     * @var TransactionInfoRepositoryInterface
     */
    private $transactionInfoRepository;

    /**
     *
     * @var DocumentService
     */
    protected $documentService;

    /**
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Context $context
     * @param ForwardFactory $resultForwardFactory
     * @param FileFactory $fileFactory
     * @param Registry $registry
     * @param DocumentHelper $documentHelper
     * @param OrderLoaderInterface $orderLoader
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param DocumentService $documentService
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        ForwardFactory $resultForwardFactory,
        FileFactory $fileFactory,
        Registry $registry,
        DocumentHelper $documentHelper,
        OrderLoaderInterface $orderLoader,
        TransactionInfoRepositoryInterface $transactionInfoRepository,
        DocumentService $documentService,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->resultForwardFactory = $resultForwardFactory;
        $this->fileFactory = $fileFactory;
        $this->registry = $registry;
        $this->documentHelper = $documentHelper;
        $this->orderLoader = $orderLoader;
        $this->transactionInfoRepository = $transactionInfoRepository;
        $this->documentService = $documentService;
        $this->logger = $logger;
    }

    /**
     * Download the transaction document if allowed.
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    #[\ReturnTypeWillChange]
    public function execute()
    {
        $result = $this->orderLoader->load($this->_request);
        if ($result instanceof ResultInterface) {
            return $result;
        }

        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->registry->registry('current_order');
        $transaction = $this->transactionInfoRepository->getByOrderId($order->getId());
        if (!$this->isDocumentDownloadAllowed($transaction, $order->getStoreId())) {
            return $this->resultForwardFactory->create()->forward('noroute');
        }

        try {
            $document = $this->getDocument(
                (int) $transaction->getSpaceId(),
                (int) $transaction->getTransactionId()
            );
        } catch (\Exception $e) {
            $this->logger->error('Document download failed.', [
                'orderId' => $order->getId(),
                'exception' => $e,
            ]);
            throw $e;
        }

        $this->logger->info('Document downloaded.', [
            'orderId' => $order->getId(),
            'document' => $document->title,
        ]);

        return $this->createDocumentDownloadResponse($document);
    }

    /**
     * Checks whether the customer can download document for the given transaction.
     *
     * @param TransactionInfoInterface $transaction
     * @param int|null $storeId
     * @return bool
     */
    abstract protected function isDocumentDownloadAllowed(TransactionInfoInterface $transaction, $storeId): bool;

    /**
     * Fetches the specific document for the given transaction.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @return RenderedDocument
     */
    abstract protected function getDocument(int $spaceId, int $transactionId): RenderedDocument;
}
