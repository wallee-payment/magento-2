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
namespace Wallee\Payment\Controller\Adminhtml;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Wallee\Payment\Helper\Data as Helper;
use Wallee\Payment\Model\ApiClient;

/**
 * Abstract backend controller action to handle order related requests.
 */
abstract class Order extends \Magento\Backend\App\Action
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
     * @var Helper
     */
    protected $_helper;

    /**
     *
     * @var OrderRepositoryInterface
     */
    protected $_orderRepository;

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
     * @param Helper $helper
     * @param OrderRepositoryInterface $orderRepository
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param ApiClient $apiClient
     */
    public function __construct(Context $context, ForwardFactory $resultForwardFactory, FileFactory $fileFactory,
        Helper $helper, OrderRepositoryInterface $orderRepository,
        TransactionInfoRepositoryInterface $transactionInfoRepository, ApiClient $apiClient)
    {
        parent::__construct($context);
        $this->_resultForwardFactory = $resultForwardFactory;
        $this->_fileFactory = $fileFactory;
        $this->_helper = $helper;
        $this->_orderRepository = $orderRepository;
        $this->_transactionInfoRepository = $transactionInfoRepository;
        $this->_apiClient = $apiClient;
    }
}