<?php
declare(strict_types=1);

/**
 * Wallee OpenCart
 *
 * This OpenCart module enables to process payments with Wallee (https://www.wallee.com).
 *
 * @package Whitelabelshortcut\Wallee
 * @author wallee AG (https://www.wallee.com)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */

namespace Wallee\Service;

/**
 * This service provides functions to deal with Wallee refunds.
 */
class Refund extends AbstractJob {

	/**
	 * Generates a unique external refund ID for the given transaction.
	 *
	 * @param \Wallee\Entity\TransactionInfo $transaction_info
	 * @return string
	 */
	private function getExternalRefundId(\Wallee\Entity\TransactionInfo $transaction_info): string {
		$count = \Wallee\Entity\RefundJob::countForOrder($this->registry, $transaction_info->getOrderId());
		return 'r-' . $transaction_info->getOrderId() . '-' . ($count + 1);
	}

	/**
	 * Creates a new refund job for the given transaction.
	 *
	 * @param \Wallee\Entity\TransactionInfo $transaction_info
	 * @param array<string, array{id: string, quantity: string, unit_price: string}> $reductions
	 * @param bool $restock
	 * @return \Wallee\Entity\RefundJob
	 * @throws \RuntimeException
	 */
	public function create(
		\Wallee\Entity\TransactionInfo $transaction_info,
		array $reductions,
		bool $restock
	): \Wallee\Entity\RefundJob {
		try {
			\WalleeHelper::instance($this->registry)->dbTransactionStart();
			\WalleeHelper::instance($this->registry)->dbTransactionLock(
				$transaction_info->getSpaceId(),
				$transaction_info->getTransactionId()
			);
			
			$job = \Wallee\Entity\RefundJob::loadNotSentForOrder($this->registry, $transaction_info->getOrderId());
			$reduction_line_items = $this->getLineItemReductions($reductions);
			
			if (!$job->getId()) {
				$job = $this->createBase($transaction_info, $job);
				$job->setReductionItems($reduction_line_items);
				$job->setRestock($restock);
				$job->setExternalId($this->getExternalRefundId($transaction_info));
				$job->save();
			} elseif ($job->getReductionItems() !== $reduction_line_items) {
				throw new \RuntimeException(
					\WalleeHelper::instance($this->registry)->getTranslation('error_already_running')
				);
			}
			
			\WalleeHelper::instance($this->registry)->dbTransactionCommit();
			return $job;
		} catch (\Exception $e) {
			\WalleeHelper::instance($this->registry)->dbTransactionRollback();
			throw new \RuntimeException(
				sprintf('Fehler beim Erstellen des Refund-Jobs: %s', $e->getMessage()),
				$e->getCode(),
				$e
			);
		}
	}

	/**
	 * Sends the refund job to the gateway.
	 *
	 * @param \Wallee\Entity\RefundJob $job
	 * @return \Wallee\Entity\RefundJob
	 * @throws \RuntimeException
	 */
	public function send(\Wallee\Entity\RefundJob $job): \Wallee\Entity\RefundJob {
		try {
			\WalleeHelper::instance($this->registry)->dbTransactionStart();
			\WalleeHelper::instance($this->registry)->dbTransactionLock($job->getSpaceId(), $job->getTransactionId());
			
			$service = new \Wallee\Sdk\Service\RefundService(\WalleeHelper::instance($this->registry)->getApiClient());
			$operation = $service->refund($job->getSpaceId(), $this->createRefund($job));
			
			if ($operation->getFailureReason() !== null) {
				$job->setFailureReason($operation->getFailureReason()->getDescription());
			}
			
			$labels = [];
			foreach ($operation->getLabels() as $label) {
				$labels[$label->getDescriptor()->getId()] = $label->getContentAsString();
			}
			$job->setLabels($labels);
			
			$job->setJobId($operation->getId());
			$job->setState(\Wallee\Entity\AbstractJob::STATE_SENT);
			$job->setAmount($operation->getAmount());
			$job->save();
			
			\WalleeHelper::instance($this->registry)->dbTransactionCommit();
			return $job;
		} catch (\Wallee\Sdk\ApiException $api_exception) {
			return $this->handleApiException($job, $api_exception);
		} catch (\Exception $e) {
			\WalleeHelper::instance($this->registry)->dbTransactionRollback();
			throw new \RuntimeException(
				sprintf('Fehler beim Senden des Refund-Jobs: %s', $e->getMessage()),
				$e->getCode(),
				$e
			);
		}
	}

	/**
	 * Creates a refund model for the API.
	 *
	 * @param \Wallee\Entity\RefundJob $job
	 * @return \Wallee\Sdk\Model\RefundCreate
	 */
	private function createRefund(\Wallee\Entity\RefundJob $job): \Wallee\Sdk\Model\RefundCreate {
		$refund_create = new \Wallee\Sdk\Model\RefundCreate();
		$refund_create->setReductions($job->getReductionItems());
		$refund_create->setExternalId($job->getExternalId());
		$refund_create->setTransaction($job->getTransactionId());
		$refund_create->setType(\Wallee\Sdk\Model\RefundType::MERCHANT_INITIATED_ONLINE);
		return $refund_create;
	}

	/**
	 * Converts the reduction data into line item reduction objects.
	 *
	 * @param array<string, array{id: string, quantity: string, unit_price: string}> $reductions
	 * @return array<\Wallee\Sdk\Model\LineItemReductionCreate>
	 */
	private function getLineItemReductions(array $reductions): array {
		$reduction_line_items = [];
		foreach ($reductions as $reduction) {
			if ((float)$reduction['quantity'] > 0 || (float)$reduction['unit_price'] > 0) {
				$line_item = new \Wallee\Sdk\Model\LineItemReductionCreate();
				$line_item->setLineItemUniqueId($reduction['id']);
				$line_item->setQuantityReduction((float)$reduction['quantity']);
				$line_item->setUnitPriceReduction((float)$reduction['unit_price']);
				$reduction_line_items[] = $line_item;
			}
		}
		return $reduction_line_items;
	}
}