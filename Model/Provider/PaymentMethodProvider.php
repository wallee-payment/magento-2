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
namespace Wallee\Payment\Model\Provider;

use Magento\Framework\Cache\FrontendInterface;
use Wallee\Payment\Model\ApiClient;
use Wallee\Sdk\Service\PaymentMethodService;

/**
 * Provider of payment method information from the gateway.
 */
class PaymentMethodProvider extends AbstractProvider
{

    /**
     *
     * @var ApiClient
     */
    private $apiClient;

    /**
     *
     * @param FrontendInterface $cache
     * @param ApiClient $apiClient
     */
    public function __construct(FrontendInterface $cache, ApiClient $apiClient)
    {
        parent::__construct(
            $cache,
            'wallee_payment_methods',
            \Wallee\Sdk\Model\PaymentMethod::class
        );
        $this->apiClient = $apiClient;
    }

    /**
     * Fetch payment methods from the API.
     *
     * @return mixed
     */
    protected function fetchData()
    {
        return $this->apiClient->getService(PaymentMethodService::class)->all();
    }

    /**
     * Get payment method ID from the given entry.
     *
     * @param \Wallee\Sdk\Model\PaymentMethod $entry
     * @return int
     */
    protected function getId($entry)
    {
        /** @var \Wallee\Sdk\Model\PaymentMethod $entry */
        return $entry->getId();
    }
}
