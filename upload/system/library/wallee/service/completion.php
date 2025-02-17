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
 * This service provides functions to deal with Wallee completions.
 */
class Completion extends AbstractJob {

	/**
	 * Creates a new completion job for the given transaction.
	 *
	 * @param \Wallee\Entity\TransactionInfo $transaction_info
	 * @return \Wallee\Entity\CompletionJob
	 * @throws \RuntimeException When the job creation fails
	 */
	public function create(\Wallee\Entity\TransactionInfo $transaction_info): \Wallee\Entity\CompletionJob {
		try {
			\WalleeHelper::instance($this->registry)->dbTransactionStart();
			\WalleeHelper::instance($this->registry)->dbTransactionLock(
				$transaction_info->getSpaceId(),
				$transaction_info->getTransactionId()
			);
			
			$job = \Wallee\Entity\CompletionJob::loadNotSentForOrder($this->registry, $transaction_info->getOrderId());
			if (!$job->getId()) {
				$job = $this->createBase($transaction_info, $job);
				$job->save();
			}
			
			\WalleeHelper::instance($this->registry)->dbTransactionCommit();
			return $job;
		} catch (\Exception $e) {
			\WalleeHelper::instance($this->registry)->dbTransactionRollback();
			throw new \RuntimeException(
				sprintf('Fehler beim Erstellen des Completion-Jobs: %s', $e->getMessage()),
				$e->getCode(),
				$e
			);
		}
	}

	/**
	 * Sends the completion job to the gateway.
	 *
	 * @param \Wallee\Entity\CompletionJob $job
	 * @return \Wallee\Entity\CompletionJob
	 * @throws \Wallee\Sdk\ApiException When the API call fails
	 * @throws \RuntimeException When the job update fails
	 */
	public function send(\Wallee\Entity\CompletionJob $job): \Wallee\Entity\CompletionJob {
		try {
			\WalleeHelper::instance($this->registry)->dbTransactionStart();
			\WalleeHelper::instance($this->registry)->dbTransactionLock($job->getSpaceId(), $job->getTransactionId());
			
			$service = new \Wallee\Sdk\Service\TransactionCompletionService(
				\WalleeHelper::instance($this->registry)->getApiClient()
			);
			$operation = $service->completeOnline($job->getSpaceId(), $job->getTransactionId());
			
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
			$job->save();
			
			\WalleeHelper::instance($this->registry)->dbTransactionCommit();
			return $job;
		} catch (\Wallee\Sdk\ApiException $api_exception) {
			return $this->handleApiException($job, $api_exception);
		} catch (\Exception $e) {
			\WalleeHelper::instance($this->registry)->dbTransactionRollback();
			throw new \RuntimeException(
				sprintf('Fehler beim Senden des Completion-Jobs: %s', $e->getMessage()),
				$e->getCode(),
				$e
			);
		}
	}
}