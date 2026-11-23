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

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Controller\AbstractController\OrderViewAuthorizationInterface;
use Magento\Sales\Model\Order;
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
     * @var DocumentHelper
     */
    protected $documentHelper;

    /**
     *
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     *
     * @var OrderViewAuthorizationInterface
     */
    private $orderAuthorization;

    /**
     *
     * @var CustomerSession
     */
    private $customerSession;

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
     * @param DocumentHelper $documentHelper
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderViewAuthorizationInterface $orderAuthorization
     * @param CustomerSession $customerSession
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param DocumentService $documentService
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        ForwardFactory $resultForwardFactory,
        FileFactory $fileFactory,
        DocumentHelper $documentHelper,
        OrderRepositoryInterface $orderRepository,
        OrderViewAuthorizationInterface $orderAuthorization,
        CustomerSession $customerSession,
        TransactionInfoRepositoryInterface $transactionInfoRepository,
        DocumentService $documentService,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->resultForwardFactory = $resultForwardFactory;
        $this->fileFactory = $fileFactory;
        $this->documentHelper = $documentHelper;
        $this->orderRepository = $orderRepository;
        $this->orderAuthorization = $orderAuthorization;
        $this->customerSession = $customerSession;
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
        $order = $this->loadOrder();
        if ($order === null) {
            return $this->resultForwardFactory->create()->forward('noroute');
        }

        if (!$this->canDownload($order)) {
            return $this->redirectUnauthorized($order);
        }

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
     * @return Order|null
     */
    private function loadOrder()
    {
        $orderId = (int) $this->getRequest()->getParam('order_id');
        if ($orderId <= 0) {
            return null;
        }

        try {
            return $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * A link carrying the order's protect code (as sent in order emails) grants access on its own,
     * otherwise the visitor must be the logged-in owner of the order.
     *
     * @param Order $order
     * @return bool
     */
    private function canDownload(Order $order): bool
    {
        $protectCode = (string) $this->getRequest()->getParam('protect_code', '');
        if ($protectCode !== '' && (string) $order->getProtectCode() !== '') {
            return \hash_equals((string) $order->getProtectCode(), $protectCode);
        }

        return $this->orderAuthorization->canView($order);
    }

    /**
     * Sends a visitor who may not download the document to a page that exists: the order history
     * when logged in, the guest order lookup for guest orders, the login page otherwise.
     *
     * @param Order $order
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    private function redirectUnauthorized(Order $order)
    {
        $redirect = $this->resultRedirectFactory->create();

        if ($this->customerSession->isLoggedIn()) {
            return $redirect->setPath('sales/order/history');
        }

        if ($order->getCustomerIsGuest()) {
            return $redirect->setPath('sales/guest/form');
        }

        $this->customerSession->setBeforeAuthUrl($this->_url->getCurrentUrl());
        return $redirect->setPath('customer/account/login');
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
