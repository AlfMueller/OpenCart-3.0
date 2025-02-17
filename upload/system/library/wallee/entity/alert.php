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

/**
 * This entity represents an alert in the system.
 *
 * @method string getRoute()
 * @method void setRoute(string $route)
 * @method string getKey()
 * @method void setKey(string $key)
 * @method string getLevel()
 * @method void setLevel(string $level)
 * @method int getCount()
 * @method void setCount(int $count)
 */
class Alert extends AbstractEntity {
	public const KEY_MANUAL_TASK = 'manual_task';
	public const KEY_FAILED_JOB = 'failed_jobs';

	/**
	 * Returns the table name for the entity.
	 *
	 * @return string
	 */
	protected static function getTableName(): string {
		return 'wallee_alert';
	}

	/**
	 * Returns the field definitions for the entity.
	 *
	 * @return array<string, string>
	 */
	protected static function getFieldDefinition(): array {
		return [
			'key' => ResourceType::STRING,
			'route' => ResourceType::STRING,
			'level' => ResourceType::STRING,
			'count' => ResourceType::INTEGER
		];
	}

	/**
	 * Modifies the entity's count by the given parameter.
	 * The parameter may be negative or positive.
	 *
	 * @param int $count The count to add (or subtract if negative)
	 * @return void
	 */
	public function modifyCount(int $count): void {
		$new_count = $this->getCount() + $count;
		if ($new_count < 0) {
			$new_count = 0;
		}
		$this->setCount($new_count);
		$this->save();
	}

	/**
	 * Loads the manual task alert.
	 *
	 * @param \Registry $registry
	 * @return static
	 */
	public static function loadManualTask(\Registry $registry): static {
		return static::loadByKey($registry, self::KEY_MANUAL_TASK);
	}

	/**
	 * Loads the failed jobs alert.
	 *
	 * @param \Registry $registry
	 * @return static
	 */
	public static function loadFailedJobs(\Registry $registry): static {
		return static::loadByKey($registry, self::KEY_FAILED_JOB);
	}

	/**
	 * Loads an alert by its key.
	 *
	 * @param \Registry $registry
	 * @param string $key
	 * @return static
	 */
	protected static function loadByKey(\Registry $registry, string $key): static {
		$db = $registry->get('db');
		
		$query = sprintf(
			'SELECT * FROM %s%s WHERE `key` = "%s";',
			DB_PREFIX,
			static::getTableName(),
			$db->escape($key)
		);
		
		$db_result = static::query($query, $db);
		if (isset($db_result->row) && !empty($db_result->row)) {
			return new static($registry, $db_result->row);
		}
		
		return new static($registry);
	}
}