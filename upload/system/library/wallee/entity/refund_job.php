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

use Wallee\Sdk\Model\LineItemReduction;

/**
 * This entity holds data about a refund job.
 *
 * @method string getExternalId()
 * @method void setExternalId(string $id)
 * @method LineItemReduction[] getReductionItems()
 * @method void setReductionItems(LineItemReduction[] $reductions)
 * @method float getAmount()
 * @method void setAmount(float $amount)
 * @method array<string, string> getFailureReason()
 * @method void setFailureReason(array<string, string> $reasons)
 * @method bool getRestock()
 * @method void setRestock(bool $restock)
 */
class RefundJob extends AbstractJob {
	public const STATE_PENDING = 'PENDING';
	public const STATE_MANUAL_CHECK = 'MANUAL_CHECK';

	/**
	 * Returns the field definitions for the entity.
	 *
	 * @return array<string, string>
	 */
	protected static function getFieldDefinition(): array {
		return array_merge(
			parent::getFieldDefinition(),
			[
				'external_id' => ResourceType::STRING,
				'restock' => ResourceType::BOOLEAN,
				'reduction_items' => ResourceType::OBJECT,
				'amount' => ResourceType::DECIMAL
			]
		);
	}

	/**
	 * Returns the table name for the entity.
	 *
	 * @return string
	 */
	protected static function getTableName(): string {
		return 'wallee_refund_job';
	}

	/**
	 * Calculates the sum of all refunded amounts for an order.
	 *
	 * @param \Registry $registry
	 * @param int $order_id
	 * @return float
	 */
	public static function sumRefundedAmount(\Registry $registry, int $order_id): float {
		$db = $registry->get('db');
		
		$query = sprintf(
			'SELECT SUM(amount) AS total FROM %s%s WHERE order_id = %d AND state = "%s";',
			DB_PREFIX,
			static::getTableName(),
			$order_id,
			self::STATE_SUCCESS
		);
		
		$db_result = static::query($query, $db);
		return isset($db_result->row['total']) ? (float)$db_result->row['total'] : 0.0;
	}

	/**
	 * Counts transactions with PENDING & MANUAL_CHECK status.
	 *
	 * @param \Registry $registry
	 * @param int $space_id
	 * @return int
	 */
	public static function countPending(\Registry $registry, int $space_id): int {
		$db = $registry->get('db');
		
		$query = sprintf(
			'SELECT COUNT(id) AS count FROM %s%s WHERE space_id = %d AND state IN ("%s", "%s");',
			DB_PREFIX,
			static::getTableName(),
			$space_id,
			self::STATE_PENDING,
			self::STATE_MANUAL_CHECK
		);
		
		$db_result = static::query($query, $db);
		return isset($db_result->row['count']) ? (int)$db_result->row['count'] : 0;
	}

	/**
	 * Loads a refund job by its external ID.
	 *
	 * @param \Registry $registry
	 * @param int $space_id
	 * @param string $external_id
	 * @return static
	 */
	public static function loadByExternalId(\Registry $registry, int $space_id, string $external_id): static {
		$db = $registry->get('db');
		
		$query = sprintf(
			'SELECT * FROM %s%s WHERE space_id = %d AND external_id = "%s";',
			DB_PREFIX,
			static::getTableName(),
			$space_id,
			$db->escape($external_id)
		);
		
		$db_result = static::query($query, $db);
		if (isset($db_result->row) && !empty($db_result->row)) {
			return new static($registry, $db_result->row);
		}
		
		return new static($registry);
	}
}