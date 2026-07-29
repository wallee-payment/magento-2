<?php

declare(strict_types=1);

namespace Wallee\Payment\Model\CoreWebhook;

use Magento\Framework\ObjectManagerInterface;

use Wallee\PluginCore\Webhook\WebhookProcessor;
use Wallee\PluginCore\Webhook\Listener\WebhookListenerRegistry;
use Wallee\PluginCore\Webhook\Enum\WebhookListener;

use Wallee\Payment\Model\CoreWebhook\DeliveryIndication\ManualCheckRequiredListener;
use Wallee\Payment\Model\CoreWebhook\ManualTask\UpdateListener as ManualTaskUpdateListener;
use Wallee\Payment\Model\CoreWebhook\PaymentMethodConfiguration\SynchronizeListener;
use Wallee\Payment\Model\CoreWebhook\Refund\FailedListener as RefundFailedListener;
use Wallee\Payment\Model\CoreWebhook\Refund\SuccessfulListener as RefundSuccessfulListener;
use Wallee\Payment\Model\CoreWebhook\Token\UpdateTokenListener;
use Wallee\Payment\Model\CoreWebhook\TokenVersion\UpdateTokenVersionListener;
use Wallee\Payment\Model\CoreWebhook\Transaction\AuthorizedListener;
use Wallee\Payment\Model\CoreWebhook\Transaction\FailedListener;
use Wallee\Payment\Model\CoreWebhook\Transaction\FulfillListener;
use Wallee\Payment\Model\CoreWebhook\Transaction\VoidedListener;
use Wallee\Payment\Model\CoreWebhook\TransactionCompletion\FailedListener
    as TransactionCompletionFailedListener;
use Wallee\Payment\Model\CoreWebhook\TransactionInvoice\CaptureListener;

use Wallee\PluginCore\DeliveryIndication\State as CoreDeliveryIndicationState;
use Wallee\PluginCore\ManualTask\State as CoreManualTaskState;
use Wallee\PluginCore\PaymentMethod\State as CorePaymentMethodConfigurationState;
use Wallee\PluginCore\Refund\State as CoreRefundState;
use Wallee\PluginCore\Token\State as CoreTokenState;
use Wallee\PluginCore\Token\Version\State as CoreTokenVersionState;
use Wallee\PluginCore\Transaction\State as CoreTransactionState;
use Wallee\PluginCore\Transaction\Completion\State as CoreTransactionCompletionState;
use Wallee\PluginCore\Transaction\Invoice\State as CoreTransactionInvoiceState;

/**
 * Configures the WebhookListenerRegistry by adding all Magento listeners.
 */
class RegistryConfigurer
{

    /**
     *
     * @param ObjectManagerInterface $objectManager
     * @param WebhookProcessor $webhookProcessor
     */
    public function __construct(
        private readonly ObjectManagerInterface $objectManager,
        private readonly WebhookProcessor $webhookProcessor,
    ) {
    }

    /**
     * Adds all necessary listeners to the registry. Call this once before processing webhooks.
     *
     * @return void
     */
    public function configure(): void
    {
        // Get the registry instance directly from the processor
        $registry = $this->webhookProcessor->getListenerRegistry();

        $registry->addListener(
            WebhookListener::TRANSACTION,
            CoreTransactionState::FAILED->value,
            $this->objectManager->create(FailedListener::class)
        );
        $registry->addListener(
            WebhookListener::TRANSACTION,
            CoreTransactionState::AUTHORIZED->value,
            $this->objectManager->create(AuthorizedListener::class)
        );
        $registry->addListener(
            WebhookListener::TRANSACTION,
            CoreTransactionState::FULFILL->value,
            $this->objectManager->create(FulfillListener::class)
        );
        $registry->addListener(
            WebhookListener::TRANSACTION,
            CoreTransactionState::VOIDED->value,
            $this->objectManager->create(VoidedListener::class)
        );

        $registry->addListener(
            WebhookListener::TRANSACTION_COMPLETION,
            CoreTransactionCompletionState::FAILED->value,
            $this->objectManager->create(TransactionCompletionFailedListener::class)
        );

        $registry->addListener(
            WebhookListener::TRANSACTION_INVOICE,
            CoreTransactionInvoiceState::PAID->value,
            $this->objectManager->create(CaptureListener::class),
        );
        $registry->addListener(
            WebhookListener::TRANSACTION_INVOICE,
            CoreTransactionInvoiceState::NOT_APPLICABLE->value,
            $this->objectManager->create(CaptureListener::class),
        );

        $registry->addListener(
            WebhookListener::REFUND,
            CoreRefundState::FAILED->value,
            $this->objectManager->create(RefundFailedListener::class)
        );
        $registry->addListener(
            WebhookListener::REFUND,
            CoreRefundState::SUCCESSFUL->value,
            $this->objectManager->create(RefundSuccessfulListener::class)
        );

        $registry->addListener(
            WebhookListener::DELIVERY_INDICATION,
            CoreDeliveryIndicationState::MANUAL_CHECK_REQUIRED->value,
            $this->objectManager->create(ManualCheckRequiredListener::class)
        );

        $registry->addListener(
            WebhookListener::MANUAL_TASK,
            CoreManualTaskState::OPEN->value,
            $this->objectManager->create(ManualTaskUpdateListener::class)
        );
        $registry->addListener(
            WebhookListener::MANUAL_TASK,
            CoreManualTaskState::DONE->value,
            $this->objectManager->create(ManualTaskUpdateListener::class)
        );
        $registry->addListener(
            WebhookListener::MANUAL_TASK,
            CoreManualTaskState::EXPIRED->value,
            $this->objectManager->create(ManualTaskUpdateListener::class)
        );

        $registry->addListener(
            WebhookListener::PAYMENT_METHOD_CONFIGURATION,
            CorePaymentMethodConfigurationState::ACTIVE->value,
            $this->objectManager->create(SynchronizeListener::class)
        );
        $registry->addListener(
            WebhookListener::PAYMENT_METHOD_CONFIGURATION,
            CorePaymentMethodConfigurationState::INACTIVE->value,
            $this->objectManager->create(SynchronizeListener::class)
        );
        $registry->addListener(
            WebhookListener::PAYMENT_METHOD_CONFIGURATION,
            CorePaymentMethodConfigurationState::DELETING->value,
            $this->objectManager->create(SynchronizeListener::class)
        );
        $registry->addListener(
            WebhookListener::PAYMENT_METHOD_CONFIGURATION,
            CorePaymentMethodConfigurationState::DELETED->value,
            $this->objectManager->create(SynchronizeListener::class)
        );
        // Payment method changes do not flow through state transitions; we want to be notified
        // on every change so the local payment method cache stays in sync with the portal.
        $registry->setNotifyEveryChange(WebhookListener::PAYMENT_METHOD_CONFIGURATION, true);

        $registry->addListener(
            WebhookListener::TOKEN,
            CoreTokenState::ACTIVE->value,
            $this->objectManager->create(UpdateTokenListener::class)
        );
        $registry->addListener(
            WebhookListener::TOKEN,
            CoreTokenState::INACTIVE->value,
            $this->objectManager->create(UpdateTokenListener::class)
        );
        $registry->addListener(
            WebhookListener::TOKEN,
            CoreTokenState::DELETING->value,
            $this->objectManager->create(UpdateTokenListener::class)
        );
        $registry->addListener(
            WebhookListener::TOKEN,
            CoreTokenState::DELETED->value,
            $this->objectManager->create(UpdateTokenListener::class)
        );

        $registry->addListener(
            WebhookListener::TOKEN_VERSION,
            CoreTokenVersionState::ACTIVE->value,
            $this->objectManager->create(UpdateTokenVersionListener::class)
        );
        $registry->addListener(
            WebhookListener::TOKEN_VERSION,
            CoreTokenVersionState::OBSOLETE->value,
            $this->objectManager->create(UpdateTokenVersionListener::class)
        );
    }
}
