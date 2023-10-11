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
        parent::__construct($cache, 'wallee_payment_label_descriptors',
            \Wallee\Sdk\Model\LabelDescriptor::class);
        $this->apiClient = $apiClient;
    }

    /**
     * Gets the label descriptor by the given id.
     *
     * @param string $id
     * @return \Wallee\Sdk\Model\LabelDescriptor
     */
    public function find($id)
    {
        return parent::find($id);
    }

    /**
     * Gets a list of label descriptors.
     *
     * @return \Wallee\Sdk\Model\LabelDescriptor[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    /**
     * @return mixed
     */
    protected function fetchData()
    {
        return $this->apiClient->getService(LabelDescriptionService::class)->all();
    }

    protected function getId($entry)
    {
        /** @var \Wallee\Sdk\Model\LabelDescriptor $entry */
        return $entry->getId();
    }
}