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
namespace Wallee\Payment\Block\Method;

use Magento\Framework\Registry;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\Order\Payment;
use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Wallee\Payment\Helper\Data as Helper;
use Wallee\Payment\Helper\Document as DocumentHelper;
use Wallee\Payment\Helper\Locale as LocaleHelper;
use Wallee\Payment\Model\Provider\LabelDescriptorGroupProvider;
use Wallee\Payment\Model\Provider\LabelDescriptorProvider;
use Wallee\Sdk\Model\TransactionState;

/**
 * Block that renders the information about a payment.
 */
class Info extends \Magento\Payment\Block\Info
{

    /**
     *
     * @var PriceCurrencyInterface
     */
    private $priceCurrency;

    /**
     *
     * @var Registry
     */
    private $registry;

    /**
     *
     * @var Helper
     */
    private $helper;

    /**
     *
     * @var LocaleHelper
     */
    private $localeHelper;

    /**
     *
     * @var DocumentHelper
     */
    private $documentHelper;

    /**
     *
     * @var TransactionInfoRepositoryInterface
     */
    private $transactionInfoRepository;

    /**
     *
     * @var LabelDescriptorProvider
     */
    private $labelDescriptorProvider;

    /**
     *
     * @var LabelDescriptorGroupProvider
     */
    private $labelDescriptorGroupProvider;

    /**
     *
     * @var string
     */
    protected $_template = 'Wallee_Payment::payment/method/info.phtml';

    /**
     *
     * @var \Wallee\Payment\Model\TransactionInfo
     */
    private $transaction;

    /**
     *
     * @param Context $context
     * @param PriceCurrencyInterface $priceCurrency
     * @param Registry $registry
     * @param Helper $helper
     * @param LocaleHelper $localeHelper
     * @param DocumentHelper $documentHelper
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param LabelDescriptorProvider $labelDescriptorProvider
     * @param LabelDescriptorGroupProvider $labelDescriptorGroupProvider
     * @param array $data
     */
    public function __construct(Context $context, PriceCurrencyInterface $priceCurrency, Registry $registry,
        Helper $helper, LocaleHelper $localeHelper, DocumentHelper $documentHelper,
        TransactionInfoRepositoryInterface $transactionInfoRepository, LabelDescriptorProvider $labelDescriptorProvider,
        LabelDescriptorGroupProvider $labelDescriptorGroupProvider, array $data = [])
    {
        parent::__construct($context, $data);
        $this->priceCurrency = $priceCurrency;
        $this->registry = $registry;
        $this->helper = $helper;
        $this->localeHelper = $localeHelper;
        $this->documentHelper = $documentHelper;
        $this->transactionInfoRepository = $transactionInfoRepository;
        $this->labelDescriptorProvider = $labelDescriptorProvider;
        $this->labelDescriptorGroupProvider = $labelDescriptorGroupProvider;
    }

    /**
     * Gets whether the payment information are to be displayed in the creditmemo detail view in the backend.
     *
     * @return boolean
     */
    public function isCreditmemo()
    {
        return $this->helper->isAdminArea() && \strstr($this->getRequest()->getControllerName(), 'creditmemo') !== false;
    }

    /**
     * Gets whether the payment information are to be displayed in the invoice detail view in the backend.
     *
     * @return boolean
     */
    public function isInvoice()
    {
        return $this->helper->isAdminArea() && \strstr($this->getRequest()->getControllerName(), 'invoice') !== false;
    }

    /**
     * Gets whether the payment information are to be displayed in the shipment detail view in the backend.
     *
     * @return boolean
     */
    public function isShipment()
    {
        return $this->helper->isAdminArea() && \strstr($this->getRequest()->getControllerName(), 'shipment') !== false;
    }

    /**
     * Gets the URL to the payment method image.
     *
     * @return string
     */
    public function getImageUrl()
    {
        $transaction = $this->getTransaction();
        if ($transaction && $transaction->getImage()) {
            return $this->helper->getResourceUrl($transaction->getImage(), $transaction->getLanguage(),
                $transaction->getSpaceId(), $transaction->getSpaceViewId());
        }
    }

    /**
     * Gets the translated name of the transaction's state.
     *
     * @return string
     */
    public function getTransactionState()
    {
        if ($this->getTransaction()) {
            switch ($this->getTransaction()->getState()) {
                case TransactionState::AUTHORIZED:
                    return \__('Authorized');
                case TransactionState::COMPLETED:
                    return \__('Completed');
                case TransactionState::CONFIRMED:
                    return \__('Confirmed');
                case TransactionState::DECLINE:
                    return \__('Decline');
                case TransactionState::FAILED:
                    return \__('Failed');
                case TransactionState::FULFILL:
                    return \__('Fulfill');
                case TransactionState::PENDING:
                    return \__('Pending');
                case TransactionState::PROCESSING:
                    return \__('Processing');
                case TransactionState::VOIDED:
                    return \__('Voided');
                default:
                    return \__('Unknown State');
            }
        } else {
            return \__('Unknown State');
        }
    }

    /**
     * Formats the given amount for the transaction's currency.
     *
     * @param float $amount
     * @return number
     */
    public function formatAmount($amount)
    {
        //NULL was changed to 0 because PHP8.1 does not allow NULL as parameter
        return $this->priceCurrency->format($amount, 0, 0, 0, $this->getTransaction()
            ->getCurrency());
    }

    /**
     * Gets the URL to the transaction detail view in wallee.
     *
     * @return string
     */
    public function getTransactionUrl()
    {
        return \rtrim($this->_scopeConfig->getValue('wallee_payment/general/base_gateway_url'), '/') .
            '/s/' . $this->getTransaction()->getSpaceId() . '/payment/transaction/view/' .
            $this->getTransaction()->getTransactionId();
    }

    /**
     * Gets the URL to the customer detail view in wallee.
     *
     * @return string
     */
    public function getCustomerUrl()
    {
        return \rtrim($this->_scopeConfig->getValue('wallee_payment/general/base_gateway_url'), '/') .
        '/s/' . $this->getTransaction()->getSpaceId() . '/payment/customer/transaction/view/' .
        $this->getTransaction()->getTransactionId();
    }

    /**
     * Gets the transaction info or false if not available.
     *
     * @return \Wallee\Payment\Model\TransactionInfo|false
     */
    public function getTransaction()
    {
        if ($this->transaction === null) {
            $this->transaction = false;
            if ($this->getInfo() instanceof Payment) {
                /** @var Payment $payment */
                $payment = $this->getInfo();
                try {
                    $this->transaction = $this->transactionInfoRepository->getByOrderId($payment->getOrder()
                        ->getId());
                } catch (NoSuchEntityException $e) {}
            }
        }
        return $this->transaction;
    }

    /**
     * Gets the URL to download the transaction's invoice PDF document.
     *
     * @return string
     */
    public function getInvoiceDownloadUrl()
    {
        return $this->getUrl('wallee_payment/order/downloadInvoice',
            [
                'order_id' => $this->getTransaction()
                    ->getOrderId()
            ]);
    }

    /**
     * Gets the URL to download the transaction's packing slip PDF document.
     *
     * @return string
     */
    public function getPackingSlipDownloadUrl()
    {
        return $this->getUrl('wallee_payment/order/downloadPackingSlip',
            [
                'order_id' => $this->getTransaction()
                    ->getOrderId()
            ]);
    }

    /**
     * Gets the URL to download the transaction's refund PDF document.
     *
     * @return string
     */
    public function getRefundDownloadUrl()
    {
        return $this->getUrl('wallee_payment/order/downloadRefund',
            [
                'creditmemo_id' => $this->registry->registry('current_creditmemo')
                    ->getId()
            ]);
    }

    /**
     * Gets whether the user is allowed to download the transaction's invoice document.
     *
     * @return boolean
     */
    public function isInvoiceDownloadAllowed()
    {
        if ($this->getTransaction()) {
            $storeId = null;
            if ($this->getInfo() instanceof Payment) {
                /** @var Payment $payment */
                $payment = $this->getInfo();
                $storeId = $payment->getOrder()->getStoreId();
            }
            return $this->documentHelper->isInvoiceDownloadAllowed($this->getTransaction(), $storeId);
        } else {
            return false;
        }
    }

    /**
     * Gets whether the user is allowed to download the transaction's packing slip.
     *
     * @return boolean
     */
    public function isPackingSlipDownloadAllowed()
    {
        if ($this->getTransaction()) {
            $storeId = null;
            if ($this->getInfo() instanceof Payment) {
                /** @var Payment $payment */
                $payment = $this->getInfo();
                $storeId = $payment->getOrder()->getStoreId();
            }
            return $this->documentHelper->isPackingSlipDownloadAllowed($this->getTransaction(), $storeId);
        } else {
            return false;
        }
    }

    /**
     * Gets whether the user is allowed to download the transaction's refund document.
     *
     * @return boolean
     */
    public function isRefundDownloadAllowed()
    {
        $creditmemo = $this->registry->registry('current_creditmemo');
        if ($this->getTransaction() && $creditmemo != null && $creditmemo->getWalleeExternalId() != null) {
            $storeId = null;
            if ($this->getInfo() instanceof Payment) {
                /** @var Payment $payment */
                $payment = $this->getInfo();
                $storeId = $payment->getOrder()->getStoreId();
            }
            return $this->documentHelper->isRefundDownloadAllowed($this->getTransaction(), $storeId);
        } else {
            return false;
        }
    }

    /**
     * Gets the transaction's labels by their groups.
     *
     * @return LabelGroup[]
     */
    public function getGroupedLabels()
    {
        if ($this->getTransaction() && $this->getTransaction()->getLabels()) {
            $labelsByGroupId = [];
            foreach ($this->getTransaction()->getLabels() as $descriptorId => $value) {
                $descriptor = $this->labelDescriptorProvider->find($descriptorId);
                if ($descriptor) {
                    $labelsByGroupId[$descriptor->getGroup()][] = new Label($descriptor, $value);
                }
            }

            $labelsByGroup = [];
            foreach ($labelsByGroupId as $groupId => $labels) {
                $group = $this->labelDescriptorGroupProvider->find($groupId);
                if ($group) {
                    \usort($labels, function ($a, $b) {
                        return $a->getWeight() - $b->getWeight();
                    });
                    $labelsByGroup[] = new LabelGroup($group, $labels);
                }
            }

            \usort($labelsByGroup, function ($a, $b) {
                return $a->getWeight() - $b->getWeight();
            });
            return $labelsByGroup;
        } else {
            return [];
        }
    }

    /**
     * Gets the translation in the current language.
     *
     * @param array $translatedString
     * @return string|NULL
     */
    public function translate($translatedString)
    {
        return $this->localeHelper->translate($translatedString);
    }
}