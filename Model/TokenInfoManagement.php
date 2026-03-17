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
use Wallee\Payment\Helper\Data as Helper;
use Wallee\Sdk\Model\CreationEntityState;
use Wallee\Sdk\Model\EntityQuery;
use Wallee\Sdk\Model\EntityQueryFilter;
use Wallee\Sdk\Model\EntityQueryFilterType;
use Wallee\Sdk\Model\TokenVersion;
use Wallee\Sdk\Model\TokenVersionState;
use Wallee\Sdk\Service\TokenService;
use Wallee\Sdk\Service\TokenVersionService;
use Psr\Log\LoggerInterface;

/**
 * Token info management service.
 */
class TokenInfoManagement implements TokenInfoManagementInterface
{

    /**
     *
     * @var Helper
     */
    private $helper;

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
     * @var ApiClient
     */
    private $apiClient;

    /**
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     *
     * @param Helper $helper
     * @param TokenInfoRepositoryInterface $tokenInfoRepository
     * @param TokenInfoFactory $tokenInfoFactory
     * @param PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository
     * @param ApiClient $apiClient
     * @param LoggerInterface $logger
     */
    public function __construct(
        Helper $helper,
        TokenInfoRepositoryInterface $tokenInfoRepository,
        TokenInfoFactory $tokenInfoFactory,
        PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository,
        ApiClient $apiClient,
        LoggerInterface $logger
    ) {
        $this->helper = $helper;
        $this->tokenInfoRepository = $tokenInfoRepository;
        $this->tokenInfoFactory = $tokenInfoFactory;
        $this->paymentMethodConfigurationRepository = $paymentMethodConfigurationRepository;
        $this->apiClient = $apiClient;
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
        $tokenVersion = $this->apiClient->getService(TokenVersionService::class)->read($spaceId, $tokenVersionId);
        $this->updateTokenVersionInfo($tokenVersion);
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
        $query = new EntityQuery();
        $filter = new EntityQueryFilter();
        $filter->setType(EntityQueryFilterType::_AND);
        $filter->setChildren(
            [
                $this->helper->createEntityFilter('token.id', $tokenId),
                $this->helper->createEntityFilter('state', TokenVersionState::ACTIVE)
            ]
        );
        $query->setFilter($filter);
        $query->setNumberOfEntities(1);
        $tokenVersions = $this->apiClient->getService(TokenVersionService::class)->search($spaceId, $query);
        if (! empty($tokenVersions)) {
            $this->updateTokenVersionInfo($tokenVersions[0]);
        } else {
            try {
                $tokenInfo = $this->tokenInfoRepository->getByTokenId($spaceId, $tokenId);
                $this->tokenInfoRepository->delete($tokenInfo);
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
    }

    /**
     * Updates token info based on the given token version.
     *
     * @param TokenVersion $tokenVersion
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\StateException
     */
    protected function updateTokenVersionInfo(TokenVersion $tokenVersion)
    {
        try {
            $tokenInfo = $this->tokenInfoRepository->getByTokenId(
                $tokenVersion->getLinkedSpaceId(),
                $tokenVersion->getToken()
                ->getId()
            );
        } catch (NoSuchEntityException $e) {
            $tokenInfo = $this->tokenInfoFactory->create();
        }

        if (! \in_array(
            $tokenVersion->getToken()->getState(),
            [
                CreationEntityState::ACTIVE,
                CreationEntityState::INACTIVE
            ]
        )) {
            if ($tokenInfo->getId()) {
                $this->tokenInfoRepository->delete($tokenInfo);
            }
        } else {
            $tokenInfo->setData(TokenInfoInterface::CUSTOMER_ID, $tokenVersion->getToken()
                ->getCustomerId());
            $tokenInfo->setData(TokenInfoInterface::NAME, $tokenVersion->getName());
            try {
                $tokenInfo->setData(
                    TokenInfoInterface::PAYMENT_METHOD_ID,
                    $this->paymentMethodConfigurationRepository->getByConfigurationId(
                        $tokenVersion->getLinkedSpaceId(),
                        $tokenVersion->getPaymentConnectorConfiguration()
                            ->getPaymentMethodConfiguration()
                        ->getId()
                    )
                    ->getId()
                );
                $tokenInfo->setData(
                    TokenInfoInterface::CONNECTOR_ID,
                    $tokenVersion->getPaymentConnectorConfiguration()
                    ->getId()
                );
            } catch (\Error $e) { //Catching, but not showing, ticket WAL-69414
                $error = $e;
            }

            $tokenInfo->setData(TokenInfoInterface::SPACE_ID, $tokenVersion->getLinkedSpaceId());
            $tokenInfo->setData(TokenInfoInterface::STATE, $tokenVersion->getToken()
                ->getState());
            $tokenInfo->setData(TokenInfoInterface::TOKEN_ID, $tokenVersion->getToken()
                ->getId());
            $this->tokenInfoRepository->save($tokenInfo);
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
        $this->apiClient->getService(TokenService::class)->delete($token->getSpaceId(), $token->getTokenId());
        $this->tokenInfoRepository->delete($token);
    }
}
