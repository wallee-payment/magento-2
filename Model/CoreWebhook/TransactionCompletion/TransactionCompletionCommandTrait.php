<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\CoreWebhook\TransactionCompletion;

use Magento\Sales\Model\Order;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\Sdk\Model\TransactionCompletion;
use Wallee\Sdk\Service\TransactionCompletionService;
use Wallee\Payment\Model\CoreWebhook\BaseOrderLookupTrait;

/**
 * A trait for reusable logic within transaction-completion related webhook commands.
 */
trait TransactionCompletionCommandTrait
{
    use BaseOrderLookupTrait;

    /**
     * Load transaction completion entity from the SDK.
     *
     * @return TransactionCompletion|null
     */
    protected function loadTransactionCompletion(): ?TransactionCompletion
    {
        /** @var SdkProvider $sdkProvider */
        $sdkProvider = $this->sdkProvider;
        /** @var TransactionCompletionService $completionService */
        $completionService = $sdkProvider->getService(TransactionCompletionService::class);

        try {
            $spaceId = $sdkProvider->getSpaceId();
            $completionId = $this->context->entityId;
            return $completionService->read($spaceId, $completionId);
        } catch (\Exception $e) {
            $this->logger->error(
                "Could not load SDK TransactionCompletion {$this->context->entityId}: " .
                $e->getMessage()
            );
            return null;
        }
    }

    /**
     * Find order linked to the given transaction completion.
     *
     * @param TransactionCompletion $completion
     * @return Order|null
     */
    protected function findOrderFromCompletion(TransactionCompletion $completion): ?Order
    {
        $transaction = $completion->getLineItemVersion()->getTransaction();
        if (!$transaction) {
            $this->logger->warning(
                "Could not get parent Transaction from TransactionCompletion {$completion->getId()}"
            );
            return null;
        }

        // Use the method from the base trait
        return $this->findOrderByTransactionId($transaction->getId());
    }
}
