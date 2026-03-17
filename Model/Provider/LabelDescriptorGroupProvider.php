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
use Wallee\Sdk\Service\LabelDescriptionGroupService;

/**
 * Provider of label descriptor group information from the gateway.
 */
class LabelDescriptorGroupProvider extends AbstractProvider
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
            'wallee_payment_label_descriptor_groups',
            \Wallee\Sdk\Model\LabelDescriptorGroup::class
        );
        $this->apiClient = $apiClient;
    }

    /**
     * Fetch label descriptor groups from the API.
     *
     * @return mixed
     */
    protected function fetchData()
    {
        return $this->apiClient->getService(LabelDescriptionGroupService::class)->all();
    }

    /**
     * Get label descriptor group ID from the given entry.
     *
     * @param \Wallee\Sdk\Model\LabelDescriptorGroup $entry
     * @return int
     */
    protected function getId($entry)
    {
        /** @var \Wallee\Sdk\Model\LabelDescriptorGroup $entry */
        return $entry->getId();
    }
}
