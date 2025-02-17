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
 * Defines the different resource types used in entities.
 */
interface ResourceType {
	public const STRING = 'string';
	public const DATETIME = 'datetime';
	public const INTEGER = 'integer';
	public const BOOLEAN = 'boolean';
	public const OBJECT = 'object';
	public const DECIMAL = 'decimal';
}