<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\CoreWebhook;

use Wallee\PluginCore\Webhook\Enum\WebhookListener;
use Wallee\PluginCore\Webhook\WebhookContext;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Lock\LockManagerInterface;
use Psr\Log\LoggerInterface;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Model\Order;
use Wallee\Payment\Api\Data\TransactionInfoInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;

/**
 * Abstract handler for order-related webhooks.
 * * RESPONSIBILITIES:
 * 1. Load Data (SDK Entity & Magento Order).
 * 2. Define Resources to Lock (Entity + Order).
 * 3. Run specific post-processing business logic.
 */
abstract class BaseOrderLifecycleHandler extends DefaultWebhookLifecycleHandler
{

    /**
     *
     * @var object|null
     */
    protected ?object $sdkEntity = null;

    /**
     *
     * @var Order|null
     */
    protected ?Order $order = null;

    /**
     *
     * @var WebhookContext|null
     */
    protected ?WebhookContext $context = null;

    /**
     *
     * @param ResourceConnection $resource
     * @param LoggerInterface $logger
     * @param LockManagerInterface $lockManager
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SdkProvider $sdkProvider
     */
    public function __construct(
        ResourceConnection $resource,
        LoggerInterface $logger,
        LockManagerInterface $lockManager,
        protected readonly TransactionInfoRepositoryInterface $transactionInfoRepository,
        protected readonly OrderRepositoryInterface $orderRepository,
        protected readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        protected readonly SdkProvider $sdkProvider
    ) {
        parent::__construct($resource, $logger, $lockManager);
    }

    /**
     * 1. Pre-loads data so we know WHICH order to lock.
     * 2. Calls parent to execute the locking loop and start DB transaction.
     *
     * @param WebhookListener $listener
     * @param WebhookContext $context
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function preProcess(WebhookListener $listener, WebhookContext $context): bool
    {
        $this->context = $context;

        // Load data FIRST so we can identify the Order ID
        $this->sdkEntity = $this->loadSdkEntity($listener, $context);
        if (!$this->sdkEntity) {
            throw new NoSuchEntityException(
                \__(
                    'Could not load SDK entity for: %1 %2',
                    $listener->getTechnicalName(),
                    $context->entityId,
                )
            );
        }

        $this->order = $this->findOrder($this->sdkEntity);
        if (!$this->order) {
            throw new NoSuchEntityException(
                \__(
                    'Could not load Magento Order for %1 %s2',
                    $listener->getTechnicalName(),
                    $context->entityId,
                )
            );
        }

        // Call Parent.
        // Parent calls $this->getLockableResources(), locks everything (Entity + Order),
        // re-checks state, and starts the DB transaction.
        return parent::preProcess($listener, $context);
    }

    /**
     * Adds the Order ID to the list of locks. The parent class handles the actual locking mechanics.
     *
     * @param WebhookListener $listener
     * @param WebhookContext $context
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getLockableResources(WebhookListener $listener, WebhookContext $context): array
    {
        // Get the standard entity lock (from Default handler)
        $locks = parent::getLockableResources($listener, $context);

        // Add the Order Lock
        // Note: $this->order was loaded in preProcess() just before this is called.
        $orderId = $this->getOrderId($context);
        if ($orderId) {
            $locks[] = 'wallee_order_update_' . $orderId;
        } else {
            // If we can't find the Order, we MUST NOT proceed, because we can't lock it.
            // Throwing an exception here forces a retry later, when the Transaction webhook
            // has likely finished creating the local data.
            throw new LocalizedException(
                \__(
                    "Locking Error: Could not determine Order ID for %1 %2. Cannot acquire Order Lock.",
                    $listener->getTechnicalName(),
                    $context->entityId
                )
            );
        }

        return $locks;
    }

    /**
     * 1. Runs specific business logic.
     * 2. Calls parent to commit DB and release locks.
     * 3. Sends email.
     *
     * @param WebhookListener $listener
     * @param WebhookContext $context
     * @param mixed $commandResult
     * @return void
     */
    public function postProcess(WebhookListener $listener, WebhookContext $context, mixed $commandResult): void
    {
        // Run specific logic (e.g. Update Transaction Info)
        $this->doPostProcess($this->sdkEntity, $this->order, $commandResult);

        // Call Parent: Updates progress table, Commits DB, Releases ALL locks
        parent::postProcess($listener, $context, $commandResult);

        // Send email (after commit/unlock)
        if ($commandResult instanceof Order) {
            $this->doSendEmail($commandResult);
        }
    }

    /**
     * We do NOT override onFailure().
     * The parent DefaultWebhookLifecycleHandler already handles:
     * 1. Rolling back the DB transaction.
     * 2. Releasing ALL locks defined in getLockableResources().
     */

    // --- Helper Methods ---

    /**
     * Finds transaction info by transaction ID.
     *
     * @param int $transactionId
     * @return TransactionInfoInterface|null
     */
    protected function findTransactionInfoByTransactionId(int $transactionId): ?TransactionInfoInterface
    {
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('transaction_id', $transactionId)->create();
        $items = $this->transactionInfoRepository->getList($searchCriteria)->getItems();
        return empty($items) ? null : array_shift($items);
    }

    // --- Abstract Methods ---

    /**
     * Load SDK entity for the given webhook context.
     *
     * @param WebhookListener $listener
     * @param WebhookContext $context
     * @return object|null
     */
    abstract protected function loadSdkEntity(WebhookListener $listener, WebhookContext $context): ?object;

    /**
     * Find order linked to the given entity.
     *
     * @param object $entity
     * @return Order|null
     */
    abstract protected function findOrder(object $entity): ?Order;

    /**
     * Get order ID for the current webhook context.
     *
     * @param WebhookContext $context
     * @return int|null
     */
    abstract protected function getOrderId(WebhookContext $context): ?int;

    /**
     * Run post-processing after command execution.
     *
     * @param object|null $entity
     * @param Order|null $order
     * @param mixed $commandResult
     * @return void
     */
    abstract protected function doPostProcess(?object $entity, ?Order $order, mixed $commandResult): void;

    /**
     * Send order email if enabled and not already sent.
     *
     * @param Order $order
     * @return void
     */
    abstract protected function doSendEmail(Order $order): void;
}
