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
require_once (DIR_SYSTEM . 'library/wallee/autoload.php');
require_once DIR_SYSTEM . '/library/wallee/version_helper.php';

class WalleeHelper {
	
	
	public const SHOP_SYSTEM = 'x-meta-shop-system';
	public const SHOP_SYSTEM_VERSION = 'x-meta-shop-system-version';
	public const SHOP_SYSTEM_AND_VERSION = 'x-meta-shop-system-and-version';

	public const FALLBACK_LANGUAGE = 'en-US';
	/**
	 *
	 * @var Wallee\Sdk\ApiClient
	 */
	private ?Wallee\Sdk\ApiClient $apiClient = null;
	/**
	 *
	 * @var Registry
	 */
	private Registry $registry;
	private $xfeepro;
	private static ?self $instance = null;
	private ?string $catalog_url = null;
	public const LOG_INFO = 2;
	public const LOG_DEBUG = 1;
	public const LOG_ERROR = 0;
	private array $loggers;

	private function __construct(Registry $registry) {
		if (!($registry instanceof Registry) || !$registry->has('session') || !$registry->has('config') || !$registry->has('db')) {
			throw new \InvalidArgumentException("Ungültige Registry für WalleeHelper.");
		}
		
		$this->registry = $registry;
		$this->loggers = [
			self::LOG_ERROR => $registry->get('log'),
			self::LOG_DEBUG => new Log('wallee_debug.log'),
			self::LOG_INFO => new Log('wallee_info.log')
		];
	}

	/**
	 * Create a customer identifier to verify that the session.
	 * Either the customer id,
	 * a concat of given values for guest (hashed),
	 * the user id,
	 * a hash of the current cart key,
	 * a hash of the current token,
	 * or the current order id.
	 *
	 * If not enough information exists to create an identifier null is returned.
	 *
	 * @return string | null
	 */
	public function getCustomerSessionIdentifier(): ?string {
		$customer = $this->getCustomer();
		if (isset($customer['customer_id']) && $this->registry->get('customer')?->isLogged()) {
			return "customer_" . $customer['customer_id'];
		}
		
		$guestId = $this->buildGuestSessionIdentifier($customer);
		if ($guestId !== null) {
			return $guestId;
		}
		
		$data = $this->registry->get('session')->data;
		if (isset($data['user_id'])) {
			return "user_" . $data['user_id'];
		}
		
		$cartId = $this->buildCartSessionIdentifier($data);
		if ($cartId !== null) {
			return $cartId;
		}
		
		if (isset($data['user_token'])) {
			return "token_" . hash('sha512', $data['user_token']);
		}
		
		return null;
	}

	private function buildCartSessionIdentifier(array $data): ?string {
		if (isset($data['cart']) && is_array($data['cart']) && count($data['cart']) === 1) {
			$cartKeys = array_keys($data['cart']);
			return "cart_" . hash('sha512', $cartKeys[0]);
		}
		return null;
	}

	private function buildGuestSessionIdentifier(array $customer): ?string {
		$id = '';
		if (isset($customer['firstname'])) {
			$id .= $customer['firstname'];
		}
		if (isset($customer['lastname'])) {
			$id .= $customer['lastname'];
		}
		if (isset($customer['email'])) {
			$id .= $customer['email'];
		}
		if (isset($customer['telephone'])) {
			$id .= $customer['telephone'];
		}
		if ($id !== '') {
			return "guest_" . hash('sha512', $id);
		}
		return null;
	}

	public function compareStoredCustomerSessionIdentifier(): bool {
		$data = $this->registry->get('session')->data;
		if (!isset($data['wallee_customer']) || empty($data['wallee_customer'])) {
			return false;
		}

		$id = $data['wallee_customer'];
		$parts = explode('_', $id);
		$customer = $this->getCustomer();
		
		return match($parts[0]) {
			'customer' => isset($customer['customer_id']) && 'customer_' . $customer['customer_id'] === $id,
			'user' => (isset($customer['user_id']) && 'user_' . $customer['user_id'] === $id) ||
					(isset($data['user_id']) && 'user_' . $data['user_id'] === $id),
			'guest' => $this->buildGuestSessionIdentifier($customer) === $id,
			'cart' => $this->buildCartSessionIdentifier($data) === $id,
			'token' => isset($data['user_token']) && 'token_' . hash('sha512', $data['user_token']) === $id,
			default => $this->logUnknownComparison($parts[0], $id)
		};
	}

	private function logUnknownComparison(string $type, string $id): bool {
		$this->log("Unbekannter Vergleichstyp {$type} mit ID {$id}");
		return false;
	}

	/**
	 * Attempt to read the current active address from different sources.
	 *
	 * @param string $key 'payment' or 'shipping' depending on which address is desired.
	 * @param array<string, mixed> $order_info Optional order_info as additional address source
	 * @return array<string, mixed>
	 */
	public function getAddress(string $key, array $order_info = []): array {
		$customer = $this->registry->get('customer');
		$session = $this->registry->get('session')->data;
		$address_model = $this->registry->get('model_account_address');
		$address = [];

		if (isset($order_info[$key . '_address']) && is_array($order_info[$key . '_address'])) {
			$address = array_merge($address, $order_info[$key . '_address']);
		}
		
		if (isset($order_info[$key . '_address_id'])) {
			$address = array_merge($address, $address_model->getAddress($order_info[$key . '_address_id']));
		}
		
		if (empty($address) && $key !== 'payment') {
			$address = $this->getAddress('payment', $order_info);
		}
		
		if (empty($address)) {
			if ($customer && $customer->isLogged() && isset($session[$key . '_address_id'])) {
				$address = $address_model->getAddress($session[$key . '_address_id']);
			}
			if (isset($session['guest'][$key]) && is_array($session['guest'][$key])) {
				$address = array_merge($address, $session['guest'][$key]);
			}
			if (isset($session[$key][$key . '_address'])) {
				$address = array_merge($address, $session[$key][$key . '_address']);
			}
			if (isset($session[$key . '_address']) && is_array($session[$key . '_address'])) {
				$address = array_merge($address, $session[$key . '_address']);
			}
		}
		return $address;
	}

	/**
	 * @throws \RuntimeException When space ID is not configured
	 * @throws \Exception When webhook update fails
	 */
	public function refreshWebhook(): void {
		$db = $this->registry->get('db');
		$config = DB_PREFIX . 'setting';

		$generated = $this->getWebhookUrl();
		$saved = $this->registry->get('config')->get('wallee_notification_url');
		
		// If URLs are identical, no update needed
		if ($generated === $saved) {
			return;
		}
		
		$space_id = (int)$this->registry->get('config')->get('wallee_space_id');
		if (!$space_id) {
			throw new \RuntimeException('Space ID is not configured.');
		}

		// Save current status before making changes
		\Wallee\Service\Webhook::instance($this->registry)->uninstall($space_id, $saved);
		
		// Add delay to prevent rapid consecutive updates
		sleep(1);
		
		if (!\Wallee\Service\Webhook::instance($this->registry)->install($space_id, $generated)) {
			throw new \Exception('Failed to install new webhook.');
		}

		$store_id = (int)($this->registry->get('config')->get('config_store_id') ?? 0);
		$store_id = $db->escape((string)$store_id);
		$generated = $db->escape($generated);
		
		$query = "UPDATE `$config` SET `value`='$generated' WHERE `store_id`='$store_id' AND `key`='wallee_notification_url';";
		$db->query($query);
		$this->registry->get('config')->set('wallee_notification_url', $generated);
		
		$this->log("Webhook URL updated from '$saved' to '$generated'", self::LOG_INFO);
	}

	/**
	 * @param string|\Exception $message
	 * @param int $level
	 */
	public function log($message, int $level = self::LOG_DEBUG): void {
		if ($message instanceof \Exception) {
			$message = get_class($message) . ": " . $message->getMessage() . "\n" . $message->getTraceAsString();
		}
		
		if ($this->registry->get('config')->get('wallee_log_level') >= $level) {
			$timestamp = date('Y-m-d H:i:s');
			$this->loggers[$level]->write("[$timestamp] $message");
		}
	}

	/**
	 * @param int $store_id
	 * @return string
	 * @throws \RuntimeException
	 */
	public function getSpaceId(int $store_id): string {
		$table = DB_PREFIX . 'setting';
		$store_id = (int)$store_id;
		$query = "SELECT value FROM $table WHERE `key`='wallee_space_id' AND `store_id`='$store_id'";
		$result = $this->registry->get('db')->query($query);
		
		if ($result->num_rows) {
			return $result->row['value'];
		}
		
		throw new \RuntimeException('No space id found for store id ' . $store_id);
	}

	/**
	 * @param float $amount1
	 * @param float $amount2
	 * @param string $currency_code
	 * @return bool
	 * @throws \RuntimeException
	 */
	public function areAmountsEqual(float $amount1, float $amount2, string $currency_code): bool {
		$currency = $this->registry->get('currency');
		if (!$currency->has($currency_code)) {
			throw new \RuntimeException("Unknown currency $currency_code");
		}
		return $currency->format($amount1, $currency_code) === $currency->format($amount2, $currency_code);
	}

	/**
	 * @param \Wallee\Entity\TransactionInfo $transaction_info
	 * @return bool
	 */
	public function hasRunningJobs(\Wallee\Entity\TransactionInfo $transaction_info): bool {
		return \Wallee\Entity\CompletionJob::countRunningForOrder($this->registry, $transaction_info->getOrderId()) +
				\Wallee\Entity\VoidJob::countRunningForOrder($this->registry, $transaction_info->getOrderId()) +
				\Wallee\Entity\RefundJob::countRunningForOrder($this->registry, $transaction_info->getOrderId()) > 0;
	}

	/**
	 * @param \Wallee\Entity\TransactionInfo $transaction_info
	 * @return bool
	 */
	public function isCompletionPossible(\Wallee\Entity\TransactionInfo $transaction_info): bool {
		return $transaction_info->getState() === \Wallee\Sdk\Model\TransactionState::AUTHORIZED &&
				(\Wallee\Entity\CompletionJob::countRunningForOrder($this->registry, $transaction_info->getOrderId()) === 0) &&
				(\Wallee\Entity\VoidJob::countRunningForOrder($this->registry, $transaction_info->getOrderId()) === 0);
	}

	/**
	 * @param \Wallee\Entity\TransactionInfo $transaction_info
	 * @return bool
	 */
	public function isRefundPossible(\Wallee\Entity\TransactionInfo $transaction_info): bool {
		return in_array($transaction_info->getState(), [
			\Wallee\Sdk\Model\TransactionState::COMPLETED,
			\Wallee\Sdk\Model\TransactionState::FULFILL,
			\Wallee\Sdk\Model\TransactionState::DECLINE
		], true) && \Wallee\Entity\RefundJob::countRunningForOrder($this->registry, $transaction_info->getOrderId()) === 0;
	}

	/**
	 * Returns a single translated string from the localization file.
	 *
	 * @param string $key
	 * @return string
	 */
	public function getTranslation($key){
		if ($this->registry->get('language')->get($key) == $key) {
			$this->registry->get('language')->load('extension/payment/wallee');
		}
		return $this->registry->get('language')->get($key);
	}

	/**
	 * Retrieves order information from front and backend.
	 *
	 * @param int $order_id
	 * @return array
	 */
	public function getOrder($order_id){
		if ($this->isAdmin()) {
			$this->registry->get('load')->model('sale/order');
			return $this->registry->get('model_sale_order')->getOrder($order_id);
		}
		$this->registry->get('load')->model('checkout/order');
		return $this->registry->get('model_checkout_order')->getOrder($order_id);
	}

	/**
	 * Returns the order model which offers methods to retrieve order information - not to add or edit.
	 *
	 * @return Model
	 */
	public function getOrderModel(){
		if ($this->isAdmin()) {
			$this->registry->get('load')->model('sale/order');
			return $this->registry->get('model_sale_order');
		}
		else {
			$this->registry->get('load')->model('account/order');
			return $this->registry->get('model_account_order');
		}
	}

	public function getCustomer(){
		$data = $this->registry->get('session')->data;
		if ($this->registry->get('customer') && $this->registry->get('customer')->isLogged()) {
			$customer_id = $this->registry->get('session')->data['customer_id'];
			$this->registry->get('load')->model('account/customer');
			$customer = $this->registry->get('model_account_customer')->getCustomer($customer_id);
			return $customer;
		}
		else if (isset($data['guest'])) {
			return $data['guest'];
		}
		return array();
	}

	/**
	 * @throws \RuntimeException
	 */
	public function dbTransactionStart(): void {
		if (!$this->registry->get('db')->query('START TRANSACTION')) {
			throw new \RuntimeException('Failed to start database transaction.');
		}
	}

	/**
	 * @throws \RuntimeException
	 */
	public function dbTransactionCommit(): void {
		if (!$this->registry->get('db')->query('COMMIT')) {
			throw new \RuntimeException('Failed to commit database transaction.');
		}
	}

	/**
	 * @throws \RuntimeException
	 */
	public function dbTransactionRollback(): void {
		if (!$this->registry->get('db')->query('ROLLBACK')) {
			throw new \RuntimeException('Failed to rollback database transaction.');
		}
	}

	/**
	 * @param int $space_id
	 * @param int $transaction_id
	 * @return bool
	 * @throws \RuntimeException
	 */
	public function dbTransactionLock(int $space_id, int $transaction_id): bool {
		$table = DB_PREFIX . 'wallee_transaction_info';
		$space_id = (int)$space_id;
		$transaction_id = (int)$transaction_id;
		
		$query = "SELECT transaction_id FROM `$table` WHERE space_id = '$space_id' AND transaction_id = '$transaction_id' FOR UPDATE";
		$result = $this->registry->get('db')->query($query);
		
		if (!$result) {
			throw new \RuntimeException('Failed to acquire transaction lock.');
		}
		
		return $result->num_rows > 0;
	}

	/**
	 * @param float $amount
	 * @param string|null $currency
	 * @return float
	 */
	public function formatAmount(float $amount, ?string $currency = null): float {
		if ($currency === null) {
			$currency = $this->registry->get('session')->data['currency'] ?? 'USD';
		}
		
		$currency_model = $this->registry->get('model_localisation_currency');
		$currency_info = $currency_model->getCurrencyByCode($currency);
		
		if ($currency_info) {
			return round($amount, (int)$currency_info['decimal_place']);
		}
		
		return round($amount, 2);
	}

	/**
	 * @param float $amount
	 * @param string|null $currency
	 * @return float
	 */
	public function roundXfeeAmount(float $amount, ?string $currency = null): float {
		if ($currency === null) {
			$currency = $this->getCurrency();
		}
		
		$currency_model = $this->registry->get('model_localisation_currency');
		$currency_info = $currency_model->getCurrencyByCode($currency);
		
		return round($amount, (int)($currency_info['decimal_place'] ?? 2));
	}

	/**
	 * @return string
	 */
	public function getCurrency(): string {
		return $this->registry->get('session')->data['currency'] ?? 'USD';
	}

	public function translate($strings, $language = null){
		$language = $this->getCleanLanguageCode($language);
		if (isset($strings[$language])) {
			return $strings[$language];
		}

		if ($language) {
			try {
				$language_provider = \Wallee\Provider\Language::instance($this->registry);
				$primary_language = $language_provider->findPrimary($language);
				if ($primary_language && isset($strings[$primary_language->getIetfCode()])) {
					return $strings[$primary_language->getIetfCode()];
				}
			}
			catch (Exception $e) {
			}
		}
		if (isset($strings[self::FALLBACK_LANGUAGE])) {
			return $strings[self::FALLBACK_LANGUAGE];
		}
		$this->log("Could not find translation for given string", self::LOG_ERROR);
		$this->log($strings, self::LOG_ERROR);
		$this->log($primary_language, self::LOG_ERROR);
		return array_shift($strings);
	}

	/**
	 * Returns the proper language code, [a-z]{2}-[A-Z]{2}
	 *
	 * @param string $language
	 * @return string
	 */
	public function getCleanLanguageCode($language = null){
		if ($language == null) {
			$config = $this->registry->get('config');
			if (isset($this->registry->get('session')->data['language'])) {
				$language = $this->registry->get('session')->data['language'];
			}
			else if ($config->has('language_code')) {
				$language = $config->get('language_code');
			}
			else if (!$this->isAdmin() && $config->has('config_language')) {
				$language = $config->get('config_language');
			}
			else if ($config->has('language_default')) {
				$language = $config->get('language_default');
			}
			else if ($this->isAdmin() && $config->has('config_admin_language')) {
				$language = $config->get('config_admin_language');
			}
		}

		$prefixWithDash = substr($language, 0, 3);
		$postfix = strtoupper(substr($language, 3));

		return $prefixWithDash . $postfix;
	}

	/**
	 *
	 * @return Wallee\Sdk\ApiClient
	 */
	public function getApiClient(){
		if ($this->apiClient === null) {
			$this->refreshApiClient();
		}
		return $this->apiClient;
	}

	public function refreshApiClient(){
		$this->apiClient = new Wallee\Sdk\ApiClient($this->registry->get('config')->get('wallee_user_id'),
			$this->registry->get('config')->get('wallee_application_key'));
		$this->apiClient->setBasePath(self::getBaseUrl() . "/api");
		foreach (self::getDefaultHeaderData() as $key => $value) {
			$this->apiClient->addDefaultHeader($key, $value);
		}
		if ($this->registry->get('config')->get('wallee_log_level') >= self::LOG_DEBUG) {
			$this->apiClient->enableDebugging();
			$this->apiClient->setDebugFile(DIR_LOGS . "wallee_communication.log");
		}
	}

	public function getCache(){
		return $this->registry->get('cache');
	}

	public function getSuccessUrl(){
		return WalleeVersionHelper::createUrl($this->getCatalogUrl(), 'checkout/success', array(
			'utm_nooverride' => 1
		), $this->registry->get('config')->get('config_secure'));
	}

	public function getFailedUrl($order_id){
		return str_replace('&amp;', '&',
				WalleeVersionHelper::createUrl($this->getCatalogUrl(), 'extension/wallee/transaction/fail',
						array(
							'order_id' => $order_id,
							'utm_nooverride' => 1
						), $this->registry->get('config')->get('config_secure')));
	}

	public function getWebhookUrl(){
		return WalleeVersionHelper::createUrl($this->getCatalogUrl(), 'extension/wallee/webhook', '',
				$this->registry->get('config')->get('config_secure'));
	}

	/**
	 * Checks if the given order_id exists, is associated with a wallee transaction, and the permissions to access it are set.
	 *
	 * @param string $order_id
	 */
	public function isValidOrder($order_id){
		if (!$this->isAdmin()) {
			$order_info = $this->getOrder($order_id);
			if ($this->registry->get('customer') && $this->registry->get('customer')->isLogged() &&
					isset($this->registry->get('session')->data['customer_id'])) {
				if ($this->registry->get('session')->data['customer_id'] != $order_info['customer_id']) {
					return false;
				}
			}
			else {
				return false;
			}
		}
		$transaction_info = \Wallee\Entity\TransactionInfo::loadByOrderId($this->registry, $order_id);
		return $transaction_info->getId() != null;
	}

	/**
	 * "wallee_pending_status_id"
	 * "wallee_processing_status_id"
	 * "wallee_failed_status_id"
	 * "wallee_voided_status_id"
	 * "wallee_decline_status_id"
	 * "wallee_fulfill_status_id"
	 * "wallee_confirmed_status_id"
	 * "wallee_authorized_status_id"
	 * "wallee_completed_status_id"
	 * "wallee_refund_status_id"
	 *
	 * @param string $order_id
	 * @param string|int $status Key for wallee status mapping, e.g. wallee_completed_status_id, or the order status id
	 * which should be applied.
	 * @param string $message
	 * @param boolean $notify
	 * @param boolean $force If the history should be added even if status is still the same.
	 * @throws Exception
	 */
	public function addOrderHistory($order_id, $status, $message = '', $notify = false, $force = false){
		$this->log(__METHOD__ . " (ID: $order_id, Status: $status, Message: $message, Notify: $notify");
		if ($this->isAdmin()) {
			$this->log('Called addOrderHistory from admin context - unsupported.', self::LOG_ERROR);
			throw new Exception("addOrderHistory from admin not supported"); // should never occur. always via webhook
		}
		if (!ctype_digit($status)) {
			$status = $this->registry->get('config')->get($status);
		}
		$this->registry->get('load')->model('checkout/order');
		$model = $this->registry->get('model_checkout_order');
		$order = $model->getOrder($order_id);
		if ($order['order_status_id'] !== $status || $force) {
			$model->addOrderHistory($order_id, $status, $message, $notify);
		}
		else {
			$this->log("Skipped adding order history, same status & !force.");
		}
	}

	public function ensurePaymentCode(array $order_info, \Wallee\Sdk\Model\Transaction $transaction){
		$allowed = $transaction->getAllowedPaymentMethodConfigurations();
		$code = null;
		if (count($allowed) === 1) {
			$code = 'wallee_' . $allowed[0];
		}
		else if (!empty($transaction->getPaymentConnectorConfiguration()) &&
				!empty($transaction->getPaymentConnectorConfiguration()->getPaymentMethodConfiguration())) {
			$code = 'wallee_' . $transaction->getPaymentConnectorConfiguration()->getPaymentMethodConfiguration()->getId();
		}
		else {
			$this->log("No payment method on transaction, skipping payment code setting.", self::LOG_DEBUG);
			$this->log($transaction, self::LOG_DEBUG);
			return;
		}
		if ($order_info['payment_code'] === $code) {
			return;
		}
		$db = $this->registry->get('db');
		$table = DB_PREFIX . 'order';
		$code = $db->escape($code);
		$title = $db->escape($transaction->getPaymentConnectorConfiguration()->getPaymentMethodConfiguration()->getName());
		$order_id = $db->escape($order_info['order_id']);
		$query = "UPDATE `$table` SET `payment_code`='$code', `payment_method`='$title' WHERE `order_id`='$order_id';";
		$this->log("Changing payment method on order: [" . $query . "], was [" . $order_info['payment_code'] . "]", self::LOG_DEBUG);
		$db->query($query);
	}

	/**
	 *
	 * @return Url
	 */
	private function getCatalogUrl(){
		if ($this->catalog_url === null) {
			if ($this->isAdmin()) {
				$config = $this->registry->get('config');
				$this->catalog_url = new Url($this->getStoreUrl(false), $this->getStoreUrl($config->get('config_secure')));
				$this->catalog_url->addRewrite($this);
			}
			else {
				$this->catalog_url = $this->registry->get('url');
			}
		}
		return $this->catalog_url;
	}

	private function getStoreUrl($ssl = true){
		$config = $this->registry->get('config');
		if ($config->get('config_store_id') == 0) { // zero and null!
			if ($this->isAdmin()) {
				if ($ssl) {
					return HTTPS_CATALOG;
				}
				return HTTP_CATALOG;
			}
			if ($ssl) {
				return HTTPS_SERVER;
			}
			return HTTP_SERVER;
		}
		if ($ssl) {
			return $config->get('config_ssl');
		}
		return $config->get('config_url');
	}

	public function rewrite($url){
		return str_replace(array(
			HTTP_SERVER,
			HTTPS_SERVER
		), array(
			HTTP_CATALOG,
			HTTPS_CATALOG
		), $url);
	}

	public function isAdmin(){
		return defined('HTTPS_CATALOG') && defined('HTTP_CATALOG');
	}

	/**
	 * Get the starting value of LIMIT for db queries.
	 * Used for paginated requests.
	 *
	 * @param int $page
	 * @return int
	 */
	public function getLimitStart($page){
		$limit = $this->registry->get('config')->get('config_limit_admin');
		return ($page - 1) * $limit;
	}

	/**
	 * Get the end value of LIMIT for db queries.
	 * Used for paginated requests.
	 *
	 * @param int $page
	 * @return int
	 */
	public function getLimitEnd($page){
		$limit = $this->registry->get('config')->get('config_limit_admin');
		return $page * $limit;
	}

	/**
	 * Disable inc vat setting in xfeepro.
	 * Necessary to ensure taxes are calculated and transmitted correctly.
	 */
	public function xfeeproDisableIncVat(){
		$config = $this->registry->get('config');
		$xfeepro = $config->get('xfeepro');
		if ($xfeepro) {
			$xfeepro = unserialize(base64_decode($xfeepro));
			$this->xfeepro = $xfeepro;
			if (isset($xfeepro['inc_vat'])) {
				foreach ($xfeepro['inc_vat'] as $i => $value) {
					$xfeepro['inc_vat'][$i] = 0;
				}
			}
			$config->set('xfeepro', base64_encode(serialize($xfeepro)));
		}
	}

	/**
	 * Restore xfeepro settings.
	 */
	public function xfeeProRestoreIncVat(){
		if ($this->xfeepro) {
			$this->registry->get('config')->set('xfeepro', base64_encode(serialize($this->xfeepro)));
		}
	}

	/**
	 * @param Registry $registry
	 * @return self
	 */
	public static function instance(Registry $registry): self {
		if (self::$instance === null) {
			self::$instance = new self($registry);
		}
		return self::$instance;
	}

	/**
	 * @param string $code
	 * @return string
	 */
	public static function extractPaymentMethodId(string $code): string {
		if (!str_contains($code, '.')) {
			return $code;
		}
		$parts = explode('.', $code);
		return $parts[1];
	}

	/**
	 * @param int $severity
	 * @param string $message
	 * @param string $file
	 * @param int $line
	 * @return bool
	 * @throws \ErrorException
	 */
	public static function exceptionErrorHandler(int $severity, string $message, string $file, int $line): bool {
		if (!(error_reporting() & $severity)) {
			return false;
		}
		throw new \ErrorException($message, 0, $severity, $file, $line);
	}

	/**
	 * Get the base URL for this installation
	 * @return string
	 */
	public static function getBaseUrl(): string {
		return str_replace('system/', '', DIR_SYSTEM);
	}

	/**
	 * @param string $state
	 * @return bool
	 */
	public static function isEditableState(string $state): bool {
		return in_array($state, [
			\Wallee\Sdk\Model\TransactionState::AUTHORIZED,
			\Wallee\Sdk\Model\TransactionState::COMPLETED,
			\Wallee\Sdk\Model\TransactionState::FULFILL,
			\Wallee\Sdk\Model\TransactionState::DECLINE
		], true);
	}

	/**
	 * @param int $tokenLength
	 * @return string
	 */
	public static function generateToken(int $tokenLength = 10): string {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$token = '';
		
		try {
			for ($i = 0; $i < $tokenLength; $i++) {
				$token .= $characters[random_int(0, strlen($characters) - 1)];
			}
		} catch (\Exception $e) {
			// Fallback to less secure but available mt_rand
			for ($i = 0; $i < $tokenLength; $i++) {
				$token .= $characters[mt_rand(0, strlen($characters) - 1)];
			}
		}
		
		return $token;
	}

	/**
	 * @return string
	 */
	public static function generateUuid(): string {
		try {
			$data = random_bytes(16);
			$data[6] = chr(ord($data[6]) & 0x0f | 0x40);
			$data[8] = chr(ord($data[8]) & 0x3f | 0x80);
			return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
		} catch (\Exception $e) {
			// Fallback method using mt_rand
			return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
				mt_rand(0, 0xffff), mt_rand(0, 0xffff),
				mt_rand(0, 0xffff),
				mt_rand(0, 0x0fff) | 0x4000,
				mt_rand(0, 0x3fff) | 0x8000,
				mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
			);
		}
	}

	/**
	 * @return array<string, string>
	 */
	protected static function getDefaultHeaderData(): array {
		$version_helper = new \Wallee\VersionHelper();
		return [
			self::SHOP_SYSTEM => 'opencart',
			self::SHOP_SYSTEM_VERSION => $version_helper->getVersion(),
			self::SHOP_SYSTEM_AND_VERSION => 'opencart-' . $version_helper->getVersion()
		];
	}
}
