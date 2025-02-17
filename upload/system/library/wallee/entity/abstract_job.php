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

namespace Wallee\Entity;

use DateTime;
use DateInterval;

/**
 * This entity holds data about a job in the system.
 *
 * @method int getId()
 * @method int getJobId()
 * @method void setJobId(int $id)
 * @method string getState()
 * @method void setState(string $state)
 * @method int getSpaceId()
 * @method void setSpaceId(int $id)
 * @method int getTransactionId()
 * @method void setTransactionId(int $id)
 * @method int getOrderId()
 * @method void setOrderId(int $id)
 * @method array<string, string>|null getFailureReason()
 * @method void setFailureReason(array<string, string> $reasons)
 * @method array<string, string> getLabels()
 * @method void setLabels(array<string, string> $labels)
 */
abstract class AbstractJob extends AbstractEntity {
	public const STATE_CREATED = 'CREATED';
	public const STATE_SENT = 'SENT';
	public const STATE_SUCCESS = 'SUCCESS';
	public const STATE_FAILED_CHECK = 'FAILED_CHECK';
	public const STATE_FAILED_DONE = 'FAILED_DONE';

	/**
	 * Returns the field definitions for the entity.
	 *
	 * @return array<string, string>
	 */
	protected static function getFieldDefinition(): array {
		return [
			'job_id' => ResourceType::INTEGER,
			'state' => ResourceType::STRING,
			'space_id' => ResourceType::INTEGER,
			'transaction_id' => ResourceType::INTEGER,
			'order_id' => ResourceType::INTEGER,
			'labels' => ResourceType::OBJECT,
			'failure_reason' => ResourceType::OBJECT
		];
	}

	/**
	 * Returns the translated failure reason.
	 *
	 * @param string|null $language
	 * @return string|null
	 */
	public function getFailureReason(?string $language = null): ?string {
		$value = $this->getValue('failure_reason');
		if (empty($value)) {
			return null;
		}
		
		return \WalleeHelper::instance($this->registry)->translate($value, $language);
	}

	/**
	 * Loads all jobs for an order.
	 *
	 * @param \Registry $registry
	 * @param int $order_id
	 * @return array<static>
	 */
	public static function loadByOrder(\Registry $registry, int $order_id): array {
		$db = $registry->get('db');
		
		$query = sprintf(
			'SELECT * FROM %s%s WHERE order_id = %d;',
			DB_PREFIX,
			static::getTableName(),
			$order_id
		);
		
		$db_result = static::query($query, $db);
		
		$result = [];
		if ($db_result->num_rows) {
			foreach ($db_result->rows as $row) {
				$result[] = new static($registry, $row);
			}
		}
		return $result;
	}

	/**
	 * Loads a job by its job data.
	 *
	 * @param \Registry $registry
	 * @param int $space_id
	 * @param int $job_id
	 * @return static
	 */
	public static function loadByJob(\Registry $registry, int $space_id, int $job_id): static {
		$db = $registry->get('db');
		
		$query = sprintf(
			'SELECT * FROM %s%s WHERE job_id = %d AND space_id = %d;',
			DB_PREFIX,
			static::getTableName(),
			$job_id,
			$space_id
		);
		
		$db_result = static::query($query, $db);
		if (isset($db_result->row) && !empty($db_result->row)) {
			return new static($registry, $db_result->row);
		}
		
		return new static($registry);
	}

	/**
	 * Counts running jobs for an order.
	 *
	 * @param \Registry $registry
	 * @param int $order_id
	 * @return int
	 */
	public static function countRunningForOrder(\Registry $registry, int $order_id): int {
		$db = $registry->get('db');
		
		$query = sprintf(
			'SELECT COUNT(id) AS count FROM %s%s WHERE order_id = %d AND state NOT IN ("%s", "%s", "%s");',
			DB_PREFIX,
			static::getTableName(),
			$order_id,
			self::STATE_SUCCESS,
			self::STATE_FAILED_CHECK,
			self::STATE_FAILED_DONE
		);
		
		$db_result = $db->query($query);
		return (int)$db_result->row['count'];
	}

	/**
	 * Loads not sent jobs.
	 * If called on abstract object will load all completions, refunds and voids.
	 *
	 * @param \Registry $registry
	 * @param string $period
	 * @return array<AbstractJob>
	 */
	public static function loadNotSent(\Registry $registry, string $period = 'PT10M'): array {
		if (get_called_class() === self::class) {
			return array_merge(
				CompletionJob::loadNotSent($registry, $period),
				VoidJob::loadNotSent($registry, $period),
				RefundJob::loadNotSent($registry, $period)
			);
		}
		
		$time = new DateTime();
		$time->sub(new DateInterval($period));
		
		$query = sprintf(
			'SELECT * FROM %s%s WHERE state = "%s" AND updated_at < "%s";',
			DB_PREFIX,
			static::getTableName(),
			self::STATE_CREATED,
			$time->format('Y-m-d H:i:s')
		);
		
		$db_result = static::query($query, $registry->get('db'));
		
		$result = [];
		if ($db_result->num_rows) {
			foreach ($db_result->rows as $row) {
				$result[] = new static($registry, $row);
			}
		}
		return $result;
	}

	/**
	 * Checks if there are any not sent jobs.
	 * Always checks all job types, not specific.
	 *
	 * @param \Registry $registry
	 * @param string $period
	 * @return bool
	 */
	public static function hasNotSent(\Registry $registry, string $period = 'PT10M'): bool {
		$time = new DateTime();
		$time->sub(new DateInterval($period));
		$timestamp = $time->format('Y-m-d H:i:s');
		
		$query = sprintf(
			'SELECT (
				EXISTS (SELECT id FROM %s%s WHERE state = "%s" AND updated_at < "%s" LIMIT 1)
				OR EXISTS (SELECT id FROM %s%s WHERE state = "%s" AND updated_at < "%s" LIMIT 1)
				OR EXISTS (SELECT id FROM %s%s WHERE state = "%s" AND updated_at < "%s" LIMIT 1)
			) AS pending_job;',
			DB_PREFIX, CompletionJob::getTableName(), self::STATE_CREATED, $timestamp,
			DB_PREFIX, VoidJob::getTableName(), self::STATE_CREATED, $timestamp,
			DB_PREFIX, RefundJob::getTableName(), self::STATE_CREATED, $timestamp
		);
		
		$db_result = static::query($query, $registry->get('db'));
		return isset($db_result->row['pending_job']) && (bool)$db_result->row['pending_job'];
	}

	/**
	 * Loads not sent jobs for an order.
	 *
	 * @param \Registry $registry
	 * @param int $order_id
	 * @return static
	 */
	public static function loadNotSentForOrder(\Registry $registry, int $order_id): static {
		$db = $registry->get('db');
		
		$query = sprintf(
			'SELECT * FROM %s%s WHERE order_id = %d AND state = "%s";',
			DB_PREFIX,
			static::getTableName(),
			$order_id,
			self::STATE_CREATED
		);
		
		$result = static::query($query, $db);
		if (isset($result->row) && !empty($result->row)) {
			return new static($registry, $result->row);
		}
		
		return new static($registry);
	}

	/**
	 * Loads running jobs for an order.
	 *
	 * @param \Registry $registry
	 * @param int $order_id
	 * @return static
	 */
	public static function loadRunningForOrder(\Registry $registry, int $order_id): static {
		$db = $registry->get('db');
		
		$query = sprintf(
			'SELECT * FROM %s%s WHERE order_id = %d AND state NOT IN ("%s", "%s", "%s", "%s");',
			DB_PREFIX,
			static::getTableName(),
			$order_id,
			self::STATE_CREATED,
			self::STATE_SUCCESS,
			self::STATE_FAILED_CHECK,
			self::STATE_FAILED_DONE
		);
		
		$result = static::query($query, $db);
		if (isset($result->row) && !empty($result->row)) {
			return new static($registry, $result->row);
		}
		
		return new static($registry);
	}

	/**
	 * Loads the oldest checkable job.
	 *
	 * @param \Registry $registry
	 * @return static
	 */
	public static function loadOldestCheckable(\Registry $registry): static {
		$db = $registry->get('db');
		
		$query = sprintf(
			'SELECT * FROM %s%s WHERE state = "%s" ORDER BY updated_at ASC LIMIT 1;',
			DB_PREFIX,
			static::getTableName(),
			self::STATE_FAILED_CHECK
		);
		
		$result = static::query($query, $db);
		if (isset($result->row) && !empty($result->row)) {
			return new static($registry, $result->row);
		}
		
		return new static($registry);
	}

	/**
	 * Counts all jobs for an order.
	 *
	 * @param \Registry $registry
	 * @param int $order_id
	 * @return int
	 */
	public static function countForOrder(\Registry $registry, int $order_id): int {
		$db = $registry->get('db');
		
		$query = sprintf(
			'SELECT COUNT(id) AS count FROM %s%s WHERE order_id = %d;',
			DB_PREFIX,
			static::getTableName(),
			$order_id
		);
		
		$db_result = static::query($query, $db);
		return (int)$db_result->row['count'];
	}

	/**
	 * Loads failed checked jobs for an order.
	 *
	 * @param \Registry $registry
	 * @param int $order_id
	 * @return array<static>
	 */
	public static function loadFailedCheckedForOrder(\Registry $registry, int $order_id): array {
		$db = $registry->get('db');
		
		$query = sprintf(
			'SELECT * FROM %s%s WHERE order_id = %d AND state = "%s";',
			DB_PREFIX,
			static::getTableName(),
			$order_id,
			self::STATE_FAILED_CHECK
		);
		
		$db_result = static::query($query, $db);
		
		$result = [];
		if ($db_result->num_rows) {
			foreach ($db_result->rows as $row) {
				$result[] = new static($registry, $row);
			}
		}
		return $result;
	}

	/**
	 * Marks failed jobs as done for an order.
	 *
	 * @param \Registry $registry
	 * @param int $order_id
	 * @return void
	 */
	public static function markFailedAsDone(\Registry $registry, int $order_id): void {
		$db = $registry->get('db');
		
		$query = sprintf(
			'UPDATE %s%s SET state = "%s" WHERE order_id = %d AND state = "%s";',
			DB_PREFIX,
			static::getTableName(),
			self::STATE_FAILED_DONE,
			$order_id,
			self::STATE_FAILED_CHECK
		);
		
		static::query($query, $db);
	}
}