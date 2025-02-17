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
 * This entity holds shipping information for a transaction.
 *
 * @method int getId()
 * @method void setTransactionId(int $id)
 * @method int getTransactionId()
 * @method void setSpaceId(int $id)
 * @method int getSpaceId()
 * @method void setTaxClassId(int $id)
 * @method int getTaxClassId()
 * @method void setCost(float $cost)
 * @method float getCost()
 */
class ShippingInfo extends AbstractEntity {
	/**
	 * Returns the field definitions for the entity.
	 *
	 * @return array<string, string>
	 */
	protected static function getFieldDefinition(): array {
		return [
			'transaction_id' => ResourceType::INTEGER,
			'space_id' => ResourceType::INTEGER,
			'cost' => ResourceType::DECIMAL,
			'tax_class_id' => ResourceType::INTEGER
		];
	}

	/**
	 * Returns the table name for the entity.
	 *
	 * @return string
	 */
	protected static function getTableName(): string {
		return 'wallee_shipping_info';
	}

	/**
	 * Loads shipping information by transaction data.
	 *
	 * @param \Registry $registry
	 * @param int $space_id
	 * @param int $transaction_id
	 * @return static
	 */
	public static function loadByTransaction(\Registry $registry, int $space_id, int $transaction_id): static {
		$db = $registry->get('db');
		
		$query = sprintf(
			'SELECT * FROM %s%s WHERE space_id = %d AND transaction_id = %d;',
			DB_PREFIX,
			static::getTableName(),
			$space_id,
			$transaction_id
		);
		
		$db_result = static::query($query, $db);
		if (isset($db_result->row) && !empty($db_result->row)) {
			return new static($registry, $db_result->row);
		}
		
		return new static($registry);
	}
}