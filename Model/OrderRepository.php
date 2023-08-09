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
namespace Wallee\Payment\Model;

use Exception;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface as BaseOrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Wallee\Payment\Api\OrderRepositoryInterface;

class OrderRepository implements OrderRepositoryInterface
{
	/**
	 * @var FilterBuilder
	 */
	protected $filterBuilder;

	/**
	 * @var FilterGroupBuilder
	 */
	protected $filterGroupBuilder;

	/**
	 * @var SearchCriteriaBuilder
	 */
	protected $searchCriteriaBuilder;

	/**
	 * @var BaseOrderRepositoryInterface
	 */
	private $orderRepository;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 *
	 * @param SearchCriteriaBuilder $searchCriteriaBuilder
	 * @param BaseOrderRepositoryInterface $orderRepository
	 * @param FilterBuilder $filterBuilder
	 * @param FilterGroupBuilder $filterGroupBuilder
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		SearchCriteriaBuilder $searchCriteriaBuilder,
		BaseOrderRepositoryInterface $orderRepository,
		FilterBuilder $filterBuilder,
		FilterGroupBuilder $filterGroupBuilder,
		LoggerInterface $logger
	) {
		$this->filterBuilder = $filterBuilder;
		$this->filterGroupBuilder = $filterGroupBuilder;
		$this->searchCriteriaBuilder = $searchCriteriaBuilder;
		$this->orderRepository = $orderRepository;
		$this->logger = $logger;
	}

	/**
	 * Get Order data by Order Increment Id
	 *
	 * @param $incrementId
	 * @return OrderInterface|null
	 */
	public function getOrderByIncrementId($incrementId)
	{
		$orderData = null;

		/** @var SearchCriteriaBuilder $searchCriteriaBuilder */
		$searchCriteriaBuilder = $this->searchCriteriaBuilder;

		/** @var FilterBuilder $filterBuilder */
		$filterBuilder = $this->filterBuilder;

		/** @var FilterGroupBuilder $filterGroupBuilder */
		$filterGroupBuilder = $this->filterGroupBuilder;

		$filter = $filterBuilder
			->setField('increment_id')
			->setValue($incrementId)
			->setConditionType('eq')
			->create();

		$filterGroup = $filterGroupBuilder
			->addFilter($filter)
			->create();

		$searchCriteriaBuilder->setFilterGroups([$filterGroup]);
		$searchCriteriaBuilder->setPageSize(1);
		$searchCriteria = $searchCriteriaBuilder->create();

		try {
			$orderList = $this->orderRepository->getList($searchCriteria);
			if ($orderList->getTotalCount() > 0) {
				$items = $orderList->getItems();
				$orderData = reset($items);
			}
		} catch (Exception $exception) {
			$this->logger->critical($exception->getMessage());
		}
		return $orderData;
	}

	/**
	 * Get Order data by Id
	 *
	 * @param $id
	 * @return OrderInterface|null
	 */
	public function getOrderById($id)
	{
		return $this->orderRepository->get($id);
	}
}
