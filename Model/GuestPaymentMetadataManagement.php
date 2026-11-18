<?php

namespace Wallee\Payment\Model;

use Wallee\Payment\Api\GuestPaymentMetadataManagementInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResource;
use Wallee\Payment\Api\PaymentMetadataManagementInterface;

class GuestPaymentMetadataManagement implements GuestPaymentMetadataManagementInterface
{
    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @var PaymentMetadataManagementInterface
     */
    private $paymentMetadataManagement;

    /**
     * @var QuoteIdMaskResource
     */
    private $quoteIdMaskResource;

    /**
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param PaymentMetadataManagementInterface $paymentMetadataManagement
     * @param QuoteIdMaskResource $quoteIdMaskResource
     */
    public function __construct(
        QuoteIdMaskFactory $quoteIdMaskFactory,
        PaymentMetadataManagementInterface $paymentMetadataManagement,
        QuoteIdMaskResource $quoteIdMaskResource
    ) {
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->paymentMetadataManagement = $paymentMetadataManagement;
        $this->quoteIdMaskResource = $quoteIdMaskResource;
    }

    /**
     * @inheritDoc
     */
    public function getMetadata(string $cartId, string $methodCode): string
    {
        $quoteIdMask = $this->quoteIdMaskFactory->create();
        $this->quoteIdMaskResource->load($quoteIdMask, $cartId, 'masked_id');
        return $this->paymentMetadataManagement->getMetadata($quoteIdMask->getQuoteId(), $methodCode);
    }
}
