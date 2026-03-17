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
use Wallee\Sdk\Service\PaymentConnectorService;

/**
 * Provider of payment connector information from the gateway.
 */
class PaymentConnectorProvider extends AbstractProvider
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
            'wallee_payment_connectors',
            \Wallee\Sdk\Model\PaymentConnector::class
        );
        $this->apiClient = $apiClient;
    }

    /**
     * Fetch payment connectors from the API.
     *
     * @return mixed
     */
    protected function fetchData()
    {
        return $this->apiClient->getService(PaymentConnectorService::class)->all();
    }

    /**
     * Get connector ID from the given entry.
     *
     * @param \Wallee\Sdk\Model\PaymentConnector $entry
     * @return int
     */
    protected function getId($entry)
    {
        /** @var \Wallee\Sdk\Model\PaymentConnector $entry */
        return $entry->getId();
    }
}
