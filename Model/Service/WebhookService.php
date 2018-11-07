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
namespace Wallee\Payment\Model\Service;

use Magento\Framework\UrlInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Wallee\Payment\Helper\Data as Helper;
use Wallee\Payment\Model\ApiClient;
use Wallee\Payment\Model\Webhook\Entity;
use Wallee\Payment\Model\Webhook\ListenerPoolInterface;
use Wallee\Payment\Model\Webhook\Request;
use Wallee\Sdk\Model\CreationEntityState;
use Wallee\Sdk\Model\DeliveryIndicationState;
use Wallee\Sdk\Model\EntityQuery;
use Wallee\Sdk\Model\EntityQueryFilter;
use Wallee\Sdk\Model\EntityQueryFilterType;
use Wallee\Sdk\Model\ManualTaskState;
use Wallee\Sdk\Model\RefundState;
use Wallee\Sdk\Model\TokenVersionState;
use Wallee\Sdk\Model\TransactionCompletionState;
use Wallee\Sdk\Model\TransactionInvoiceState;
use Wallee\Sdk\Model\TransactionState;
use Wallee\Sdk\Model\WebhookListenerCreate;
use Wallee\Sdk\Model\WebhookUrl;
use Wallee\Sdk\Model\WebhookUrlCreate;
use Wallee\Sdk\Service\WebhookListenerService;
use Wallee\Sdk\Service\WebhookUrlService;

/**
 * Service to handler webhooks.
 */
class WebhookService
{

    /**
     *
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     *
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     *
     * @var UrlInterface
     */
    protected $_urlBuilder;

    /**
     *
     * @var Helper
     */
    protected $_helper;

    /**
     *
     * @var ListenerPoolInterface
     */
    protected $_webhookListenerPool;

    /**
     *
     * @var ApiClient
     */
    protected $_apiClient;

    /**
     *
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param UrlInterface $urlBuilder
     * @param Helper $helper
     * @param ListenerPoolInterface $webhookListenerPool
     * @param ApiClient $apiClient
     */
    public function __construct(StoreManagerInterface $storeManager, ScopeConfigInterface $scopeConfig,
        UrlInterface $urlBuilder, Helper $helper, ListenerPoolInterface $webhookListenerPool, ApiClient $apiClient)
    {
        $this->_storeManager = $storeManager;
        $this->_scopeConfig = $scopeConfig;
        $this->_urlBuilder = $urlBuilder;
        $this->_helper = $helper;
        $this->_webhookListenerPool = $webhookListenerPool;
        $this->_apiClient = $apiClient;
    }

    /**
     * Execute the webhook request.
     *
     * @param Request $request
     */
    public function execute(Request $request)
    {
        $this->_webhookListenerPool->get(strtolower($request->getListenerEntityTechnicalName()))
            ->execute($request);
    }

    /**
     * Installs the necessary webhooks in wallee.
     */
    public function install()
    {
        $spaceIds = [];
        foreach ($this->_storeManager->getWebsites() as $website) {
            $spaceId = $this->_scopeConfig->getValue('wallee_payment/general/space_id',
                ScopeInterface::SCOPE_WEBSITE, $website->getId());
            if ($spaceId && ! in_array($spaceId, $spaceIds)) {
                $webhookUrl = $this->getWebhookUrl($spaceId);
                if (! ($webhookUrl instanceof WebhookUrl)) {
                    $webhookUrl = $this->createWebhookUrl($spaceId);
                }

                $webhookListeners = $this->getWebhookListeners($spaceId, $webhookUrl);
                foreach ($this->getEntities() as $webhookEntity) {
                    if (! $this->isWebhookListenerExisting($webhookEntity, $webhookListeners)) {
                        $this->createWebhookListener($spaceId, $webhookEntity, $webhookUrl);
                    }
                }
            }
        }
    }

    /**
     * Gets whether a webhook listener already exists for the given entity.
     *
     * @param Entity $webhookEntity
     * @param \Wallee\Sdk\Model\WebhookListener[] $webhookListeners
     * @return boolean
     */
    protected function isWebhookListenerExisting(Entity $webhookEntity, array $webhookListeners)
    {
        foreach ($webhookListeners as $webhookListener) {
            if ($webhookListener->getEntity() == $webhookEntity->getId()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Creates a webhook listener.
     *
     * @param int $spaceId
     * @param Entity $webhookEntity
     * @param WebhookUrl $webhookUrl
     * @return \Wallee\Sdk\Model\WebhookListener
     */
    protected function createWebhookListener($spaceId, Entity $webhookEntity, WebhookUrl $webhookUrl)
    {
        $entity = new WebhookListenerCreate();
        $entity->setEntity($webhookEntity->getId());
        $entity->setEntityStates($webhookEntity->getStates());
        $entity->setName('Magento 2 ' . $webhookEntity->getName());
        $entity->setState(CreationEntityState::ACTIVE);
        $entity->setUrl($webhookUrl->getId());
        $entity->setNotifyEveryChange($webhookEntity->isNotifyEveryChange());
        return $this->_apiClient->getService(WebhookListenerService::class)->create($spaceId, $entity);
    }

    /**
     * Gets the existing webhook listeners.
     *
     * @param int $spaceId
     * @param WebhookUrl $webhookUrl
     * @return \Wallee\Sdk\Model\WebhookListener[]
     */
    protected function getWebhookListeners($spaceId, WebhookUrl $webhookUrl)
    {
        $query = new EntityQuery();
        $filter = new EntityQueryFilter();
        $filter->setType(EntityQueryFilterType::_AND);
        $filter->setChildren(
            array(
                $this->_helper->createEntityFilter('state', CreationEntityState::ACTIVE),
                $this->_helper->createEntityFilter('url.id', $webhookUrl->getId())
            ));
        $query->setFilter($filter);
        return $this->_apiClient->getService(WebhookListenerService::class)->search($spaceId, $query);
    }

    /**
     * Creates a webhook URL.
     *
     * @param int $spaceId
     * @return WebhookUrl
     */
    protected function createWebhookUrl($spaceId)
    {
        $entity = new WebhookUrlCreate();
        $entity->setUrl($this->getUrl());
        $entity->setState(CreationEntityState::ACTIVE);
        $entity->setName('Magento 2');
        return $this->_apiClient->getService(WebhookUrlService::class)->create($spaceId, $entity);
    }

    /**
     * Gets the existing webhook URL if existing.
     *
     * @param int $spaceId
     * @return WebhookUrl
     */
    protected function getWebhookUrl($spaceId)
    {
        $query = new EntityQuery();
        $query->setNumberOfEntities(1);
        $filter = new EntityQueryFilter();
        $filter->setType(EntityQueryFilterType::_AND);
        $filter->setChildren(
            array(
                $this->_helper->createEntityFilter('state', CreationEntityState::ACTIVE),
                $this->_helper->createEntityFilter('url', $this->getUrl())
            ));
        $query->setFilter($filter);
        $result = $this->_apiClient->getService(WebhookUrlService::class)->search($spaceId, $query);
        if (! empty($result)) {
            return \current($result);
        } else {
            return null;
        }
    }

    /**
     * Gets the webhook endpoint URL.
     *
     * @return string
     */
    protected function getUrl()
    {
        return $this->_urlBuilder->setScope($this->_storeManager->getDefaultStoreView())
            ->getUrl('wallee_payment/webhook/index',
            [
                '_secure' => true,
                '_nosid' => true
            ]);
    }

    /**
     * Gets the webhook entities that are required.
     *
     * @return Entity[]
     */
    protected function getEntities()
    {
        $listeners = [];

        $listeners[] = new Entity(1487165678181, 'Manual Task',
            [
                ManualTaskState::DONE,
                ManualTaskState::EXPIRED,
                ManualTaskState::OPEN
            ]);

        $listeners[] = new Entity(1472041857405, 'Payment Method Configuration',
            [
                CreationEntityState::ACTIVE,
                CreationEntityState::DELETED,
                CreationEntityState::DELETING,
                CreationEntityState::INACTIVE
            ], true);

        $listeners[] = new Entity(1472041829003, 'Transaction',
            [
                TransactionState::AUTHORIZED,
                TransactionState::DECLINE,
                TransactionState::FAILED,
                TransactionState::FULFILL,
                TransactionState::VOIDED,
                TransactionState::COMPLETED,
                TransactionState::PROCESSING,
                TransactionState::CONFIRMED
            ]);

        $listeners[] = new Entity(1472041819799, 'Delivery Indication',
            [
                DeliveryIndicationState::MANUAL_CHECK_REQUIRED
            ]);

        $listeners[] = new Entity(1472041831364, 'Transaction Completion',
            [
                TransactionCompletionState::FAILED
            ]);

        $listeners[] = new Entity(1472041816898, 'Transaction Invoice',
            [
                TransactionInvoiceState::NOT_APPLICABLE,
                TransactionInvoiceState::PAID,
                TransactionInvoiceState::DERECOGNIZED
            ]);

        $listeners[] = new Entity(1472041839405, 'Refund',
            [
                RefundState::FAILED,
                RefundState::SUCCESSFUL
            ]);

        $listeners[] = new Entity(1472041806455, 'Token',
            [
                CreationEntityState::ACTIVE,
                CreationEntityState::INACTIVE,
                CreationEntityState::DELETING,
                CreationEntityState::DELETED
            ]);

        $listeners[] = new Entity(1472041811051, 'Token Version',
            [
                TokenVersionState::ACTIVE,
                TokenVersionState::OBSOLETE
            ]);

        return $listeners;
    }
}