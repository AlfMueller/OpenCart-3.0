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
 * This entity holds data about a transaction on the gateway.
 *
 * @method int getId()
 * @method int getTransactionId()
 * @method void setTransactionId(int $id)
 * @method string getState()
 * @method void setState(string $state)
 * @method int getSpaceId()
 * @method void setSpaceId(int $id)
 * @method int getSpaceViewId()
 * @method void setSpaceViewId(int $id)
 * @method string getLanguage()
 * @method void setLanguage(string $language)
 * @method string getCurrency()
 * @method void setCurrency(string $currency)
 * @method float getAuthorizationAmount()
 * @method void setAuthorizationAmount(float $amount)
 * @method string getImage()
 * @method void setImage(string $image)
 * @method array<string, string> getLabels()
 * @method void setLabels(array<string, string> $labels)
 * @method int getPaymentMethodId()
 * @method void setPaymentMethodId(int $id)
 * @method int getConnectorId()
 * @method void setConnectorId(int $id)
 * @method int getOrderId()
 * @method void setOrderId(int $id)
 * @method array<string, string>|null getFailureReason()
 * @method void setFailureReason(array<string, string> $reasons)
 * @method string|null getCouponCode()
 * @method void setCouponCode(string $coupon_code)
 * @method DateTime|null getLockedAt()
 * @method void setLockedAt(DateTime $locked_at)
 */
class TransactionInfo extends AbstractEntity {
	/**
	 * Returns the field definitions for the entity.
	 *
	 * @return array<string, string>
	 */
	protected static function getFieldDefinition(): array {
		return [
			'transaction_id' => ResourceType::INTEGER,
			'state' => ResourceType::STRING,
			'space_id' => ResourceType::INTEGER,
			'space_view_id' => ResourceType::INTEGER,
			'language' => ResourceType::STRING,
			'currency' => ResourceType::STRING,
			'authorization_amount' => ResourceType::DECIMAL,
			'image' => ResourceType::STRING,
			'labels' => ResourceType::OBJECT,
			'payment_method_id' => ResourceType::INTEGER,
			'connector_id' => ResourceType::INTEGER,
			'order_id' => ResourceType::INTEGER,
			'failure_reason' => ResourceType::OBJECT,
			'coupon_code' => ResourceType::STRING,
			'locked_at' => ResourceType::DATETIME
		];
	}

	/**
	 * Returns the table name for the entity.
	 *
	 * @return string
	 */
	protected static function getTableName(): string {
		return 'wallee_transaction_info';
	}

	/**
	 * Returns the translated failure reason.
	 *
	 * @param string|null $language
	 * @return string|null
	 */
	public function getFailureReason(?string $language = null): ?string {
		$value = $this->getValue('failure_reason');
		if (empty($value)) {
			return null;
		}
		
		return \WalleeHelper::instance($this->registry)->translate($value, $language);
	}

	/**
	 * Loads a transaction info by its order ID.
	 *
	 * @param \Registry $registry
	 * @param int $order_id
	 * @return static
	 */
	public static function loadByOrderId(\Registry $registry, int $order_id): static {
		$db = $registry->get('db');
		
		$query = sprintf(
			'SELECT * FROM %s%s WHERE order_id = %d;',
			DB_PREFIX,
			static::getTableName(),
			$order_id
		);
		
		$db_result = static::query($query, $db);
		if (isset($db_result->row) && !empty($db_result->row)) {
			return new static($registry, $db_result->row);
		}
		
		return new static($registry);
	}

	/**
	 * Loads a transaction info by its transaction data.
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