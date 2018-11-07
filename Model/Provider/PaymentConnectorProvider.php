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
use Wallee\Sdk\Service\PaymentConnectorService;

/**
 * Provider of payment connector information from the gateway.
 */
class PaymentConnectorProvider extends AbstractProvider
{

    /**
     *
     * @param FrontendInterface $cache
     * @param ApiClient $apiClient
     */
    public function __construct(FrontendInterface $cache, ApiClient $apiClient)
    {
        parent::__construct($cache, $apiClient, 'wallee_payment_connectors');
    }

    /**
     * Gets the payment connector by the given id.
     *
     * @param int $id
     * @return \Wallee\Sdk\Model\PaymentConnector
     */
    public function find($id)
    {
        return parent::find($id);
    }

    /**
     * Gets a list of payment connectors.
     *
     * @return \Wallee\Sdk\Model\PaymentConnector[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        return $this->_apiClient->getService(PaymentConnectorService::class)->all();
    }

    protected function getId($entry)
    {
        /** @var \Wallee\Sdk\Model\PaymentConnector $entry */
        return $entry->getId();
    }
}