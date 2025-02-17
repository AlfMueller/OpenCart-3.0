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

/**
 *
 * @method int getId()
 * @method DateTime getCreatedAt()
 * @method DateTime getUpdatedAt()
 *
 * @method void setId(int $id)
 * @method void setCreatedAt(DateTime $createdAt)
 * @method void setUpdatedAt(DateTime $updatedAt)
 *
 * Abstract implementation of a entity
 */
abstract class AbstractEntity {
	/**
	 * Entity data storage.
	 *
	 * @var array<string, mixed>
	 */
	protected array $data = [];

	/**
	 * OpenCart registry.
	 *
	 * @var \Registry
	 */
	protected \Registry $registry;

	/**
	 * Returns the base fields for all entities.
	 *
	 * @return array<string, string>
	 */
	protected static function getBaseFields(): array {
		return [
			'id' => ResourceType::INTEGER,
			'created_at' => ResourceType::DATETIME,
			'updated_at' => ResourceType::DATETIME
		];
	}

	/**
	 * Returns the field definitions for the entity.
	 *
	 * @return array<string, string>
	 * @throws \Exception When not implemented
	 */
	protected static function getFieldDefinition(): array {
		throw new \Exception("Mock abstract, must be overwritten.");
	}

	/**
	 * Returns the table name for the entity.
	 *
	 * @return string
	 * @throws \Exception When not implemented
	 */
	protected static function getTableName(): string {
		throw new \Exception("Mock abstract, must be overwritten.");
	}

	/**
	 * Gets a value from the entity data.
	 *
	 * @param string $variable_name
	 * @return mixed
	 */
	protected function getValue(string $variable_name): mixed {
		return $this->data[$variable_name] ?? null;
	}

	/**
	 * Sets a value in the entity data.
	 *
	 * @param string $variable_name
	 * @param mixed $value
	 */
	protected function setValue(string $variable_name, mixed $value): void {
		$this->data[$variable_name] = $value;
	}

	/**
	 * Checks if a value exists in the entity data.
	 *
	 * @param string $variable_name
	 * @return bool
	 */
	protected function hasValue(string $variable_name): bool {
		return array_key_exists($variable_name, $this->data);
	}

	/**
	 * Executes a database query with error handling.
	 *
	 * @param string $query
	 * @param object $db
	 * @return object
	 */
	protected static function query(string $query, object $db): object {
		set_error_handler('WalleeHelper::exceptionErrorHandler');
		try {
			$result = $db->query($query);
			restore_error_handler();
			return $result;
		} catch (\Exception $e) {
			restore_error_handler();
			throw $e;
		}
	}

	/**
	 * Magic method to handle getters and setters.
	 *
	 * @param string $name
	 * @param array<mixed> $arguments
	 * @return mixed
	 */
	public function __call(string $name, array $arguments): mixed {
		$variable_name = substr($name, 3);
		
		$cleaned = '';
		// first character should be upper
		for ($i = 0; $i < strlen($variable_name); $i++) {
			if (ctype_upper($variable_name[$i])) {
				$cleaned .= '_';
			}
			$cleaned .= $variable_name[$i];
		}
		$variable_name = substr(strtolower($cleaned), 1);
		
		if (0 === strpos($name, 'get')) {
			return $this->getValue($variable_name);
		}
		elseif (0 === strpos($name, 'set')) {
			$this->setValue($variable_name, $arguments[0]);
			return $this;
		}
		elseif (0 === strpos($name, 'has')) {
			return $this->hasValue($variable_name);
		}
		
		throw new \BadMethodCallException(sprintf('Method %s does not exist', $name));
	}

	/**
	 * Constructor.
	 *
	 * @param \Registry $registry
	 * @param array<string, mixed> $data
	 * @throws \Exception When registry is not provided
	 */
	public function __construct(\Registry $registry, array $data = []) {
		$this->registry = $registry;
		
		if (!empty($data)) {
			$this->fillValuesFromDb($data);
		}
	}

	/**
	 * Fills entity values from database data.
	 *
	 * @param array<string, mixed> $db_values
	 * @throws \Exception When an unsupported variable type is encountered
	 */
	protected function fillValuesFromDb(array $db_values): void {
		$fields = array_merge(static::getBaseFields(), static::getFieldDefinition());
		foreach ($fields as $key => $type) {
			if (isset($db_values[$key])) {
				$value = $db_values[$key];
				$value = match ($type) {
					ResourceType::STRING => $value,
					ResourceType::BOOLEAN => $value === 'Y',
					ResourceType::INTEGER => (int)$value,
					ResourceType::DECIMAL => (float)$value,
					ResourceType::DATETIME => new DateTime($value),
					ResourceType::OBJECT => unserialize($value),
					default => throw new \Exception('Unsupported variable type: ' . $type),
				};
				$this->setValue($key, $value);
			}
		}
	}

	/**
	 * Saves the entity to the database.
	 *
	 * @throws \Exception When an unsupported variable type is encountered
	 */
	public function save(): void {
		$db = $this->registry->get('db');
		$data_array = [];
		
		foreach (static::getFieldDefinition() as $key => $type) {
			$value = $this->getValue($key);
			if ($value === null) {
				continue;
			}

			$value = match ($type) {
				ResourceType::STRING => $value,
				ResourceType::BOOLEAN => $value ? 'Y' : 'N',
				ResourceType::INTEGER => $value,
				ResourceType::DATETIME => $value instanceof DateTime ? $value->format('Y-m-d H:i:s') : $value,
				ResourceType::OBJECT => serialize($value),
				ResourceType::DECIMAL => number_format((float)$value, 8, '.', ''),
				default => throw new \Exception('Unsupported variable type: ' . $type),
			};
			$data_array[$key] = $value;
		}

		$current_time = date('Y-m-d H:i:s');
		$data_array['updated_at'] = $current_time;
		
		if ($this->getId() === null) {
			$data_array['created_at'] = $current_time;
		}
		
		$values_query = [];
		foreach ($data_array as $key => $value) {
			$escaped_value = $value === null ? 'NULL' : '"' . $db->escape($value) . '"';
			$values_query[] = sprintf('`%s`=%s', $key, $escaped_value);
		}
		
		$table = DB_PREFIX . static::getTableName();
		$values_string = implode(',', $values_query);
		
		if ($this->getId() === null) {
			$query = sprintf('INSERT INTO %s SET %s;', $table, $values_string);
			static::query($query, $db);
			$this->setId((int)$db->getLastId());
		} else {
			$query = sprintf('UPDATE %s SET %s WHERE id = %d;', $table, $values_string, $this->getId());
			static::query($query, $db);
		}
	}

	/**
	 * Loads an entity by its ID.
	 *
	 * @param \Registry $registry
	 * @param int $id
	 * @return static
	 */
	public static function loadById(\Registry $registry, int $id): static {
		$result = static::query(
			sprintf(
				'SELECT * FROM %s%s WHERE id = %d;',
				DB_PREFIX,
				static::getTableName(),
				$id
			),
			$registry->get('db')
		);
		
		if (isset($result->row) && !empty($result->row)) {
			return new static($registry, $result->row);
		}
		
		return new static($registry);
	}

	/**
	 * Loads all entities.
	 *
	 * @param \Registry $registry
	 * @return array<static>
	 */
	public static function loadAll(\Registry $registry): array {
		$db_result = static::query(
			sprintf(
				'SELECT * FROM %s%s;',
				DB_PREFIX,
				static::getTableName()
			),
			$registry->get('db')
		);
		
		$result = [];
		foreach ($db_result->rows as $row) {
			$result[] = new static($registry, $row);
		}
		return $result;
	}

	/**
	 * Gets the default filter value.
	 *
	 * @param array<string, mixed> $filters
	 * @param string $filterName
	 * @param mixed $default
	 * @return mixed
	 */
	private static function getDefaultFilter(array &$filters, string $filterName, mixed $default): mixed {
		if (!isset($filters[$filterName])) {
			$filters[$filterName] = $default;
		}
		return $filters[$filterName];
	}

	/**
	 * Builds the WHERE clause for a query.
	 *
	 * @param object $db
	 * @param array<string, mixed> $filters
	 * @return string
	 */
	private static function buildWhereClause(object $db, array $filters): string {
		$where = [];
		
		foreach ($filters as $key => $value) {
			if ($value === null) {
				continue;
			}
			
			if (static::isDateField($key)) {
				if (isset($value['from'])) {
					$where[] = sprintf(
						'`%s` >= "%s"',
						$db->escape($key),
						$db->escape($value['from']->format('Y-m-d H:i:s'))
					);
				}
				if (isset($value['to'])) {
					$where[] = sprintf(
						'`%s` <= "%s"',
						$db->escape($key),
						$db->escape($value['to']->format('Y-m-d H:i:s'))
					);
				}
			} else {
				$where[] = sprintf(
					'`%s` = "%s"',
					$db->escape($key),
					$db->escape($value)
				);
			}
		}
		
		return empty($where) ? '' : ' WHERE ' . implode(' AND ', $where);
	}

	/**
	 * Loads entities by filters.
	 *
	 * @param \Registry $registry
	 * @param array<string, mixed> $filters
	 * @return array<static>
	 */
	public static function loadByFilters(\Registry $registry, array $filters): array {
		$db = $registry->get('db');
		
		$order_by = static::getDefaultFilter($filters, 'order_by', 'id');
		$order_way = static::getDefaultFilter($filters, 'order_way', 'DESC');
		$page = (int)static::getDefaultFilter($filters, 'page', 1);
		$limit = (int)static::getDefaultFilter($filters, 'limit', 50);
		
		unset($filters['order_by'], $filters['order_way'], $filters['page'], $filters['limit']);
		
		$query = sprintf(
			'SELECT * FROM %s%s%s ORDER BY `%s` %s LIMIT %d, %d;',
			DB_PREFIX,
			static::getTableName(),
			static::buildWhereClause($db, $filters),
			$db->escape($order_by),
			$order_way,
			($page - 1) * $limit,
			$limit
		);
		
		$db_result = static::query($query, $db);
		
		$result = [];
		foreach ($db_result->rows as $row) {
			$result[] = new static($registry, $row);
		}
		return $result;
	}

	/**
	 * Counts the total number of rows.
	 *
	 * @param \Registry $registry
	 * @return int
	 */
	public static function countRows(\Registry $registry): int {
		$result = static::query(
			sprintf(
				'SELECT COUNT(*) AS count FROM %s%s;',
				DB_PREFIX,
				static::getTableName()
			),
			$registry->get('db')
		);
		return (int)$result->row['count'];
	}

	/**
	 * Deletes the entity.
	 *
	 * @param \Registry $registry
	 * @return void
	 */
	public function delete(\Registry $registry): void {
		if ($this->getId() === null) {
			return;
		}

		static::query(
			sprintf(
				'DELETE FROM %s%s WHERE id = %d;',
				DB_PREFIX,
				static::getTableName(),
				$this->getId()
			),
			$registry->get('db')
		);
	}

	/**
	 * Checks if a field is a date field.
	 *
	 * @param string $field
	 * @return bool
	 */
	protected static function isDateField(string $field): bool {
		return in_array($field, [
			'created_at',
			'updated_at'
		], true);
	}
}