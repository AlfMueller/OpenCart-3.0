<?php

define('DIR_SYSTEM', dirname(__DIR__) . '/upload/system/');

function modification($path){
	return $path;
}

require DIR_SYSTEM . 'library/wallee/autoload.php';

final class TestTax {
	private $rate;

	public function __construct($rate){
		$this->rate = $rate;
	}

	public function getRates($amount, $tax_class_id){
		return array(array(
			'type' => 'P',
			'amount' => $amount * $this->rate / 100
		));
	}
}

final class TestCouponLineItemService extends \Wallee\Service\LineItem {
	public function __construct(){
	}

	public function configure(array $coupon, array $products, $sub_total, $tax_rate){
		$this->setParentProperty('coupon', $coupon);
		$this->setParentProperty('products', $products);
		$this->setParentProperty('sub_total', $sub_total);
		$this->setParentProperty('tax', new TestTax($tax_rate));
	}

	public function calculate(array $product){
		return parent::calculateCouponDiscount($product);
	}

	private function setParentProperty($name, $value){
		$property = new ReflectionProperty(\Wallee\Service\LineItem::class, $name);
		$property->setAccessible(true);
		$property->setValue($this, $value);
	}
}

function assertAmount($expected, $actual, $scenario){
	if (abs($expected - $actual) > 0.0001) {
		fwrite(STDERR, $scenario . " failed. Expected $expected, got $actual.\n");
		exit(1);
	}
}

$products = array(
	array('product_id' => 1, 'total' => 100, 'tax_class_id' => 1),
	array('product_id' => 2, 'total' => 50, 'tax_class_id' => 1)
);

$service = new TestCouponLineItemService();
$service->configure(array(
	'type' => 'F',
	'discount' => 30,
	'product' => array()
), $products, 150, 8.1);
assertAmount(21.62, $service->calculate($products[0]), 'Fixed coupon including tax');
assertAmount(10.81, $service->calculate($products[1]), 'Fixed coupon proportional distribution');

$service->configure(array(
	'type' => 'P',
	'discount' => 10,
	'product' => array()
), $products, 150, 8.1);
assertAmount(10.81, $service->calculate($products[0]), 'Percentage coupon including tax');

$service->configure(array(
	'type' => 'F',
	'discount' => 200,
	'product' => array(1, 2)
), $products, 150, 0);
assertAmount(100, $service->calculate($products[0]), 'Fixed coupon capped at eligible subtotal');
assertAmount(50, $service->calculate($products[1]), 'Product-specific proportional distribution');

echo "Coupon discount scenarios passed.\n";
