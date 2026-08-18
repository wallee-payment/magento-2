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
namespace Wallee\Payment\Controller\Webhook;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\Exception\NotFoundException;
use Wallee\PluginCore\SharedKernel\AbstractDomainException;
use Wallee\PluginCore\Webhook\WebhookProcessor;
use Wallee\Payment\Model\Webhook\RegistryConfigurer;
use Wallee\PluginCore\Http\Request as CoreRequest;
use Wallee\PluginCore\Log\LoggerInterface;

/**
 * Frontend controller action to proces webhook requests.
 */
class Index extends \Wallee\Payment\Controller\Webhook implements CsrfAwareActionInterface
{

    /**
     *
     * @var WebhookProcessor
     */
    private WebhookProcessor $webhookProcessor;

    /**
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Context $context The context object.
     * @param WebhookProcessor $webhookProcessor The webhook processor.
     * @param RegistryConfigurer $registryConfigurer The registry configurer.
     * @param LoggerInterface $logger The logger instance.
     */
    public function __construct(
        Context $context,
        WebhookProcessor $webhookProcessor,
        private readonly RegistryConfigurer $registryConfigurer,
        LoggerInterface $logger,
    ) {
        parent::__construct($context);
        $this->webhookProcessor = $webhookProcessor;
        $this->logger = $logger;
    }

    /**
     * Handle the incoming webhook request and set the HTTP response code.
     *
     * @return void
     */
    public function execute()
    {
        http_response_code(500);
        $this->getResponse()->setHttpResponseCode(500);
        try {
            $pluginCoreRequest = CoreRequest::fromMagentoRequest($this->getRequest());
            $this->logger->debug('Webhook received.', [
                'spaceId' => $pluginCoreRequest->get('spaceId'),
                'entityId' => $pluginCoreRequest->get('entityId'),
                'listenerEntityTechnicalName' => $pluginCoreRequest->get('listenerEntityTechnicalName'),
            ]);
            $this->registryConfigurer->configure();
            $this->webhookProcessor->process($pluginCoreRequest);
        } catch (\Throwable $e) {
            $retryableCause = $this->findRetryableCause($e);
            if ($retryableCause !== null) {
                // Expected, self-healing condition (e.g. a cross-entity ordering race) that the
                // portal's own webhook retry will resolve — not an operator-facing error. Log the
                // specific reason as plain text rather than the raw exception object: our backend
                // renders a Throwable in context as "... at /vendor/path/File.php:95", which reads
                // like an unhandled error even though this is a deliberately caught, expected case.
                $this->logger->warning($e->getMessage(), ['reason' => $retryableCause->getMessage()]);
            } else {
                $this->logger->critical($e->getMessage(), ['exception' => $e]);
            }
            $this->getResponse()->setHttpResponseCode(500);
            return;
        }
        $this->getResponse()->setHttpResponseCode(200);
    }

    /**
     * Finds the retryable cause in the given exception's chain, if any.
     *
     * WebhookProcessor re-wraps every failure into a generic CommandException before it
     * reaches this controller, so the original cause's retryability has to be recovered by
     * walking the exception chain rather than checked on $e directly.
     *
     * @param \Throwable $e
     * @return AbstractDomainException|null
     */
    private function findRetryableCause(\Throwable $e): ?AbstractDomainException
    {
        for ($cause = $e; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof AbstractDomainException && $cause->isRetryable()) {
                return $cause;
            }
        }
        return null;
    }

    /**
     * Bypass CSRF validation for this endpoint.
     *
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * No CSRF validation exception is required for this endpoint.
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }
}
