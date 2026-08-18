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
namespace Wallee\Payment\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Wallee\Payment\Api\PaymentMethodConfigurationRepositoryInterface;
use Wallee\Payment\Api\TokenInfoManagementInterface;
use Wallee\Payment\Api\TokenInfoRepositoryInterface;
use Wallee\Payment\Api\Data\TokenInfoInterface;
use Wallee\PluginCore\Token\State as CoreTokenState;
use Wallee\PluginCore\Token\TokenService as CoreTokenService;
use Wallee\PluginCore\Token\TokenVersion as CoreTokenVersion;
use Psr\Log\LoggerInterface;

/**
 * Token info management service.
 */
class TokenInfoManagement implements TokenInfoManagementInterface
{

    /**
     *
     * @var TokenInfoRepositoryInterface
     */
    private $tokenInfoRepository;

    /**
     *
     * @var TokenInfoFactory
     */
    private $tokenInfoFactory;

    /**
     *
     * @var PaymentMethodConfigurationRepositoryInterface
     */
    private $paymentMethodConfigurationRepository;

    /**
     *
     * @var CoreTokenService
     */
    private $pluginCoreTokenService;

    /**
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     *
     * @param TokenInfoRepositoryInterface $tokenInfoRepository
     * @param TokenInfoFactory $tokenInfoFactory
     * @param PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository
     * @param CoreTokenService $pluginCoreTokenService
     * @param LoggerInterface $logger
     */
    public function __construct(
        TokenInfoRepositoryInterface $tokenInfoRepository,
        TokenInfoFactory $tokenInfoFactory,
        PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository,
        CoreTokenService $pluginCoreTokenService,
        LoggerInterface $logger
    ) {
        $this->tokenInfoRepository = $tokenInfoRepository;
        $this->tokenInfoFactory = $tokenInfoFactory;
        $this->paymentMethodConfigurationRepository = $paymentMethodConfigurationRepository;
        $this->pluginCoreTokenService = $pluginCoreTokenService;
        $this->logger = $logger;
    }

    /**
     * Update local token version info based on token version id.
     *
     * @param int $spaceId
     * @param int $tokenVersionId
     * @return void
     */
    public function updateTokenVersion($spaceId, $tokenVersionId)
    {
        $tokenVersion = $this->pluginCoreTokenService->getTokenVersion((int) $spaceId, (int) $tokenVersionId);
        if ($tokenVersion !== null) {
            $this->updateTokenVersionInfo($tokenVersion);
        } else {
            $this->logger->debug('Token version not found; nothing to update.', [
                'spaceId' => $spaceId,
                'tokenVersionId' => $tokenVersionId,
            ]);
        }
    }

    /**
     * Synchronize local token info with the remote token state.
     *
     * @param int $spaceId
     * @param int $tokenId
     * @return void
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\StateException
     */
    public function updateToken($spaceId, $tokenId)
    {
        $tokenVersion = $this->pluginCoreTokenService->getActiveTokenVersion((int) $spaceId, (int) $tokenId);
        if ($tokenVersion !== null) {
            $this->updateTokenVersionInfo($tokenVersion);
            return;
        }

        try {
            $tokenInfo = $this->tokenInfoRepository->getByTokenId($spaceId, $tokenId);
            $this->tokenInfoRepository->delete($tokenInfo);
            $this->logger->info('Token has no active version; deleted local token info.', [
                'spaceId' => $spaceId,
                'tokenId' => $tokenId,
            ]);
        } catch (NoSuchEntityException $e) {
            $this->logger->debug(
                sprintf(
                    "An issue occurred retrieving or deleting the token info by token id %s.",
                    $tokenId,
                ),
                ['exception' => $e]
            );
        }
    }

    /**
     * Updates token info based on the given token version.
     *
     * @param CoreTokenVersion $tokenVersion
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\StateException
     */
    protected function updateTokenVersionInfo(CoreTokenVersion $tokenVersion)
    {
        try {
            $tokenInfo = $this->tokenInfoRepository->getByTokenId(
                $tokenVersion->linkedSpaceId,
                $tokenVersion->token->id
            );
        } catch (NoSuchEntityException $e) {
            $tokenInfo = $this->tokenInfoFactory->create();
        }

        if (! \in_array($tokenVersion->token->state, [CoreTokenState::ACTIVE, CoreTokenState::INACTIVE], true)) {
            if ($tokenInfo->getId()) {
                $this->tokenInfoRepository->delete($tokenInfo);
                $this->logger->info('Token version is no longer active or inactive; deleted local token info.', [
                    'tokenId' => $tokenVersion->token->id,
                    'spaceId' => $tokenVersion->linkedSpaceId,
                    'state' => $tokenVersion->token->state->value,
                ]);
            }
        } else {
            $tokenInfo->setData(TokenInfoInterface::CUSTOMER_ID, $tokenVersion->token->customerId);
            $tokenInfo->setData(TokenInfoInterface::NAME, $tokenVersion->name);

            if ($tokenVersion->paymentMethodConfigurationId !== null) {
                try {
                    $tokenInfo->setData(
                        TokenInfoInterface::PAYMENT_METHOD_ID,
                        $this->paymentMethodConfigurationRepository->getByConfigurationId(
                            $tokenVersion->linkedSpaceId,
                            $tokenVersion->paymentMethodConfigurationId
                        )->getId()
                    );
                } catch (NoSuchEntityException $e) {
                    $this->logger->debug(
                        "Could not resolve the local payment method configuration for a token version.",
                        [
                            'spaceId' => $tokenVersion->linkedSpaceId,
                            'paymentMethodConfigurationId' => $tokenVersion->paymentMethodConfigurationId,
                            'exception' => $e,
                        ]
                    );
                }
            }
            if ($tokenVersion->connectorConfigurationId !== null) {
                $tokenInfo->setData(TokenInfoInterface::CONNECTOR_ID, $tokenVersion->connectorConfigurationId);
            }

            $tokenInfo->setData(TokenInfoInterface::SPACE_ID, $tokenVersion->linkedSpaceId);
            $tokenInfo->setData(TokenInfoInterface::STATE, $tokenVersion->token->state->value);
            $tokenInfo->setData(TokenInfoInterface::TOKEN_ID, $tokenVersion->token->id);
            $this->tokenInfoRepository->save($tokenInfo);

            $this->logger->info('Token info updated.', [
                'tokenId' => $tokenVersion->token->id,
                'tokenVersionId' => $tokenVersion->id,
                'spaceId' => $tokenVersion->linkedSpaceId,
                'state' => $tokenVersion->token->state->value,
            ]);
        }
    }

    /**
     * Deletes token in portal and repository.
     *
     * @param TokenInfoInterface $token
     * @return void
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\StateException
     */
    public function deleteToken(TokenInfoInterface $token)
    {
        $this->pluginCoreTokenService->deleteToken((int) $token->getSpaceId(), (int) $token->getTokenId());
        $this->tokenInfoRepository->delete($token);

        $this->logger->info('Token deleted.', [
            'tokenId' => $token->getTokenId(),
            'spaceId' => $token->getSpaceId(),
        ]);
    }
}
