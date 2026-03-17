<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\CoreWebhook;

use Wallee\PluginCore\Webhook\DefaultWebhookLifecycleHandler as PluginCoreDefaultHandler;
use Wallee\PluginCore\Webhook\Enum\WebhookListener;
use Wallee\PluginCore\Webhook\Exception\SkippedStepException;
use Wallee\PluginCore\Webhook\WebhookContext;
use Wallee\PluginCore\Webhook\StateValidator;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * The base lifecycle handler for Magento.
 * It implements the platform-specifics for locking and DB persistence.
 */
class DefaultWebhookLifecycleHandler extends PluginCoreDefaultHandler
{
    protected const WEBHOOK_PROGRESS_TABLE = 'wallee_webhook_progress';
    private const MAX_LOCK_ATTEMPTS = 5;
    private const LOCK_WAIT_TIME = 500000; // 0.5 seconds

    /**
     *
     * @var \Magento\Framework\DB\Adapter\AdapterInterface
     */
    protected \Magento\Framework\DB\Adapter\AdapterInterface $dbConnection;

    /**
     *
     * @param ResourceConnection $resource
     * @param LoggerInterface $logger
     * @param LockManagerInterface $lockManager
     */
    public function __construct(
        protected readonly ResourceConnection $resource,
        protected readonly LoggerInterface $logger,
        protected readonly LockManagerInterface $lockManager
    ) {
        $this->dbConnection = $this->resource->getConnection('default');
    }

    /**
     * Get last processed state for the given webhook entity.
     *
     * @param WebhookListener $listener
     * @param int $entityId
     * @return string
     */
    public function getLastProcessedState(WebhookListener $listener, int $entityId): string
    {
        $select = $this->dbConnection->select()
            ->from($this->resource->getTableName(self::WEBHOOK_PROGRESS_TABLE), ['last_processed_state'])
            ->where('entity_id = ?', $entityId)
            ->where('entity_type = ?', $listener->getTechnicalName());

        $lastProcessedState = $this->dbConnection->fetchOne($select);

        if ($lastProcessedState !== false) {
            return $lastProcessedState;
        }

        $this->logger->debug(
            "Webhook progress not found for {$listener->getTechnicalName()} {$entityId}. " .
            "Returning default initial state."
        );
        return $this->findDefaultInitialState($listener);
    }

    /**
     * Define WHAT to lock.
     *
     * By default, we lock the specific webhook entity to prevent duplicate processing.
     *
     * @param WebhookListener $listener
     * @param WebhookContext $context
     * @return array
     */
    public function getLockableResources(WebhookListener $listener, WebhookContext $context): array
    {
        return ['wallee_webhook_' . $listener->getTechnicalName() . '_' . $context->entityId];
    }

    /**
     * Implement HOW to lock using Magento's LockManager.
     *
     * Includes retry logic.
     *
     * @param string $resourceId
     * @return void
     */
    protected function doAcquireLock(string $resourceId): void
    {
        $this->logger->debug("Locking: Requesting lock for ID: {$resourceId}");
        $this->acquireLockWithRetry($resourceId, 0);
    }

    /**
     * Implement HOW to unlock.
     *
     * @param string $resourceId
     * @return void
     */
    protected function doReleaseLock(string $resourceId): void
    {
        $this->logger->debug("Locking: Releasing lock for ID: {$resourceId}");
        $this->lockManager->unlock($resourceId);
    }

    /**
     * Overridden to add Database Transaction logic and Race Condition check.
     *
     * @param WebhookListener $listener
     * @param WebhookContext $context
     * @return bool
     */
    public function preProcess(WebhookListener $listener, WebhookContext $context): bool
    {
        // 1. Call Parent to acquire locks (calls doAcquireLock internally)
        parent::preProcess($listener, $context);

        // 2. Race Condition Check (must happen AFTER locking)
        $trueLastState = $this->getLastProcessedState($listener, $context->entityId);
        $validator = new StateValidator();
        $path = $validator->getTransitionPath($listener, $trueLastState, $context->remoteState);

        if (empty($path)) {
            $this->logger->debug(
                "Race condition detected: State is already {$trueLastState}. " .
                "Skipping step {$context->remoteState}."
            );
            // Release locks immediately since we are stopping
            $this->onFailure($listener, $context, new SkippedStepException("Skipping due to race condition"));
            return false;
        }

        // 3. Start Database Transaction
        $this->dbConnection->beginTransaction();

        return true;
    }

    /**
     * Overridden to add Database Commit and Table Update logic.
     *
     * @param WebhookListener $listener
     * @param WebhookContext $context
     * @param mixed $commandResult
     * @return void
     */
    public function postProcess(WebhookListener $listener, WebhookContext $context, mixed $commandResult): void
    {
        // 1. Update Webhook Progress Table
        $data = [
            'entity_id' => $context->entityId,
            'entity_type' => $listener->getTechnicalName(),
            'last_processed_state' => $context->remoteState,
        ];
        $this->dbConnection->insertOnDuplicate(
            $this->resource->getTableName(self::WEBHOOK_PROGRESS_TABLE),
            $data,
            ['last_processed_state']
        );

        // 2. Commit Database Transaction
        $this->dbConnection->commit();

        // 3. Call Parent to release locks (calls doReleaseLock internally)
        parent::postProcess($listener, $context, $commandResult);
    }

    /**
     * Overridden to add Database Rollback logic.
     *
     * @param WebhookListener $listener
     * @param WebhookContext $context
     * @param \Throwable $exception
     * @return void
     */
    public function onFailure(WebhookListener $listener, WebhookContext $context, \Throwable $exception): void
    {
        if ($exception instanceof SkippedStepException) {
            $this->logger->debug(
                "Skipping webhook step for " .
                " {$listener->getTechnicalName()}/{$context->entityId}: {$exception->getMessage()}"
            );
        } else {
            $this->logger->error(
                "Webhook processing failed for {$listener->getTechnicalName()} {$context->entityId}. Rolling back.",
                ['exception' => $exception->getMessage()]
            );
        }

        // 1. Rollback Database Transaction (safely)
        if ($this->dbConnection->getTransactionLevel() > 0) {
            $this->dbConnection->rollBack();
        }

        // 2. Call Parent to release locks
        parent::onFailure($listener, $context, $exception);
    }

    // --- Private helper for the recursive retry logic ---

    /**
     * Acquire lock with retry mechanism.
     *
     * @param string $lockId
     * @param int $attempt
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function acquireLockWithRetry(string $lockId, int $attempt): void
    {
        if ($attempt >= self::MAX_LOCK_ATTEMPTS) {
            throw new LocalizedException(
                \__(
                    'Wallee Webhook: Max lock wait attempts reached for lock ID: %1',
                    $lockId
                )
            );
        }

        if ($this->lockManager->isLocked($lockId)) {
            $this->logger->debug("Locking: Waiting for lock on ID: {$lockId}...");
            usleep(self::LOCK_WAIT_TIME);
            $this->acquireLockWithRetry($lockId, $attempt + 1);
            return;
        }

        if (!$this->lockManager->lock($lockId, 1)) {
            $this->acquireLockWithRetry($lockId, $attempt + 1);
            return;
        }
        $this->logger->debug("Locking: Lock acquired for ID: {$lockId}");
    }
}
