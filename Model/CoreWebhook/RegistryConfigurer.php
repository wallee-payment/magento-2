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

use Wallee\PluginCore\DeliveryIndication\State as DeliveryIndicationState;
use Wallee\PluginCore\ManualTask\State as ManualTaskState;
use Wallee\PluginCore\PaymentMethod\State as PaymentMethodConfigurationState;
use Wallee\PluginCore\Refund\State as RefundState;
use Wallee\PluginCore\Token\State as TokenState;
use Wallee\PluginCore\Token\Version\State as TokenVersionState;
use Wallee\PluginCore\Transaction\State as TransactionState;
use Wallee\PluginCore\Transaction\Completion\State as TransactionCompletionState;
use Wallee\PluginCore\Transaction\Invoice\State as TransactionInvoiceState;

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
            TransactionState::FAILED->value,
            $this->objectManager->create(FailedListener::class)
        );
        $registry->addListener(
            WebhookListener::TRANSACTION,
            TransactionState::AUTHORIZED->value,
            $this->objectManager->create(AuthorizedListener::class)
        );
        $registry->addListener(
            WebhookListener::TRANSACTION,
            TransactionState::FULFILL->value,
            $this->objectManager->create(FulfillListener::class)
        );
        $registry->addListener(
            WebhookListener::TRANSACTION,
            TransactionState::VOIDED->value,
            $this->objectManager->create(VoidedListener::class)
        );

        $registry->addListener(
            WebhookListener::TRANSACTION_COMPLETION,
            TransactionCompletionState::FAILED->value,
            $this->objectManager->create(TransactionCompletionFailedListener::class)
        );

        $registry->addListener(
            WebhookListener::TRANSACTION_INVOICE,
            TransactionInvoiceState::PAID->value,
            $this->objectManager->create(CaptureListener::class),
        );
        $registry->addListener(
            WebhookListener::TRANSACTION_INVOICE,
            TransactionInvoiceState::NOT_APPLICABLE->value,
            $this->objectManager->create(CaptureListener::class),
        );

        $registry->addListener(
            WebhookListener::REFUND,
            RefundState::FAILED->value,
            $this->objectManager->create(RefundFailedListener::class)
        );
        $registry->addListener(
            WebhookListener::REFUND,
            RefundState::SUCCESSFUL->value,
            $this->objectManager->create(RefundSuccessfulListener::class)
        );

        $registry->addListener(
            WebhookListener::DELIVERY_INDICATION,
            DeliveryIndicationState::MANUAL_CHECK_REQUIRED->value,
            $this->objectManager->create(ManualCheckRequiredListener::class)
        );

        $registry->addListener(
            WebhookListener::MANUAL_TASK,
            ManualTaskState::OPEN->value,
            $this->objectManager->create(ManualTaskUpdateListener::class)
        );
        $registry->addListener(
            WebhookListener::MANUAL_TASK,
            ManualTaskState::DONE->value,
            $this->objectManager->create(ManualTaskUpdateListener::class)
        );
        $registry->addListener(
            WebhookListener::MANUAL_TASK,
            ManualTaskState::EXPIRED->value,
            $this->objectManager->create(ManualTaskUpdateListener::class)
        );

        $registry->addListener(
            WebhookListener::PAYMENT_METHOD_CONFIGURATION,
            PaymentMethodConfigurationState::ACTIVE->value,
            $this->objectManager->create(SynchronizeListener::class)
        );
        $registry->addListener(
            WebhookListener::PAYMENT_METHOD_CONFIGURATION,
            PaymentMethodConfigurationState::INACTIVE->value,
            $this->objectManager->create(SynchronizeListener::class)
        );
        $registry->addListener(
            WebhookListener::PAYMENT_METHOD_CONFIGURATION,
            PaymentMethodConfigurationState::DELETING->value,
            $this->objectManager->create(SynchronizeListener::class)
        );
        $registry->addListener(
            WebhookListener::PAYMENT_METHOD_CONFIGURATION,
            PaymentMethodConfigurationState::DELETED->value,
            $this->objectManager->create(SynchronizeListener::class)
        );

        $registry->addListener(
            WebhookListener::TOKEN,
            TokenState::ACTIVE->value,
            $this->objectManager->create(UpdateTokenListener::class)
        );
        $registry->addListener(
            WebhookListener::TOKEN,
            TokenState::INACTIVE->value,
            $this->objectManager->create(UpdateTokenListener::class)
        );
        $registry->addListener(
            WebhookListener::TOKEN,
            TokenState::DELETING->value,
            $this->objectManager->create(UpdateTokenListener::class)
        );
        $registry->addListener(
            WebhookListener::TOKEN,
            TokenState::DELETED->value,
            $this->objectManager->create(UpdateTokenListener::class)
        );

        $registry->addListener(
            WebhookListener::TOKEN_VERSION,
            TokenVersionState::ACTIVE->value,
            $this->objectManager->create(UpdateTokenVersionListener::class)
        );
        $registry->addListener(
            WebhookListener::TOKEN_VERSION,
            TokenVersionState::OBSOLETE->value,
            $this->objectManager->create(UpdateTokenVersionListener::class)
        );
    }
}
