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
namespace Wallee\Payment\Cron;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\Payment\Api\RefundJobRepositoryInterface;
use Wallee\PluginCore\Refund\Exception\InvalidRefundException;
use Wallee\PluginCore\Refund\Exception\RefundException;
use Wallee\PluginCore\Refund\RefundService;
use Wallee\PluginCore\Transaction\Exception\TransactionException;

/**
 * Class to handle pending refund jobs.
 */
class Refund
{

    /**
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     *
     * @var RefundJobRepositoryInterface
     */
    private $refundJobRepository;

    /**
     *
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     *
     * @var RefundService
     */
    private $refundService;

    /**
     *
     * @param LoggerInterface $logger
     * @param RefundJobRepositoryInterface $refundJobRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param RefundService $refundService
     */
    public function __construct(
        LoggerInterface $logger,
        RefundJobRepositoryInterface $refundJobRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RefundService $refundService
    ) {
        $this->logger = $logger;
        $this->refundJobRepository = $refundJobRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->refundService = $refundService;
    }

    /**
     * Process pending refund jobs.
     *
     * @return void
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\StateException
     */
    public function execute()
    {
        $searchCriteria = $this->searchCriteriaBuilder->setPageSize(100)->create();
        $refundJobs = $this->refundJobRepository->getList($searchCriteria)->getItems();
        foreach ($refundJobs as $refundJob) {
            try {
                $this->refundService->createRefund((int) $refundJob->getSpaceId(), $refundJob->getRefund());
                $this->logger->info('Refund job resubmitted to the gateway.', ['refundJobId' => $refundJob->getId()]);
            } catch (InvalidRefundException|RefundException|TransactionException $e) {
                if ($e->isRetryable()) {
                    // Transient failure: leave the job for the next run.
                    $this->logger->critical('Refund job failed; leaving it for the next run.', [
                        'refundJobId' => $refundJob->getId(),
                        'exception' => $e,
                    ]);
                } else {
                    // Terminal failure: retrying the same payload will fail again.
                    $this->logger->critical('Refund job rejected by the gateway; deleting it.', [
                        'refundJobId' => $refundJob->getId(),
                        'exception' => $e,
                    ]);
                    $this->refundJobRepository->delete($refundJob);
                }
            } catch (\Exception $e) {
                $this->logger->critical('Unexpected error processing refund job.', [
                    'refundJobId' => $refundJob->getId(),
                    'exception' => $e,
                ]);
            }
        }
    }
}
