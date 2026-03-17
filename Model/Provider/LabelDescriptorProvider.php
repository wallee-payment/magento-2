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
use Wallee\Sdk\Service\LabelDescriptionService;

/**
 * Provider of label descriptor information from the gateway.
 */
class LabelDescriptorProvider extends AbstractProvider
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
            'wallee_payment_label_descriptors',
            \Wallee\Sdk\Model\LabelDescriptor::class
        );
        $this->apiClient = $apiClient;
    }

    /**
     * Fetch label descriptor ID from the API.
     *
     * @return mixed
     */
    protected function fetchData()
    {
        return $this->apiClient->getService(LabelDescriptionService::class)->all();
    }

    /**
     * Get label descriptor ID from the given entry.
     *
     * @param \Wallee\Sdk\Model\LabelDescriptor $entry
     * @return int
     */
    protected function getId($entry)
    {
        /** @var \Wallee\Sdk\Model\LabelDescriptor $entry */
        return $entry->getId();
    }
}
