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

namespace Wallee\Service;

use Wallee\Sdk\Service\ChargeAttemptService;
use Wallee\Sdk\Service\TransactionService;
use Wallee\Sdk\Service\TransactionIframeService;
use Wallee\Sdk\Service\TransactionPaymentPageService;
use Wallee\Sdk\Model\Transaction as TransactionModel;
use Wallee\Sdk\Model\TransactionState;
use Wallee\Sdk\Model\CustomersPresence;
use Wallee\Sdk\Model\AbstractTransactionPending;
use Wallee\Sdk\Model\TransactionCreate;
use Wallee\Sdk\Model\TransactionPending;

/**
 * This service provides functions to deal with Wallee transactions.
 *
 * It generally provides three ways of creating & updating transactions.
 * 1) With total & address, for filtering active payment methods.
 * 2) With session data, before the order has been persisted in the database. For getting javascript url & confirming the order.
 * 3) With the order information, after the order has been completed. For backend operations.
 */
class Transaction extends AbstractService {

	/**
	 * Gets the available payment methods for the given order.
	 *
	 * @param array<string, mixed> $order_info The order information
	 * @return array<\Wallee\Sdk\Model\PaymentMethodConfiguration>
	 * @throws \Wallee\Sdk\ApiException If the API call fails
	 * @throws \RuntimeException If the payment methods cannot be retrieved
	 */
	public function getPaymentMethods(array $order_info): array {
		$sessionId = \WalleeHelper::instance($this->registry)->getCustomerSessionIdentifier();
		if (!$sessionId || !array_key_exists($sessionId, self::$possible_payment_method_cache)) {
			$transaction = $this->update($order_info, false);
			try {
				$payment_methods = $this->getTransactionService()->fetchPaymentMethods(
					$transaction->getLinkedSpaceId(),
					$transaction->getId(),
					'iframe'
				);
				foreach ($payment_methods as $payment_method) {
					MethodConfiguration::instance($this->registry)->updateData($payment_method);
				}
				self::$possible_payment_method_cache[$sessionId] = $payment_methods;
			}
			catch (\Exception $e) {
				self::$possible_payment_method_cache[$sessionId] = [];
				throw $e;
			}
		}
		return self::$possible_payment_method_cache[$sessionId];
	}

	/**
	 * Gets the JavaScript URL for the transaction iframe.
	 *
	 * @return string The JavaScript URL
	 * @throws \Wallee\Sdk\ApiException If the API call fails
	 * @throws \RuntimeException If the URL cannot be retrieved
	 */
	public function getJavascriptUrl(): string {
		$transaction = $this->getTransaction([], false, [
			TransactionState::PENDING 
		]);
		$this->persist($transaction, []);
		return $this->getIframeService()->javascriptUrl($transaction->getLinkedSpaceId(), $transaction->getId());
	}

	/**
	 * Gets the payment page URL for the given transaction and payment code.
	 *
	 * @param TransactionModel $transaction The transaction
	 * @param string $paymentCode The payment code
	 * @return string The payment page URL
	 * @throws \RuntimeException If the URL cannot be retrieved
	 */
	public function getPaymentPageUrl(TransactionModel $transaction, string $paymentCode): string {
		$paymentMethodId = \WalleeHelper::extractPaymentMethodId($paymentCode);
		return $this->getPaymentPageService()->paymentPageUrl($transaction->getLinkedSpaceId(), $transaction->getId()) .
				 '&paymentMethodConfigurationId=' . $paymentMethodId;
	}
	
	/**
	 * Gets the allowed payment method configurations for the given order.
	 *
	 * @param array<string, mixed> $order_info The order information
	 * @return array<int>|null The allowed payment method configuration IDs or null if not restricted
	 */
	protected function getAllowedPaymentMethodConfigurations(array $order_info): ?array {
		if(isset($order_info['payment_method']) && isset($order_info['payment_method']['code'])){
			return [\WalleeHelper::extractPaymentMethodId($order_info['payment_method']['code'])];
		}
		return null;
	}

	/**
	 * Updates an existing transaction or creates a new one.
	 *
	 * @param array<string, mixed> $order_info The order information
	 * @param bool $confirm Whether to confirm the transaction
	 * @return TransactionModel The updated or created transaction
	 * @throws \Wallee\Sdk\ApiException If the API call fails
	 * @throws \RuntimeException If the transaction cannot be updated or created
	 */
	public function update(array $order_info, bool $confirm = false): TransactionModel {
		$last = null;
		try {
			for ($i = 0; $i < 5; $i++) {
				$transaction = $this->getTransaction($order_info, false);
				if ($transaction->getState() !== TransactionState::PENDING) {
					if ($confirm) {
						throw new \RuntimeException('No pending transaction available to be confirmed.');
					}
					return $this->create($order_info);
				}
				
				$pending_transaction = new TransactionPending();
				$pending_transaction->setId($transaction->getId());
				$pending_transaction->setVersion($transaction->getVersion());
				$this->assembleTransaction($pending_transaction, $order_info);
				
				if ($confirm) {
					$pending_transaction->setAllowedPaymentMethodConfigurations($this->getAllowedPaymentMethodConfigurations($order_info));
					$transaction = $this->getTransactionService()->confirm($transaction->getLinkedSpaceId(), $pending_transaction);
					$this->clearTransactionInSession();
				}
				else {
					$transaction = $this->getTransactionService()->update($transaction->getLinkedSpaceId(), $pending_transaction);
				}
				
				$this->persist($transaction, $order_info);
				
				return $transaction;
			}
		}
		catch (\Wallee\Sdk\ApiException $e) {
			$last = $e;
			if ($e->getCode() !== 409) {
				throw $e;
			}
		}
		
		throw $last;
	}

	/**
	 * Wait for the order to reach a given state.
	 *
	 * @param int $order_id The order ID
	 * @param array<string> $states The states to wait for
	 * @param int $maxWaitTime Maximum wait time in seconds
	 * @return bool True if the order reached one of the states, false if timeout
	 * @throws \RuntimeException If the order information cannot be loaded
	 */
	public function waitForStates(int $order_id, array $states, int $maxWaitTime = 10): bool {
		$startTime = microtime(true);
		while (true) {
			$transactionInfo = \Wallee\Entity\TransactionInfo::loadByOrderId($this->registry, $order_id);
			if (in_array($transactionInfo->getState(), $states, true)) {
				return true;
			}
			
			if (microtime(true) - $startTime >= $maxWaitTime) {
				return false;
			}
			sleep(1);
		}
	}

	/**
	 * Reads or creates a new transaction.
	 *
	 * @param array<string, mixed> $order_info The order information
	 * @param bool $cache Whether to use cached transaction
	 * @param array<string> $allowed_states The allowed transaction states
	 * @return TransactionModel The transaction
	 * @throws \RuntimeException If the transaction cannot be loaded or created
	 */
	public function getTransaction(array $order_info = [], bool $cache = true, array $allowed_states = []): TransactionModel {
		$sessionId = \WalleeHelper::instance($this->registry)->getCustomerSessionIdentifier();
		
		if ($sessionId && isset(self::$transaction_cache[$sessionId]) && $cache) {
			return self::$transaction_cache[$sessionId];
		}
		
		$create = true;
		
		// attempt to load via session variables
		if ($this->hasTransactionInSession()) {
			self::$transaction_cache[$sessionId] = $this->getTransactionService()->read($this->getSessionSpaceId(), $this->getSessionTransactionId());
			// check if the status is expected
			$create = empty($allowed_states) ? false : !in_array(self::$transaction_cache[$sessionId]->getState(), $allowed_states, true);
		}
		
		// attempt to load via order id (existing transaction_info)
		if (isset($order_info['order_id']) && $create) {
			$transaction_info = \Wallee\Entity\TransactionInfo::loadByOrderId($this->registry, $order_info['order_id']);
			if ($transaction_info->getId() && $transaction_info->getState() === 'PENDING') {
				self::$transaction_cache[$sessionId] = $this->getTransactionService()->read($transaction_info->getSpaceId(),
						$transaction_info->getTransactionId());
				$create = empty($allowed_states) ? false : !in_array(self::$transaction_cache[$sessionId]->getState(), $allowed_states, true);
			}
			if ($create) {
				throw new \RuntimeException('Order ID was already used.');
			}
		}
		
		// no applicable transaction found, create new one.
		if ($create) {
			self::$transaction_cache[$sessionId] = $this->create($order_info);
		}
		
		return self::$transaction_cache[$sessionId];
	}

	/**
	 * Persists the transaction data.
	 *
	 * @param TransactionModel $transaction The transaction to persist
	 * @param array<string, mixed> $order_info The order information
	 */
	private function persist(TransactionModel $transaction, array $order_info): void {
		if (isset($order_info['order_id'])) {
			$this->updateTransactionInfo($transaction, $order_info['order_id']);
		}
		$this->storeTransactionIdsInSession($transaction);
		$this->storeShipping($transaction);
	}

	/**
	 * Creates a new transaction.
	 *
	 * @param array<string, mixed> $order_info The order information
	 * @return TransactionModel The created transaction
	 * @throws \RuntimeException If the transaction cannot be created
	 */
	private function create(array $order_info): TransactionModel {
		$create_transaction = new TransactionCreate();
		
		$create_transaction->setCustomersPresence(CustomersPresence::VIRTUAL_PRESENT);
		if (isset($this->registry->get('request')->cookie['wallee_device_id'])) {
			$create_transaction->setDeviceSessionIdentifier($this->registry->get('request')->cookie['wallee_device_id']);
		}
		
		$create_transaction->setAutoConfirmationEnabled(false);
		$create_transaction->setChargeRetryEnabled(false);
		$this->assembleTransaction($create_transaction, $order_info);
		$transaction = $this->getTransactionService()->create($this->registry->get('config')->get('wallee_space_id'),
				$create_transaction);
		
		$this->persist($transaction, $order_info);
		
		return $transaction;
	}

	/**
	 * Assembles the transaction data.
	 *
	 * @param AbstractTransactionPending $transaction The transaction to assemble
	 * @param array<string, mixed> $order_info The order information
	 * @throws \RuntimeException If required data is missing
	 */
	private function assembleTransaction(AbstractTransactionPending $transaction, array $order_info): void {
		$order_id = isset($order_info['order_id']) ? $order_info['order_id'] : null;
		$data = $this->registry->get('session')->data;
		
		if (isset($data['currency'])) {
			$transaction->setCurrency($data['currency']);
		}
		else {
			throw new \RuntimeException('Session currency not set.');
		}
		
		$transaction->setBillingAddress(
				$this->assembleAddress(\WalleeHelper::instance($this->registry)->getAddress('payment', $order_info)));
		if ($this->registry->get('cart')->hasShipping()) {
			$transaction->setShippingAddress(
					$this->assembleAddress(\WalleeHelper::instance($this->registry)->getAddress('shipping', $order_info)));
		}
		
		$customer = \WalleeHelper::instance($this->registry)->getCustomer();
		if (isset($customer['customer_id'])) {
			$transaction->setCustomerId($customer['customer_id']);
		}
		if (isset($customer['customer_email'])) {
			$transaction->setCustomerEmailAddress($this->getFixedSource($customer, 'customer_email', 150));
		}
		else if (isset($customer['email'])) {
			$transaction->setCustomerEmailAddress($this->getFixedSource($customer, 'email', 150));
		}
		
		$transaction->setLanguage(\WalleeHelper::instance($this->registry)->getCleanLanguageCode());
		if (isset($data['shipping_method'])) {
			$transaction->setShippingMethod($this->fixLength($data['shipping_method']['title'], 200));
		}
		
		$transaction->setLineItems(LineItem::instance($this->registry)->getItemsFromSession());
		$transaction->setSuccessUrl(\WalleeHelper::instance($this->registry)->getSuccessUrl());

		if ($order_id) {
			$transaction->setMerchantReference($order_id);
			$transaction->setFailedUrl(\WalleeHelper::instance($this->registry)->getFailedUrl($order_id));
		}
	}
	
	/**
	 * Cache for cart transactions.
	 *
	 * @var array<string, TransactionModel>
	 */
	private static array $transaction_cache = [];
	
	/**
	 * Cache for possible payment methods by cart.
	 *
	 * @var array<string, array<\Wallee\Sdk\Model\PaymentMethodConfiguration>>
	 */
	private static array $possible_payment_method_cache = [];
	
	/**
	 * The transaction API service.
	 */
	private ?TransactionService $transaction_service = null;
	
	/**
	 * The charge attempt API service.
	 */
	private ?ChargeAttemptService $charge_attempt_service = null;
	
	/**
	 * The iframe API service, to retrieve JS url
	 */
	private ?TransactionIframeService $transaction_iframe_service = null;
	
	/**
	 * The payment page API service, tro retrieve pp URL
	 */
	private ?TransactionPaymentPageService $transaction_payment_page_service = null;

	/**
	 * Returns the transaction API service.
	 *
	 * @return TransactionService
	 */
	private function getTransactionService(): TransactionService {
		if ($this->transaction_service === null) {
			$this->transaction_service = new TransactionService(\WalleeHelper::instance($this->registry)->getApiClient());
		}
		return $this->transaction_service;
	}

	/**
	 * Returns the charge attempt API service.
	 *
	 * @return ChargeAttemptService
	 */
	private function getChargeAttemptService(): ChargeAttemptService {
		if ($this->charge_attempt_service === null) {
			$this->charge_attempt_service = new ChargeAttemptService(\WalleeHelper::instance($this->registry)->getApiClient());
		}
		return $this->charge_attempt_service;
	}
	
	/**
	 * Returns the transaction iframe API service.
	 *
	 * @return TransactionIframeService
	 */
	private function getIframeService(): TransactionIframeService {
		if ($this->transaction_iframe_service === null) {
			$this->transaction_iframe_service = new TransactionIframeService(\WalleeHelper::instance($this->registry)->getApiClient());
		}
		return $this->transaction_iframe_service;
	}
	
	/**
	 * Returns the transaction payment page API service.
	 *
	 * @return TransactionPaymentPageService
	 */
	private function getPaymentPageService(): TransactionPaymentPageService {
		if ($this->transaction_payment_page_service === null) {
			$this->transaction_payment_page_service = new TransactionPaymentPageService(\WalleeHelper::instance($this->registry)->getApiClient());
		}
		return $this->transaction_payment_page_service;
	}
	
	/**
	 * Updates the line items to be in line with the current order.
	 *
	 * @param int $order_id The order ID
	 * @return \Wallee\Sdk\Model\TransactionLineItemVersion The updated line items
	 * @throws \RuntimeException If the line items cannot be updated
	 */
	public function updateLineItemsFromOrder(int $order_id): \Wallee\Sdk\Model\TransactionLineItemVersion {
		$order_info = \WalleeHelper::instance($this->registry)->getOrder($order_id);
		$transaction_info = \Wallee\Entity\TransactionInfo::loadByOrderId($this->registry, $order_id);
		
		\WalleeHelper::instance($this->registry)->xfeeproDisableIncVat();
		$line_items = \Wallee\Service\LineItem::instance($this->registry)->getItemsFromOrder($order_info,
				$transaction_info->getTransactionId(), $transaction_info->getSpaceId());
		\WalleeHelper::instance($this->registry)->xfeeproRestoreIncVat();
		
		$update_request = new \Wallee\Sdk\Model\TransactionLineItemUpdateRequest();
		$update_request->setTransactionId($transaction_info->getTransactionId());
		$update_request->setNewLineItems($line_items);
		return $this->getTransactionService()->updateTransactionLineItems($transaction_info->getSpaceId(), $update_request);
	}

	/**
	 * Stores the transaction data in the database.
	 *
	 * @param \Wallee\Sdk\Model\Transaction $transaction
	 * @param array $order_info
	 * @return \Wallee\Entity\TransactionInfo
	 */
	public function updateTransactionInfo(\Wallee\Sdk\Model\Transaction $transaction, $order_id){
		$info = \Wallee\Entity\TransactionInfo::loadByTransaction($this->registry, $transaction->getLinkedSpaceId(),
				$transaction->getId());
		$info->setTransactionId($transaction->getId());
		$info->setAuthorizationAmount($transaction->getAuthorizationAmount());
		$info->setOrderId($order_id);
		$info->setState($transaction->getState());
		$info->setSpaceId($transaction->getLinkedSpaceId());
		$info->setSpaceViewId($transaction->getSpaceViewId());
		$info->setLanguage($transaction->getLanguage());
		$info->setCurrency($transaction->getCurrency());
		$info->setConnectorId(
				$transaction->getPaymentConnectorConfiguration() != null ? $transaction->getPaymentConnectorConfiguration()->getConnector() : null);
		$info->setPaymentMethodId(
				$transaction->getPaymentConnectorConfiguration() != null && $transaction->getPaymentConnectorConfiguration()->getPaymentMethodConfiguration() !=
				null ? $transaction->getPaymentConnectorConfiguration()->getPaymentMethodConfiguration()->getPaymentMethod() : null);
		$info->setImage($this->getPaymentMethodImage($transaction));
		$info->setLabels($this->getTransactionLabels($transaction));
		if ($transaction->getState() == \Wallee\Sdk\Model\TransactionState::FAILED ||
				 $transaction->getState() == \Wallee\Sdk\Model\TransactionState::DECLINE) {
			$failed_charge_attempt = $this->getFailedChargeAttempt($transaction->getLinkedSpaceId(), $transaction->getId());
			if ($failed_charge_attempt && $failed_charge_attempt->getFailureReason() != null) {
				$info->setFailureReason($failed_charge_attempt->getFailureReason()->getDescription());
			}
			else if ($transaction->getFailureReason()) {
				$info->setFailureReason($transaction->getFailureReason()->getDescription());
			}
		}
		// TODO into helper?
		if($this->hasSaveableCoupon()) {
			$info->setCouponCode($this->getCoupon());
		}
		$info->save();
		return $info;
	}

	/**
	 * Returns the last failed charge attempt of the transaction.
	 *
	 * @param int $space_id
	 * @param int $transaction_id
	 * @return \Wallee\Sdk\Model\ChargeAttempt
	 */
	private function getFailedChargeAttempt($space_id, $transaction_id){
		$charge_attempt_service = $this->getChargeAttemptService();
		$query = new \Wallee\Sdk\Model\EntityQuery();
		$filter = new \Wallee\Sdk\Model\EntityQueryFilter();
		$filter->setType(\Wallee\Sdk\Model\EntityQueryFilterType::_AND);
		$filter->setChildren(
				array(
					$this->createEntityFilter('charge.transaction.id', $transaction_id),
					$this->createEntityFilter('state', \Wallee\Sdk\Model\ChargeAttemptState::FAILED) 
				));
		$query->setFilter($filter);
		$query->setOrderBys(array(
			$this->createEntityOrderBy('failedOn') 
		));
		$query->setNumberOfEntities(1);
		$result = $charge_attempt_service->search($space_id, $query);
		if ($result != null && !empty($result)) {
			return current($result);
		}
		else {
			return null;
		}
	}

	/**
	 * Returns an array of the transaction's labels.
	 *
	 * @param \Wallee\Sdk\Model\Transaction $transaction
	 * @return string[]
	 */
	private function getTransactionLabels(\Wallee\Sdk\Model\Transaction $transaction){
		$charge_attempt = $this->getChargeAttempt($transaction);
		if ($charge_attempt != null) {
			$labels = array();
			foreach ($charge_attempt->getLabels() as $label) {
				$labels[$label->getDescriptor()->getId()] = $label->getContentAsString();
			}
			return $labels;
		}
		else {
			return array();
		}
	}

	/**
	 * Returns the successful charge attempt of the transaction.
	 *
	 * @param \Wallee\Sdk\Model\Transaction $transaction
	 * @return \Wallee\Sdk\Model\ChargeAttempt
	 */
	private function getChargeAttempt(\Wallee\Sdk\Model\Transaction $transaction){
		$charge_attempt_service = $this->getChargeAttemptService();
		$query = new \Wallee\Sdk\Model\EntityQuery();
		$filter = new \Wallee\Sdk\Model\EntityQueryFilter();
		$filter->setType(\Wallee\Sdk\Model\EntityQueryFilterType::_AND);
		$filter->setChildren(
				array(
					$this->createEntityFilter('charge.transaction.id', $transaction->getId()),
					$this->createEntityFilter('state', \Wallee\Sdk\Model\ChargeAttemptState::SUCCESSFUL) 
				));
		$query->setFilter($filter);
		$query->setNumberOfEntities(1);
		$result = $charge_attempt_service->search($transaction->getLinkedSpaceId(), $query);
		if ($result != null && !empty($result)) {
			return current($result);
		}
		else {
			return null;
		}
	}

	/**
	 * Returns the payment method's image.
	 *
	 * @param \Wallee\Sdk\Model\Transaction $transaction
	 * @return string
	 */
	private function getPaymentMethodImage(\Wallee\Sdk\Model\Transaction $transaction){
		if ($transaction->getPaymentConnectorConfiguration() == null ||
				 $transaction->getPaymentConnectorConfiguration()->getPaymentMethodConfiguration() == null) {
			return null;
		}
		return $transaction->getPaymentConnectorConfiguration()->getPaymentMethodConfiguration()->getResolvedImageUrl();
	}

	private function assembleAddress($source, $prefix = ''){
		$address = new \Wallee\Sdk\Model\AddressCreate();
		$customer = \WalleeHelper::instance($this->registry)->getCustomer();

		if (isset($customer['email'])) {
			$address->setEmailAddress($this->getFixedSource($customer, 'email', 150));
		}
		
		if (isset($source[$prefix . 'city'])) {
			$address->setCity($this->getFixedSource($source, $prefix . 'city', 100, false));
		}
		if (isset($source[$prefix . 'iso_code_2'])) {
			$address->setCountry($source[$prefix . 'iso_code_2']);
		}
		if (isset($source[$prefix . 'lastname'])) {
			$address->setFamilyName($this->getFixedSource($source, $prefix . 'lastname', 100, false));
		}
		if (isset($source[$prefix . 'firstname'])) {
			$address->setGivenName($this->getFixedSource($source, $prefix . 'firstname', 100, false));
		}
		if (isset($source[$prefix . 'company'])) {
			$address->setOrganizationName($this->getFixedSource($source, $prefix . 'company', 100, false));
		}
		if (isset($source[$prefix . 'postcode'])) {
			$address->setPostCode($this->getFixedSource($source, $prefix . 'postcode', 40));
		}
		if (isset($source[$prefix . 'address_1'])) {
			$address->setStreet($this->fixLength(trim($source[$prefix . 'address_1'] . "\n" . $source[$prefix . 'address_2']), 300, false));
		}
		
		// state is 2-part
		if (isset($source[$prefix . 'zone_code']) && isset($source[$prefix . 'iso_code_2'])) {
			$address->setPostalState($source[$prefix . 'iso_code_2'] . '_' . $source[$prefix . 'zone_code']);
		}
		
		return $address;
	}

	private function getFixedSource(array $source_array, $key, $max_length = null, $is_ascii = true, $new_lines = false){
		$value = null;
		if (isset($source_array[$key])) {
			$value = $source_array[$key];
			if ($max_length) {
				$value = $this->fixLength($value, $max_length);
			}
			if ($is_ascii) {
				$value = $this->removeNonAscii($value);
			}
			if (!$new_lines) {
				$value = str_replace("\n", "", $value);
			}
		}
		return $value;
	}

	/**
	 * Checks if a transaction is stored in the session.
	 *
	 * @return bool True if a transaction is stored in the session
	 */
	private function hasTransactionInSession(): bool {
		$data = $this->registry->get('session')->data;
		return isset($data['wallee_transaction_id']) && isset($data['wallee_space_id']) &&
				 $data['wallee_space_id'] == $this->registry->get('config')->get('wallee_space_id') &&
				 \WalleeHelper::instance($this->registry)->compareStoredCustomerSessionIdentifier();
	}

	/**
	 * Clears the transaction data from the session.
	 */
	public function clearTransactionInSession(): void {
		if ($this->hasTransactionInSession()) {
			unset($this->registry->get('session')->data['wallee_transaction_id']);
			unset($this->registry->get('session')->data['wallee_customer']);
			unset($this->registry->get('session')->data['wallee_space_id']);
		}
	}

	/**
	 * Gets the transaction ID from the session.
	 *
	 * @return int The transaction ID
	 * @throws \RuntimeException If no transaction ID is stored in the session
	 */
	private function getSessionTransactionId(): int {
		return (int)$this->registry->get('session')->data['wallee_transaction_id'];
	}

	/**
	 * Gets the space ID from the session.
	 *
	 * @return int The space ID
	 * @throws \RuntimeException If no space ID is stored in the session
	 */
	private function getSessionSpaceId(): int {
		return (int)$this->registry->get('session')->data['wallee_space_id'];
	}

	/**
	 * Stores the transaction IDs in the session.
	 *
	 * @param TransactionModel $transaction The transaction
	 */
	private function storeTransactionIdsInSession(TransactionModel $transaction): void {
		$this->registry->get('session')->data['wallee_customer'] = \WalleeHelper::instance($this->registry)->getCustomerSessionIdentifier();
		$this->registry->get('session')->data['wallee_transaction_id'] = $transaction->getId();
		$this->registry->get('session')->data['wallee_space_id'] = $transaction->getLinkedSpaceId();
	}

	/**
	 * Stores the shipping information.
	 *
	 * @param TransactionModel $transaction The transaction
	 */
	private function storeShipping(TransactionModel $transaction): void {
		$session = $this->registry->get('session')->data;
		if (isset($session['shipping_method']) && isset($session['shipping_method']['cost']) && !empty($session['shipping_method']['cost'])) {
			$shipping_info = \Wallee\Entity\ShippingInfo::loadByTransaction($this->registry, $transaction->getLinkedSpaceId(),
					$transaction->getId());
			$shipping_info->setTransactionId($transaction->getId());
			$shipping_info->setSpaceId($transaction->getLinkedSpaceId());
			$shipping_info->setCost($this->registry->get('session')->data['shipping_method']['cost']);
			$shipping_info->setTaxClassId($this->registry->get('session')->data['shipping_method']['tax_class_id']);
			$shipping_info->save();
		}
	}
	
	/**
	 * Checks if there is a saveable coupon.
	 *
	 * @return bool True if there is a saveable coupon
	 */
	private function hasSaveableCoupon(): bool {
		return isset($this->registry->get('session')->data['coupon']) && isset($this->registry->get('session')->data['order_id']);
	}
	
	/**
	 * Gets the coupon from the session.
	 *
	 * @return string The coupon code
	 * @throws \RuntimeException If no coupon is stored in the session
	 */
	private function getCoupon(): string {
		return $this->registry->get('session')->data['coupon'];
	}
}