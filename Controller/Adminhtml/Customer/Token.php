<?php
/**
 * wallee Magento 2
 *
 * This Magento 2 extension enables to process payments with wallee (https://www.wallee.com/).
 *
 * @package Wallee_Payment
 * @author wallee AG (http://www.wallee.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
namespace Wallee\Payment\Controller\Adminhtml\Customer;

use Magento\Backend\App\Action\Context;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\Address\Mapper;
use Magento\Framework\DataObjectFactory as ObjectFactory;
use Magento\Framework\Api\DataObjectHelper;
use Psr\Log\LoggerInterface;
use Wallee\Payment\Api\TokenInfoManagementInterface;
use Wallee\Payment\Api\TokenInfoRepositoryInterface;

/**
 * Backend controller action to delete wallee tokens.
 */
class Token extends \Magento\Customer\Controller\Adminhtml\Index
{

    /**
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     *
     * @var TokenInfoRepositoryInterface
     */
    private $tokenInfoRepository;

    /**
     *
     * @var TokenInfoManagementInterface
     */
    private $tokenInfoManagement;

    /**
     *
     * @param Context $context
     * @param LoggerInterface $logger
     * @param TokenInfoRepositoryInterface $tokenInfoRepository
     * @param TokenInfoManagementInterface $tokenInfoManagement
     */
    public function __construct(Context $context, \Magento\Framework\Registry $coreRegistry,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory,
        \Magento\Customer\Model\CustomerFactory $customerFactory, \Magento\Customer\Model\AddressFactory $addressFactory,
        \Magento\Customer\Model\Metadata\FormFactory $formFactory,
        \Magento\Newsletter\Model\SubscriberFactory $subscriberFactory, \Magento\Customer\Helper\View $viewHelper,
        \Magento\Framework\Math\Random $random, CustomerRepositoryInterface $customerRepository,
        \Magento\Framework\Api\ExtensibleDataObjectConverter $extensibleDataObjectConverter, Mapper $addressMapper,
        AccountManagementInterface $customerAccountManagement, AddressRepositoryInterface $addressRepository,
        CustomerInterfaceFactory $customerDataFactory, AddressInterfaceFactory $addressDataFactory,
        \Magento\Customer\Model\Customer\Mapper $customerMapper,
        \Magento\Framework\Reflection\DataObjectProcessor $dataObjectProcessor, DataObjectHelper $dataObjectHelper,
        ObjectFactory $objectFactory, \Magento\Framework\View\LayoutFactory $layoutFactory,
        \Magento\Framework\View\Result\LayoutFactory $resultLayoutFactory,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Backend\Model\View\Result\ForwardFactory $resultForwardFactory,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory, LoggerInterface $logger,
        TokenInfoRepositoryInterface $tokenInfoRepository, TokenInfoManagementInterface $tokenInfoManagement)
    {
        parent::__construct($context, $coreRegistry, $fileFactory, $customerFactory, $addressFactory, $formFactory,
            $subscriberFactory, $viewHelper, $random, $customerRepository, $extensibleDataObjectConverter,
            $addressMapper, $customerAccountManagement, $addressRepository, $customerDataFactory, $addressDataFactory,
            $customerMapper, $dataObjectProcessor, $dataObjectHelper, $objectFactory, $layoutFactory,
            $resultLayoutFactory, $resultPageFactory, $resultForwardFactory, $resultJsonFactory);
        $this->logger = $logger;
        $this->tokenInfoRepository = $tokenInfoRepository;
        $this->tokenInfoManagement = $tokenInfoManagement;
    }

    /**
     *
     * @return \Magento\Framework\View\Result\Layout
     */
    public function execute()
    {
        $customerId = $this->initCurrentCustomer();
        $tokenId = (int) $this->getRequest()->getParam('delete');
        if ($customerId && $tokenId) {
            try {
                /** @var \Wallee\Payment\Model\TokenInfo $token */
                $token = $this->tokenInfoRepository->get($tokenId);
                $this->tokenInfoManagement->deleteToken($token);
            } catch (\Exception $exception) {
                $this->logger->critical($exception);
            }
        }

        $resultLayout = $this->resultLayoutFactory->create();
        return $resultLayout;
    }
}