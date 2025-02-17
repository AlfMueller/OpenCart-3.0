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
 * Pseudo-Entity.
 * Provides static methods to interact with cron jobs.
 */
class Cron extends AbstractEntity {
	private const STATE_PENDING = 'pending';
	private const STATE_PROCESSING = 'processing';
	private const STATE_SUCCESS = 'success';
	private const STATE_ERROR = 'error';
	private const CONSTRAINT_PENDING = 0;
	private const CONSTRAINT_PROCESSING = -1;
	private const MAX_RUN_TIME_MINUTES = 10;
	private const TIMEOUT_MINUTES = 5;

	/**
	 * Returns the base fields for the entity.
	 *
	 * @return array<string, string>
	 */
	protected static function getBaseFields(): array {
		return [
			'id' => ResourceType::INTEGER
		];
	}

	/**
	 * Returns the table name for the entity.
	 *
	 * @return string
	 */
	protected static function getTableName(): string {
		return 'wallee_cron';
	}

	/**
	 * Returns the field definitions for the entity.
	 *
	 * @return array<string, string>
	 */
	protected static function getFieldDefinition(): array {
		return [
			'security_token' => ResourceType::STRING,
			'state' => ResourceType::STRING,
			'constraint_key' => ResourceType::INTEGER,
			'date_scheduled' => ResourceType::DATETIME,
			'date_started' => ResourceType::DATETIME,
			'date_completed' => ResourceType::DATETIME,
			'error_message' => ResourceType::STRING
		];
	}

	/**
	 * Sets a cron job to processing state.
	 *
	 * @param \Registry $registry
	 * @param string $security_token
	 * @return bool
	 */
	public static function setProcessing(\Registry $registry, string $security_token): bool {
		$db = $registry->get('db');
		$table = DB_PREFIX . self::getTableName();
		$constraint = self::CONSTRAINT_PROCESSING;
		$processing = self::STATE_PROCESSING;
		$pending = self::STATE_PENDING;
		$security_token = $db->escape($security_token);
		$time = (new DateTime())->format('Y-m-d H:i:s');
		
		$query = sprintf(
			"UPDATE %s SET constraint_key='%d', state='%s', date_started='%s' WHERE security_token='%s' AND state='%s';",
			$table,
			$constraint,
			$processing,
			$time,
			$security_token,
			$pending
		);
		self::query($query, $db);
		
		return $db->countAffected() === 1;
	}

	/**
	 * Sets a cron job to complete state.
	 *
	 * @param \Registry $registry
	 * @param string $security_token
	 * @param string|null $error
	 * @return bool
	 */
	public static function setComplete(\Registry $registry, string $security_token, ?string $error = null): bool {
		$db = $registry->get('db');
		$table = DB_PREFIX . self::getTableName();
		$processing = self::STATE_PROCESSING;
		$status = $error ? self::STATE_ERROR : self::STATE_SUCCESS;
		$error = $error ? $db->escape($error) : '';
		$security_token = $db->escape($security_token);
		$time = (new DateTime())->format('Y-m-d H:i:s');
		
		$query = sprintf(
			"UPDATE %s SET `constraint_key`=id, `state`='%s', date_completed='%s', `error_message`='%s' WHERE `security_token`='%s' AND `state`='%s';",
			$table,
			$status,
			$time,
			$error,
			$security_token,
			$processing
		);
		self::query($query, $db);
		
		return $db->countAffected() === 1;
	}

	/**
	 * Cleans up hanging cron jobs.
	 *
	 * @param \Registry $registry
	 * @return void
	 */
	public static function cleanUpHangingCrons(\Registry $registry): void {
		$db = $registry->get('db');
		\WalleeHelper::instance($registry)->dbTransactionStart();
		
		try {
			$timeout = (new DateTime())->sub(new DateInterval('PT' . self::TIMEOUT_MINUTES . 'M'))->format('Y-m-d H:i:s');
			$end_time = (new DateTime())->format('Y-m-d H:i:s');
			$table = DB_PREFIX . self::getTableName();
			
			$query = sprintf(
				"UPDATE %s SET constraint_key=id, `state`='%s', date_completed='%s', error_message='%s' WHERE `state`='%s' AND date_started<'%s';",
				$table,
				self::STATE_ERROR,
				$end_time,
				'Cron did not terminate correctly, timeout exceeded.',
				self::STATE_PROCESSING,
				$timeout
			);
			self::query($query, $db);
			\WalleeHelper::instance($registry)->dbTransactionCommit();
		} catch (\Exception $e) {
			\WalleeHelper::instance($registry)->dbTransactionRollback();
			\WalleeHelper::instance($registry)->log('Error clean up hanging cron: ' . $e->getMessage());
		}
	}

	/**
	 * Inserts a new pending cron job.
	 *
	 * @param \Registry $registry
	 * @return bool
	 */
	public static function insertNewPendingCron(\Registry $registry): bool {
		$db = $registry->get('db');
		\WalleeHelper::instance($registry)->dbTransactionStart();
		$table = DB_PREFIX . self::getTableName();
		
		try {
			$query = sprintf(
				"SELECT security_token FROM %s WHERE `state`='%s';",
				$table,
				self::STATE_PENDING
			);
			$result = self::query($query, $db);
			
			if ($result->num_rows === 1) {
				\WalleeHelper::instance($registry)->dbTransactionCommit();
				return false;
			}
			
			$uuid = \WalleeHelper::generateUuid();
			$time = (new DateTime())->add(new DateInterval('PT1M'))->format('Y-m-d H:i:s');
			
			$query = sprintf(
				"INSERT INTO %s (constraint_key, state, security_token, date_scheduled) VALUES ('%d', '%s', '%s', '%s');",
				$table,
				self::CONSTRAINT_PENDING,
				self::STATE_PENDING,
				$uuid,
				$time
			);
			self::query($query, $db);
			\WalleeHelper::instance($registry)->dbTransactionCommit();
			
			return $db->countAffected() === 1;
		} catch (\Exception $e) {
			\WalleeHelper::instance($registry)->dbTransactionRollback();
			return false;
		}
	}

	/**
	 * Returns the current token or false if no pending job is scheduled to run.
	 *
	 * @param \Registry $registry
	 * @return string|false
	 */
	public static function getCurrentSecurityTokenForPendingCron(\Registry $registry): string|false {
		try {
			$db = $registry->get('db');
			\WalleeHelper::instance($registry)->dbTransactionStart();
			$table = DB_PREFIX . self::getTableName();
			$now = (new DateTime())->format('Y-m-d H:i:s');
			
			$query = sprintf(
				"SELECT security_token FROM %s WHERE `state`='%s' AND date_scheduled<'%s';",
				$table,
				self::STATE_PENDING,
				$now
			);
			
			$result = self::query($query, $db);
			\WalleeHelper::instance($registry)->dbTransactionCommit();
			
			return $result->num_rows ? $result->row['security_token'] : false;
		} catch (\Exception $e) {
			\WalleeHelper::instance($registry)->dbTransactionRollback();
			return false;
		}
	}
	
	/**
	 * Removes all cron jobs older than 1 day which are completed (success / error).
	 *
	 * @param \Registry $registry
	 * @return bool
	 */
	public static function cleanUpCronDB(\Registry $registry): bool {
		try {
			$db = $registry->get('db');
			\WalleeHelper::instance($registry)->dbTransactionStart();
			$table = DB_PREFIX . self::getTableName();
			$cutoff = (new DateTime())->add(new DateInterval('P1D'))->format('Y-m-d H:i:s');
			
			$query = sprintf(
				"DELETE FROM %s WHERE `state`='%s' OR `state`='%s' AND `date_completed`<'%s';",
				$table,
				self::STATE_ERROR,
				self::STATE_SUCCESS,
				$cutoff
			);
			
			self::query($query, $db);
			\WalleeHelper::instance($registry)->dbTransactionCommit();
			return true;
		} catch (\Exception $e) {
			\WalleeHelper::instance($registry)->dbTransactionRollback();
			return false;
		}
	}
}