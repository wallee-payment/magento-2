<?php

namespace Wallee\Payment\Model;

use Wallee\Payment\Api\GuestPaymentMetadataManagementInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
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
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param PaymentMetadataManagementInterface $paymentMetadataManagement
     */
    public function __construct(
        QuoteIdMaskFactory $quoteIdMaskFactory,
        PaymentMetadataManagementInterface $paymentMetadataManagement
    ) {
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->paymentMetadataManagement = $paymentMetadataManagement;
    }

    /**
     * {@inheritDoc}
     */
    public function getMetadata(string $cartId, string $methodCode): string
    {
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        return $this->paymentMetadataManagement->getMetadata($quoteIdMask->getQuoteId(), $methodCode);
    }
}
