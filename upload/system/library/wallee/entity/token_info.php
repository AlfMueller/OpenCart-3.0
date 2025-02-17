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
 * This entity holds data about a token on the gateway.
 *
 * @method int getId()
 * @method int getTokenId()
 * @method void setTokenId(int $id)
 * @method string getState()
 * @method void setState(string $state)
 * @method int getSpaceId()
 * @method void setSpaceId(int $id)
 * @method string getName()
 * @method void setName(string $name)
 * @method int getCustomerId()
 * @method void setCustomerId(int $id)
 * @method int getPaymentMethodId()
 * @method void setPaymentMethodId(int $id)
 * @method int getConnectorId()
 * @method void setConnectorId(int $id)
 */
class TokenInfo extends AbstractEntity {
	/**
	 * Returns the field definitions for the entity.
	 *
	 * @return array<string, string>
	 */
	protected static function getFieldDefinition(): array {
		return [
			'token_id' => ResourceType::INTEGER,
			'state' => ResourceType::STRING,
			'space_id' => ResourceType::INTEGER,
			'name' => ResourceType::STRING,
			'customer_id' => ResourceType::INTEGER,
			'payment_method_id' => ResourceType::INTEGER,
			'connector_id' => ResourceType::INTEGER
		];
	}

	/**
	 * Returns the table name for the entity.
	 *
	 * @return string
	 */
	protected static function getTableName(): string {
		return 'wallee_token_info';
	}

	/**
	 * Loads a token info by its token data.
	 *
	 * @param \Registry $registry
	 * @param int $space_id
	 * @param int $token_id
	 * @return static
	 */
	public static function loadByToken(\Registry $registry, int $space_id, int $token_id): static {
		$db = $registry->get('db');
		
		$query = sprintf(
			'SELECT * FROM %s%s WHERE space_id = %d AND token_id = %d;',
			DB_PREFIX,
			static::getTableName(),
			$space_id,
			$token_id
		);
		
		$db_result = static::query($query, $db);
		if (isset($db_result->row) && !empty($db_result->row)) {
			return new static($registry, $db_result->row);
		}
		
		return new static($registry);
	}
}