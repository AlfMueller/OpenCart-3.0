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
 * This entity represents a completion job.
 *
 * @method void setAmount(float $amount)
 * @method float getAmount()
 */
class CompletionJob extends AbstractJob {
	/**
	 * Returns the field definitions for the entity.
	 *
	 * @return array<string, string>
	 */
	protected static function getFieldDefinition(): array {
		return array_merge(
			parent::getFieldDefinition(),
			[
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
		return 'wallee_completion_job';
	}
}