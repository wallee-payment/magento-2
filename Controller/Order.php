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
namespace Wallee\Payment\Controller;

use Magento\Framework\Registry;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Sales\Controller\AbstractController\OrderLoaderInterface;
use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Wallee\Payment\Helper\Data as Helper;
use Wallee\Payment\Helper\Document as DocumentHelper;
use Wallee\Payment\Model\ApiClient;

/**
 * Abstract frontend controller action to handle order related requests.
 */
abstract class Order extends \Magento\Framework\App\Action\Action
{

    /**
     *
     * @var ForwardFactory
     */
    protected $_resultForwardFactory;

    /**
     *
     * @var FileFactory
     */
    protected $_fileFactory;

    /**
     *
     * @var Registry
     */
    protected $_registry;

    /**
     *
     * @var Helper
     */
    protected $_helper;

    /**
     *
     * @var DocumentHelper
     */
    protected $_documentHelper;

    /**
     *
     * @var OrderLoaderInterface
     */
    protected $_orderLoader;

    /**
     *
     * @var TransactionInfoRepositoryInterface
     */
    protected $_transactionInfoRepository;

    /**
     *
     * @var ApiClient
     */
    protected $_apiClient;

    /**
     *
     * @param Context $context
     * @param ForwardFactory $resultForwardFactory
     * @param FileFactory $fileFactory
     * @param Registry $registry
     * @param Helper $helper
     * @param DocumentHelper $documentHelper
     * @param OrderLoaderInterface $orderLoader
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param ApiClient $apiClient
     */
    public function __construct(Context $context, ForwardFactory $resultForwardFactory, FileFactory $fileFactory,
        Registry $registry, Helper $helper, DocumentHelper $documentHelper, OrderLoaderInterface $orderLoader,
        TransactionInfoRepositoryInterface $transactionInfoRepository, ApiClient $apiClient)
    {
        parent::__construct($context);
        $this->_resultForwardFactory = $resultForwardFactory;
        $this->_fileFactory = $fileFactory;
        $this->_registry = $registry;
        $this->_helper = $helper;
        $this->_documentHelper = $documentHelper;
        $this->_orderLoader = $orderLoader;
        $this->_transactionInfoRepository = $transactionInfoRepository;
        $this->_apiClient = $apiClient;
    }
}