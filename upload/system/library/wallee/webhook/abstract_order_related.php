<?php
/**
 * Wallee OpenCart
 *
 * This OpenCart module enables to process payments with Wallee (wallee164).
 *
 * @package Whitelabelshortcut\Wallee
 * @author wallee144 (wallee164)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */

namespace Wallee\Webhook;

/**
 * Abstract webhook processor for order related entities.
 */
abstract class AbstractOrderRelated extends AbstractWebhook {

	/**
	 * Processes the received order related webhook request.
	 *
	 * @param Request $request
	 */
	public function process(Request $request){
		if ($request->getSpaceId() != $this->registry->get('config')->get('wallee_space_id')) {
			throw new \Exception("Received webhook with space id {$request->getSpaceId()} in store for space id  {$this->registry->get('config')->get('wallee_space_id')}.");
		}
		
		$entity = $this->loadEntity($request);
		$lock_acquired = false;
		$transaction_started = false;
		$lock_space_id = $request->getSpaceId();
		$lock_transaction_id = $this->getTransactionId($entity);
		try {
			\WalleeHelper::instance($this->registry)->dbWebhookLock($lock_space_id, $lock_transaction_id);
			$lock_acquired = true;
			\WalleeHelper::instance($this->registry)->dbTransactionStart();
			$transaction_started = true;

			$order_id = $this->getOrderId($entity);
			$this->registry->get('load')->model('checkout/order');
			$order_info = $this->registry->get('model_checkout_order')->getOrder($order_id);
			if ($order_info) {
				$transaction_info = \Wallee\Entity\TransactionInfo::loadByOrderId($this->registry, $order_id);
				if ($transaction_info->getTransactionId() !== $this->getTransactionId($entity)) {
					\WalleeHelper::instance($this->registry)->dbTransactionCommit();
					$transaction_started = false;
					return;
				}
				\WalleeHelper::instance($this->registry)->dbTransactionLock($lock_space_id, $lock_transaction_id);
				$this->processOrderRelatedInner($order_info, $entity);
			}
			
			\WalleeHelper::instance($this->registry)->dbTransactionCommit();
			$transaction_started = false;
		}
		catch (\Exception $e) {
			if ($transaction_started) {
				\WalleeHelper::instance($this->registry)->dbTransactionRollback();
			}
			throw $e;
		}
		finally {
			if ($lock_acquired) {
				\WalleeHelper::instance($this->registry)->dbWebhookUnlock($lock_space_id, $lock_transaction_id);
			}
		}
	}

	/**
	 * Loads and returns the entity for the webhook request.
	 *
	 * @param Request $request
	 * @return object
	 */
	abstract protected function loadEntity(Request $request);

	/**
	 * Returns the order id linked to the entity.
	 *
	 * @param object $entity
	 * @return string
	 */
	abstract protected function getOrderId($entity);

	/**
	 * Returns the transaction id linked to the entity
	 *
	 *
	 * @param object $entity
	 * @return int
	 */
	abstract protected function getTransactionId($entity);

	/**
	 * Actually processes the order related webhook request.
	 *
	 * This must be implemented
	 *
	 * @param array $order_info
	 * @param object $entity
	 */
	abstract protected function processOrderRelatedInner(array $order_info, $entity);
}
