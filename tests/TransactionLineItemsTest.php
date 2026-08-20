<?php

define('DIR_SYSTEM', dirname(__DIR__) . '/upload/system/');

function modification($path){
	return $path;
}

require DIR_SYSTEM . 'library/wallee/autoload.php';

final class TestTransactionService extends \Wallee\Service\Transaction {
	private $orders;
	public $orderLookups = array();
	public $orderItemCalls = array();
	public $sessionItemCalls = 0;

	public function __construct(array $orders = array()){
		$this->orders = $orders;
	}

	public function resolveLineItems(array $order_info){
		return parent::getLineItems($order_info);
	}

	protected function getOrderForLineItems($order_id){
		$this->orderLookups[] = $order_id;
		return isset($this->orders[$order_id]) ? $this->orders[$order_id] : array();
	}

	protected function getLineItemsFromOrder(array $order_info){
		$this->orderItemCalls[] = $order_info['order_id'];
		return array('persisted-order-items');
	}

	protected function getLineItemsFromSession(){
		$this->sessionItemCalls++;
		return array('session-items');
	}
}

function assertSameValue($expected, $actual, $scenario){
	if ($expected !== $actual) {
		fwrite(STDERR, $scenario . " failed.\nExpected: " . json_encode($expected)
				. "\nActual: " . json_encode($actual) . "\n");
		exit(1);
	}
}

$persistedOrder = array(
	'order_id' => 162843,
	'total' => 120.30
);
$service = new TestTransactionService(array(162843 => $persistedOrder));
$items = $service->resolveLineItems(array('order_id' => 162843));
assertSameValue(array('persisted-order-items'), $items, 'Persisted order source');
assertSameValue(array(162843), $service->orderLookups, 'Persisted order lookup');
assertSameValue(array(162843), $service->orderItemCalls, 'Persisted order line items');
assertSameValue(0, $service->sessionItemCalls, 'Session bypass after order persistence');

$service = new TestTransactionService();
$items = $service->resolveLineItems(array('order_id' => 999));
assertSameValue(array('session-items'), $items, 'Fallback when persisted order is unavailable');
assertSameValue(1, $service->sessionItemCalls, 'Session fallback count');

$service = new TestTransactionService();
$items = $service->resolveLineItems(array());
assertSameValue(array('session-items'), $items, 'Pre-order session source');
assertSameValue(array(), $service->orderLookups, 'No order lookup before persistence');

echo "Transaction line-item source scenarios passed.\n";
