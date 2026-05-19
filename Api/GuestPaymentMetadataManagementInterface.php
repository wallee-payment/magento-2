<?php
namespace Wallee\Payment\Api;

interface GuestPaymentMetadataManagementInterface
{
    /**
     * Get payment metadata for a guest checkout step
     *
     * @param string $cartId
     * @param string $methodCode
     * @return string JSON encoded metadata
     */
    public function getMetadata(string $cartId, string $methodCode): string;
}
