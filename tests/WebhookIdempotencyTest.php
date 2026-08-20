<?php

define('DIR_SYSTEM', dirname(__DIR__) . '/upload/system/');
define('DB_PREFIX', 'oc_');

final class Registry {
	private $values = array();

	public function set($key, $value){
		$this->values[$key] = $value;
	}

	public function get($key){
		return isset($this->values[$key]) ? $this->values[$key] : null;
	}

	public function has($key){
		return array_key_exists($key, $this->values);
	}
}

final class Log {
	public function __construct($file = null){
	}

	public function write($message){
	}
}

final class TestConfig {
	private $values;

	public function __construct(array $values){
		$this->values = $values;
	}

	public function get($key){
		return isset($this->values[$key]) ? $this->values[$key] : null;
	}
}

final class TestDbResult {
	public $num_rows = 1;
	public $row;

	public function __construct($acquired){
		$this->row = array('acquired' => $acquired);
	}
}

final class TestDb {
	public $lockResult = 1;
	public $queries = array();

	public function escape($value){
		return (string) $value;
	}

	public function query($query){
		$this->queries[] = $query;
		return new TestDbResult($this->lockResult);
	}
}

final class TestOrderModel {
	public $status = '5';
	public $history = array();

	public function getOrder($order_id){
		return array('order_status_id' => $this->status);
	}

	public function addOrderHistory($order_id, $status, $message, $notify){
		$this->history[] = array($order_id, $status, $message, $notify);
	}
}

final class TestLoader {
	private $registry;
	private $orderModel;

	public function __construct(Registry $registry, TestOrderModel $orderModel){
		$this->registry = $registry;
		$this->orderModel = $orderModel;
	}

	public function model($route){
		$this->registry->set('model_checkout_order', $this->orderModel);
	}
}

require DIR_SYSTEM . 'library/wallee/helper.php';

function assertTest($condition, $message){
	if (!$condition) {
		fwrite(STDERR, $message . "\n");
		exit(1);
	}
}

$registry = new Registry();
$db = new TestDb();
$orderModel = new TestOrderModel();
$registry->set('session', new stdClass());
$registry->set('config', new TestConfig(array(
	'wallee_log_level' => 0,
	'wallee_fulfill_status_id' => 5
)));
$registry->set('db', $db);
$registry->set('log', new Log());
$registry->set('load', new TestLoader($registry, $orderModel));

$helper = WalleeHelper::instance($registry);
$helper->addOrderHistory(100, 'wallee_fulfill_status_id', 'Paid', true);
assertTest(count($orderModel->history) === 0,
		'Equivalent string and integer status IDs must not create duplicate history.');

$orderModel->status = '4';
$helper->addOrderHistory(100, 5, 'Paid', true);
assertTest(count($orderModel->history) === 1 && $orderModel->history[0][1] === 5,
		'An integer status ID must be accepted and a changed status must be stored.');

$orderModel->status = '5';
$helper->addOrderHistory(100, '5', 'Forced', true, true);
assertTest(count($orderModel->history) === 2,
		'The explicit force flag must continue to add history for the same status.');

$helper->dbWebhookLock(10, 20);
$helper->dbWebhookUnlock(10, 20);
$queries = implode("\n", $db->queries);
assertTest(strpos($queries, 'GET_LOCK') !== false && strpos($queries, 'RELEASE_LOCK') !== false,
		'The webhook mutex must be acquired and released.');

$db->lockResult = 0;
$failed = false;
try {
	$helper->dbWebhookLock(10, 20);
}
catch (Exception $e) {
	$failed = true;
}
assertTest($failed, 'Webhook processing must fail when the mutex cannot be acquired.');

$processor = file_get_contents(DIR_SYSTEM . 'library/wallee/webhook/abstract_order_related.php');
$lockPosition = strpos($processor, '->dbWebhookLock(');
$transactionPosition = strpos($processor, '->dbTransactionStart(');
$unlockPosition = strpos($processor, '->dbWebhookUnlock(');
$finallyPosition = strpos($processor, 'finally');
assertTest($lockPosition !== false && $transactionPosition !== false && $lockPosition < $transactionPosition,
		'The webhook mutex must be acquired before transactional data is read.');
assertTest($finallyPosition !== false && $unlockPosition > $finallyPosition,
		'The webhook mutex must be released from a finally block.');

echo "Webhook idempotency scenarios passed.\n";
