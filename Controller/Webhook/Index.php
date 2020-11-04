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
namespace Wallee\Payment\Controller\Webhook;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\Exception\NotFoundException;
use Wallee\Payment\Model\Service\WebhookService;
use Wallee\Payment\Model\Webhook\Request;

/**
 * Frontend controller action to proces webhook requests.
 */
class Index extends \Wallee\Payment\Controller\Webhook implements CsrfAwareActionInterface
{

    /**
     *
     * @var WebhookService
     */
    private $webhookService;

    /**
     *
     * @param Context $context
     * @param WebhookService $webhookService
     */
    public function __construct(Context $context, WebhookService $webhookService)
    {
        parent::__construct($context);
        $this->webhookService = $webhookService;
    }

    public function execute()
    {
        http_response_code(500);
        $this->getResponse()->setHttpResponseCode(500);
        try {
            $this->webhookService->execute($this->parseRequest());
        } catch (NotFoundException $e) {
            throw new \Exception($e);
        }
        $this->getResponse()->setHttpResponseCode(200);
    }

    /**
     * Parses the HTTP request.
     *
     * @throws \InvalidArgumentException
     * @return \Wallee\Payment\Model\Webhook\Request
     */
    private function parseRequest()
    {
        $jsonRequest = $this->getRequest()->getContent();
        if (empty($jsonRequest)) {
            throw new \InvalidArgumentException('Empty request.');
        }
        $parsedRequest = \json_decode($jsonRequest, true);
        if (\json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Unable to unserialize value.');
        }
        return new Request($parsedRequest);
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }
}