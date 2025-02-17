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
 * This entity represents a void job.
 */
class VoidJob extends AbstractJob {
	/**
	 * Returns the table name for the entity.
	 *
	 * @return string
	 */
	protected static function getTableName(): string {
		return 'wallee_void_job';
	}
}