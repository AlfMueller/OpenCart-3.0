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
 * This entity holds data about a Wallee payment method.
 *
 * @method int getId()
 * @method string getState()
 * @method void setState(string $state)
 * @method int getSpaceId()
 * @method void setSpaceId(int $id)
 * @method int getConfigurationId()
 * @method void setConfigurationId(int $id)
 * @method string getConfigurationName()
 * @method void setConfigurationName(string $name)
 * @method array<string, string> getTitle()
 * @method void setTitle(array<string, string> $title)
 * @method array<string, string> getDescription()
 * @method void setDescription(array<string, string> $description)
 * @method string getImage()
 * @method void setImage(string $image)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 */
class MethodConfiguration extends AbstractEntity {
	public const STATE_ACTIVE = 'active';
	public const STATE_INACTIVE = 'inactive';
	public const STATE_HIDDEN = 'hidden';

	/**
	 * Returns the field definitions for the entity.
	 *
	 * @return array<string, string>
	 */
	protected static function getFieldDefinition(): array {
		return [
			'state' => ResourceType::STRING,
			'space_id' => ResourceType::INTEGER,
			'configuration_id' => ResourceType::INTEGER,
			'configuration_name' => ResourceType::STRING,
			'sort_order' => ResourceType::INTEGER,
			'title' => ResourceType::OBJECT,
			'description' => ResourceType::OBJECT,
			'image' => ResourceType::STRING
		];
	}

	/**
	 * Returns the table name for the entity.
	 *
	 * @return string
	 */
	protected static function getTableName(): string {
		return 'wallee_method_configuration';
	}

	/**
	 * Loads a method configuration by its configuration data.
	 *
	 * @param \Registry $registry
	 * @param int $space_id
	 * @param int $configuration_id
	 * @return static
	 */
	public static function loadByConfiguration(\Registry $registry, int $space_id, int $configuration_id): static {
		$db = $registry->get('db');
		
		$query = sprintf(
			'SELECT * FROM %s%s WHERE space_id = %d AND configuration_id = %d;',
			DB_PREFIX,
			static::getTableName(),
			$space_id,
			$configuration_id
		);
		
		$result = static::query($query, $db);
		if (isset($result->row) && !empty($result->row)) {
			return new static($registry, $result->row);
		}
		
		return new static($registry);
	}

	/**
	 * Loads all method configurations for a space.
	 *
	 * @param \Registry $registry
	 * @param int $space_id
	 * @return array<static>
	 */
	public static function loadBySpaceId(\Registry $registry, int $space_id): array {
		$db = $registry->get('db');
		
		$query = sprintf(
			'SELECT * FROM %s%s WHERE space_id = %d;',
			DB_PREFIX,
			static::getTableName(),
			$space_id
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
}