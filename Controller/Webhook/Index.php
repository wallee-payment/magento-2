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
use Wallee\PluginCore\Webhook\WebhookProcessor;
use Wallee\Payment\Model\CoreWebhook\RegistryConfigurer;
use Wallee\PluginCore\Http\Request as PluginCoreRequest;
use Psr\Log\LoggerInterface;

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
            $this->registryConfigurer->configure();
            $pluginCoreRequest = PluginCoreRequest::fromMagentoRequest($this->getRequest());
            $this->webhookProcessor->process($pluginCoreRequest);
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $this->getResponse()->setHttpResponseCode(500);
            return;
        }
        $this->getResponse()->setHttpResponseCode(200);
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
