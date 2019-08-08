<?php
/**
 * wallee Magento 2
 *
 * This Magento 2 extension enables to process payments with wallee (https://www.wallee.com/).
 *
 * @package Wallee_Payment
 * @author customweb GmbH (http://www.customweb.com/)
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
        parent::__construct($cache, 'wallee_payment_label_descriptor_groups',
            \Wallee\Sdk\Model\LabelDescriptorGroup::class);
        $this->apiClient = $apiClient;
    }

    /**
     * Gets the label descriptor group by the given id.
     *
     * @param int $id
     * @return \Wallee\Sdk\Model\LabelDescriptorGroup
     */
    public function find($id)
    {
        return parent::find($id);
    }

    /**
     * Gets a list of label descriptor groups.
     *
     * @return \Wallee\Sdk\Model\LabelDescriptorGroup[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        return $this->apiClient->getService(LabelDescriptionGroupService::class)->all();
    }

    protected function getId($entry)
    {
        /** @var \Wallee\Sdk\Model\LabelDescriptorGroup $entry */
        return $entry->getId();
    }
}