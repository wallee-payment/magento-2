<?php
namespace Wallee\Payment\Api;

interface PaymentMetadataManagementInterface
{
    /**
     * Get payment metadata for a customer checkout step
     *
     * @param string $cartId
     * @param string $methodCode
     * @return string JSON encoded metadata
     */
    public function getMetadata(string $cartId, string $methodCode): string;
}
