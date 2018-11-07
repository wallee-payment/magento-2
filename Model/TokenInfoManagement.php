<?php
/**
 * wallee Magento 2
 *
 * This Magento 2 extension enables to process payments with wallee (https://www.wallee.com/).
 *
 * @package Wallee_Payment
 * @author customweb GmbH (http://www.customweb.com/)
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

/**
 * Token info management service.
 */
class TokenInfoManagement implements TokenInfoManagementInterface
{

    /**
     *
     * @var Helper
     */
    protected $_helper;

    /**
     *
     * @var TokenInfoRepositoryInterface
     */
    protected $_tokenInfoRepository;

    /**
     *
     * @var TokenInfoFactory
     */
    protected $_tokenInfoFactory;

    /**
     *
     * @var PaymentMethodConfigurationRepositoryInterface
     */
    protected $_paymentMethodConfigurationRepository;

    /**
     *
     * @var ApiClient
     */
    protected $_apiClient;

    /**
     *
     * @param Helper $helper
     * @param TokenInfoRepositoryInterface $tokenInfoRepository
     * @param TokenInfoFactory $tokenInfoFactory
     * @param PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository
     * @param ApiClient $apiClient
     */
    public function __construct(Helper $helper, TokenInfoRepositoryInterface $tokenInfoRepository,
        TokenInfoFactory $tokenInfoFactory,
        PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository, ApiClient $apiClient)
    {
        $this->_helper = $helper;
        $this->_tokenInfoRepository = $tokenInfoRepository;
        $this->_tokenInfoFactory = $tokenInfoFactory;
        $this->_paymentMethodConfigurationRepository = $paymentMethodConfigurationRepository;
        $this->_apiClient = $apiClient;
    }

    public function updateTokenVersion($spaceId, $tokenVersionId)
    {
        $tokenVersion = $this->_apiClient->getService(TokenVersionService::class)->read($spaceId, $tokenVersionId);
        $this->updateTokenVersionInfo($tokenVersion);
    }

    public function updateToken($spaceId, $tokenId)
    {
        $query = new EntityQuery();
        $filter = new EntityQueryFilter();
        $filter->setType(EntityQueryFilterType::_AND);
        $filter->setChildren(
            [
                $this->_helper->createEntityFilter('token.id', $tokenId),
                $this->_helper->createEntityFilter('state', TokenVersionState::ACTIVE)
            ]);
        $query->setFilter($filter);
        $query->setNumberOfEntities(1);
        $tokenVersions = $this->_apiClient->getService(TokenVersionService::class)->search($spaceId, $query);
        if (! empty($tokenVersions)) {
            $this->updateTokenVersionInfo($tokenVersions[0]);
        } else {
            try {
                $tokenInfo = $this->_tokenInfoRepository->getByTokenId($spaceId, $tokenId);
                $this->_tokenInfoRepository->delete($tokenInfo);
            } catch (NoSuchEntityException $e) {}
        }
    }

    protected function updateTokenVersionInfo(TokenVersion $tokenVersion)
    {
        try {
            $tokenInfo = $this->_tokenInfoRepository->getByTokenId($tokenVersion->getLinkedSpaceId(),
                $tokenVersion->getToken()
                    ->getId());
        } catch (NoSuchEntityException $e) {
            $tokenInfo = $this->_tokenInfoFactory->create();
        }

        if (! \in_array($tokenVersion->getToken()->getState(),
            [
                CreationEntityState::ACTIVE,
                CreationEntityState::INACTIVE
            ])) {
            if ($tokenInfo->getId()) {
                $this->_tokenInfoRepository->delete($tokenInfo);
            }
        } else {
            $tokenInfo->setData(TokenInfoInterface::CUSTOMER_ID,
                $tokenVersion->getToken()
                    ->getCustomerId());
            $tokenInfo->setData(TokenInfoInterface::NAME, $tokenVersion->getName());
            $tokenInfo->setData(TokenInfoInterface::PAYMENT_METHOD_ID,
                $this->_paymentMethodConfigurationRepository->getByConfigurationId($tokenVersion->getLinkedSpaceId(),
                    $tokenVersion->getPaymentConnectorConfiguration()
                        ->getPaymentMethodConfiguration()
                        ->getId())
                    ->getId());
            $tokenInfo->setData(TokenInfoInterface::CONNECTOR_ID,
                $tokenVersion->getPaymentConnectorConfiguration()
                    ->getId());
            $tokenInfo->setData(TokenInfoInterface::SPACE_ID, $tokenVersion->getLinkedSpaceId());
            $tokenInfo->setData(TokenInfoInterface::STATE, $tokenVersion->getToken()
                ->getState());
            $tokenInfo->setData(TokenInfoInterface::TOKEN_ID, $tokenVersion->getToken()
                ->getId());
            $this->_tokenInfoRepository->save($tokenInfo);
        }
    }

    public function deleteToken(TokenInfo $token)
    {
        $this->_apiClient->getService(TokenService::class)->delete($token->getSpaceId(), $token->getTokenId());
        $this->_tokenInfoRepository->delete($token);
    }
}