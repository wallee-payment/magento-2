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

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Magento\Framework\Exception\LocalizedException;
use Wallee\Payment\Api\TransactionInfoManagementInterface;
use Wallee\Payment\Api\TransactionInfoRepositoryInterface;
use Wallee\Payment\Api\Data\TransactionInfoInterface;
use Wallee\PluginCore\Charge\ChargeService;
use Wallee\PluginCore\GlobalData\GlobalDataService;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Transaction\State as CoreTransactionState;
use Wallee\PluginCore\Transaction\Transaction;

/**
 * Transaction info management service.
 */
class TransactionInfoManagement implements TransactionInfoManagementInterface
{
    /**
     * Cache identifier for the connector-id-to-payment-method-type-id map.
     *
     * @var string
     */
    private const CACHE_KEY_PAYMENT_CONNECTOR_MAP = 'wallee_payment_connector_map';

    /**
     * Payment connectors are shop-agnostic reference data that changes rarely, so a short
     * TTL is enough to avoid fetching the full connector catalog on every transaction update.
     *
     * @var int
     */
    private const CACHE_LIFETIME_PAYMENT_CONNECTOR_MAP = 600;

    /**
     *
     * @var TransactionInfoRepositoryInterface
     */
    private $transactionInfoRepository;

    /**
     *
     * @var TransactionInfoFactory
     */
    private $transactionInfoFactory;

    /**
     *
     * @var ChargeService
     */
    private $chargeService;

    /**
     *
     * @var GlobalDataService
     */
    private $globalDataService;

    /**
     *
     * @var CacheInterface
     */
    private $cache;

    /**
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     *
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param TransactionInfoFactory $transactionInfoFactory
     * @param ChargeService $chargeService
     * @param GlobalDataService $globalDataService
     * @param CacheInterface $cache
     * @param LoggerInterface $logger
     */
    public function __construct(
        TransactionInfoRepositoryInterface $transactionInfoRepository,
        TransactionInfoFactory $transactionInfoFactory,
        ChargeService $chargeService,
        GlobalDataService $globalDataService,
        CacheInterface $cache,
        LoggerInterface $logger
    ) {
        $this->transactionInfoRepository = $transactionInfoRepository;
        $this->transactionInfoFactory = $transactionInfoFactory;
        $this->chargeService = $chargeService;
        $this->globalDataService = $globalDataService;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * Update transaction info for the given order.
     *
     * @param Transaction $transaction
     * @param Order $order
     * @return TransactionInfoInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function update(Transaction $transaction, Order $order)
    {
        try {
            $info = $this->transactionInfoRepository->getByTransactionId(
                $transaction->spaceId,
                $transaction->id
            );

            if ($info->getOrderId() != $order->getId() && !$info->isExternalPaymentUrl()) {
                $this->logger->error('Transaction info is already linked to a different order.', [
                    'orderId' => $order->getId(),
                    'existingOrderId' => $info->getOrderId(),
                    'transactionId' => $transaction->id,
                    'spaceId' => $transaction->spaceId,
                ]);
                throw new LocalizedException(
                    \__('The wallee transaction info is already linked to a different order.')
                );
            }
        } catch (NoSuchEntityException $e) {
            $info = $this->transactionInfoFactory->create();
        }
        $info = $this->setTransactionData($transaction, $info, null, $order);
        $this->transactionInfoRepository->save($info);

        $this->logger->info('Transaction info updated.', [
            'orderId' => $order->getId(),
            'transactionId' => $transaction->id,
            'spaceId' => $transaction->spaceId,
            'state' => $transaction->state->value,
        ]);

        return $info;
    }

    /**
     * Update the transaction info with the success and failure URL to redirect the customer after placing the order
     *
     * @param Transaction $transaction
     * @param int $orderId
     * @param string $successUrl
     * @param string $failureUrl
     * @return TransactionInfoInterface|TransactionInfo
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function setRedirectUrls(Transaction $transaction, $orderId, $successUrl, $failureUrl)
    {
        try {
            $info = $this->transactionInfoRepository->getByTransactionId(
                $transaction->spaceId,
                $transaction->id
            );

            //prevents a new transaction info from being created by duplicating the order id
            if ($info->getOrderId() != (int)$orderId) {
                $info = $this->transactionInfoRepository->getByOrderId($orderId);
            }

        } catch (NoSuchEntityException $e) {
            $info = $this->transactionInfoFactory->create();
        }

        $info = $this->setTransactionData($transaction, $info, $orderId, null, $successUrl, $failureUrl);
        $this->transactionInfoRepository->save($info);

        $this->logger->info('Transaction info redirect URLs updated.', [
            'orderId' => $orderId,
            'transactionId' => $transaction->id,
            'spaceId' => $transaction->spaceId,
        ]);

        return $info;
    }

    /**
     * Update the transaction info
     *
     * @param Transaction $transaction
     * @param TransactionInfo $transactionInfo
     * @param int|null $orderId
     * @param Order|null $order
     * @param string|null $successUrl
     * @param string|null $failureUrl
     * @return TransactionInfoInterface|TransactionInfo
     */
    private function setTransactionData(
        Transaction $transaction,
        TransactionInfo $transactionInfo,
        $orderId = null,
        ?Order $order = null,
        $successUrl = null,
        $failureUrl = null
    ) {
        $transactionInfo->setData(TransactionInfoInterface::TRANSACTION_ID, $transaction->id);
        $transactionInfo->setData(
            TransactionInfoInterface::AUTHORIZATION_AMOUNT,
            $transaction->authorizedAmount
        );
        $transactionInfo->setData(
            TransactionInfoInterface::ORDER_ID,
            $order instanceof Order ? $order->getId() : $orderId
        );
        $transactionInfo->setData(TransactionInfoInterface::STATE, $transaction->state->value);
        $transactionInfo->setData(TransactionInfoInterface::SPACE_ID, $transaction->spaceId);
        $transactionInfo->setData(TransactionInfoInterface::SPACE_VIEW_ID, $transaction->environment?->spaceViewId);
        $transactionInfo->setData(TransactionInfoInterface::LANGUAGE, $transaction->environment?->language);
        $transactionInfo->setData(TransactionInfoInterface::CURRENCY, $transaction->currency);
        $transactionInfo->setData(TransactionInfoInterface::CONNECTOR_ID, $transaction->paymentMethod?->connectorId);
        $transactionInfo->setData(
            TransactionInfoInterface::PAYMENT_METHOD_ID,
            $this->resolvePaymentMethodTypeId($transaction->paymentMethod?->connectorId)
        );
        $transactionInfo->setData(TransactionInfoInterface::LABELS, $this->getTransactionLabels($transaction));

        if (!empty($order) && $order instanceof Order) {
            $transactionInfo->setData(
                TransactionInfoInterface::IMAGE,
                $this->getPaymentMethodImage($transaction, $order)
            );
        }

        if (!empty($successUrl) || !empty($failureUrl)) {
            $transactionInfo->setData(TransactionInfoInterface::SUCCESS_URL, $successUrl);
            $transactionInfo->setData(TransactionInfoInterface::FAILURE_URL, $failureUrl);
        }

        if ($transaction->state === CoreTransactionState::FAILED
            || $transaction->state === CoreTransactionState::DECLINE) {
            $transactionInfo->setData(
                TransactionInfoInterface::FAILURE_REASON,
                $transaction->failureReason?->getDefault()
            );
        }

        return $transactionInfo;
    }

    /**
     * Gets an array of the transaction's labels.
     *
     * @param Transaction $transaction
     * @return string[]
     */
    private function getTransactionLabels(Transaction $transaction)
    {
        $chargeAttempt = $this->chargeService->findSuccessfulAttemptByTransaction(
            $transaction->spaceId,
            $transaction->id
        );
        if ($chargeAttempt === null) {
            $this->logger->debug('No successful charge attempt found for transaction; no labels to show.', [
                'transactionId' => $transaction->id,
                'spaceId' => $transaction->spaceId,
            ]);
            return [];
        }

        $labels = [];
        foreach ($chargeAttempt->labels as $label) {
            $labels[$label->descriptorId] = $label->content;
        }

        $this->logger->debug('Resolved transaction labels from the successful charge attempt.', [
            'transactionId' => $transaction->id,
            'spaceId' => $transaction->spaceId,
            'labelCount' => count($labels),
        ]);

        return $labels;
    }

    /**
     * Resolves the payment method type ID for the given connector, via the
     * connector's own listing — TransactionPaymentMethod only carries the
     * connector-scoped ID, not the payment method type ID directly.
     *
     * @param int|null $connectorId
     * @return int|null
     */
    private function resolvePaymentMethodTypeId(?int $connectorId): ?int
    {
        if ($connectorId === null) {
            return null;
        }
        return $this->getConnectorToPaymentMethodTypeIdMap()[$connectorId] ?? null;
    }

    /**
     * Returns the connector-id-to-payment-method-type-id map, backed by a short-lived cache.
     *
     * Avoids re-fetching the full connector catalog on every transaction update.
     *
     * @return array<int, int|null>
     */
    private function getConnectorToPaymentMethodTypeIdMap(): array
    {
        $cached = $this->cache->load(self::CACHE_KEY_PAYMENT_CONNECTOR_MAP);
        if ($cached !== false) {
            return json_decode($cached, true);
        }

        $map = [];
        foreach ($this->globalDataService->getPaymentConnectors() as $connector) {
            $map[$connector->id] = $connector->paymentMethodId;
        }

        $this->cache->save(
            json_encode($map),
            self::CACHE_KEY_PAYMENT_CONNECTOR_MAP,
            [],
            self::CACHE_LIFETIME_PAYMENT_CONNECTOR_MAP
        );

        return $map;
    }

    /**
     * Gets the payment method's image.
     *
     * @param Transaction $transaction
     * @param Order $order
     * @return string
     */
    private function getPaymentMethodImage(Transaction $transaction, Order $order)
    {
        if ($transaction->paymentMethod?->resolvedImageUrl !== null) {
            return $this->extractImagePath($transaction->paymentMethod->resolvedImageUrl);
        } else {
            return $order->getPayment()
                ->getMethodInstance()
                ->getPaymentMethodConfiguration()
                ->getImage();
        }
    }

    /**
     * Extracts the image path from the URL.
     *
     * @param string $resolvedImageUrl
     * @return string
     */
    private function extractImagePath($resolvedImageUrl)
    {
        $index = \strpos($resolvedImageUrl ?? '', 'resource/');
        return \substr($resolvedImageUrl, $index + \strlen('resource/'));
    }
}
