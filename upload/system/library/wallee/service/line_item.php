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

use Wallee\Sdk\Model\LineItemCreate;
use Wallee\Sdk\Model\LineItemType;
use Wallee\Sdk\Model\TaxCreate;

/**
 * This service provides methods to handle line items for orders and transactions.
 */
class LineItem extends AbstractService {
	private \Cart\Tax $tax;
	private array $fixed_taxes = [];
	private float $sub_total = 0.0;
	private array $products = [];
	private ?array $shipping = null;
	private ?array $coupon = null;
	private float $coupon_total = 0.0;
	private ?array $voucher = null;
	private array $total = [];
	private ?array $xfeepro = null;

	/**
	 * Returns a singleton instance of the service.
	 *
	 * @param \Registry $registry OpenCart registry object
	 * @return self Service instance
	 */
	public static function instance(\Registry $registry): self {
		return new self($registry);
	}

	/**
	 * Gets the current order items, with all successful refunds applied.
	 *
	 * @param array<string, mixed> $order_info Order information array
	 * @param int $transaction_id Transaction ID
	 * @param int $space_id Space ID
	 * @return array<LineItemCreate> Array of line items with refunds applied
	 * @throws \RuntimeException When tax calculation fails
	 */
	public function getReducedItemsFromOrder(array $order_info, int $transaction_id, int $space_id): array {
		$this->tax = \WalleeVersionHelper::newTax($this->registry);
		$this->tax->setShippingAddress($order_info['shipping_country_id'], $order_info['shipping_zone_id']);
		$this->tax->setPaymentAddress($order_info['payment_country_id'], $order_info['payment_zone_id']);
		$this->coupon_total = 0.0;

		\WalleeHelper::instance($this->registry)->xfeeproDisableIncVat();
		$line_items = $this->getItemsFromOrder($order_info);
		\WalleeHelper::instance($this->registry)->xfeeproRestoreIncVat();

		// Get all successfully reduced items
		$refund_jobs = \Wallee\Entity\RefundJob::loadByOrder($this->registry, $order_info['order_id']);
		$reduction_items = $this->buildReductionItems($refund_jobs);

		// Remove reduced items from available items
		return $this->applyReductions($line_items, $reduction_items);
	}

	/**
	 * Builds an array of reduction items from refund jobs.
	 *
	 * @param array<\Wallee\Entity\RefundJob> $refund_jobs Array of refund jobs
	 * @return array<string, array{quantity: float, unit_price: float}> Reduction items array
	 */
	private function buildReductionItems(array $refund_jobs): array {
		$reduction_items = [];
		foreach ($refund_jobs as $refund) {
			if ($this->isValidRefundState($refund)) {
				foreach ($refund->getReductionItems() as $already_reduced) {
					$unique_id = $already_reduced->getLineItemUniqueId();
					if (!isset($reduction_items[$unique_id])) {
						$reduction_items[$unique_id] = [
							'quantity' => 0,
							'unit_price' => 0.0
						];
					}
					$reduction_items[$unique_id]['quantity'] += $already_reduced->getQuantityReduction();
					$reduction_items[$unique_id]['unit_price'] += $already_reduced->getUnitPriceReduction();
				}
			}
		}
		return $reduction_items;
	}

	/**
	 * Checks if a refund job is in a valid state for reduction.
	 *
	 * @param \Wallee\Entity\RefundJob $refund Refund job to check
	 * @return bool True if the refund state is valid
	 */
	private function isValidRefundState(\Wallee\Entity\RefundJob $refund): bool {
		return $refund->getState() !== \Wallee\Entity\RefundJob::STATE_FAILED_CHECK &&
			   $refund->getState() !== \Wallee\Entity\RefundJob::STATE_FAILED_DONE;
	}

	/**
	 * Applies reductions to line items.
	 *
	 * @param array<LineItemCreate> $line_items Original line items
	 * @param array<string, array{quantity: float, unit_price: float}> $reduction_items Reduction items
	 * @return array<LineItemCreate> Updated line items
	 */
	private function applyReductions(array $line_items, array $reduction_items): array {
		foreach ($line_items as $key => $line_item) {
			if (isset($reduction_items[$line_item->getUniqueId()])) {
				$reduction = $reduction_items[$line_item->getUniqueId()];
				if ($reduction['quantity'] === $line_item->getQuantity()) {
					unset($line_items[$key]);
				} else {
					$this->applyReduction($line_item, $reduction);
				}
			}
		}
		return array_values($line_items);
	}

	/**
	 * Applies a reduction to a single line item.
	 *
	 * @param LineItemCreate $line_item Line item to update
	 * @param array{quantity: float, unit_price: float} $reduction Reduction to apply
	 */
	private function applyReduction(LineItemCreate $line_item, array $reduction): void {
		$unit_price = $line_item->getAmountIncludingTax() / $line_item->getQuantity();
		$unit_price -= $reduction['unit_price'];
		$line_item->setQuantity($line_item->getQuantity() - $reduction['quantity']);
		$line_item->setAmountIncludingTax($unit_price * $line_item->getQuantity());
	}

	/**
	 * Gets the line items from an order.
	 *
	 * @param array<string, mixed> $order_info Order information array
	 * @return array<LineItemCreate> Array of line items
	 * @throws \RuntimeException When order information is invalid
	 */
	public function getItemsFromOrder(array $order_info): array {
		$this->initializeTaxSettings($order_info);
		$this->loadOrderData($order_info);
		return $this->createLineItems($order_info['currency_code']);
	}

	/**
	 * Initializes tax settings for the order.
	 *
	 * @param array<string, mixed> $order_info Order information array
	 */
	private function initializeTaxSettings(array $order_info): void {
		$this->tax = \WalleeVersionHelper::newTax($this->registry);
		$this->tax->setShippingAddress($order_info['shipping_country_id'], $order_info['shipping_zone_id']);
		$this->tax->setPaymentAddress($order_info['payment_country_id'], $order_info['payment_zone_id']);
	}

	/**
	 * Loads all necessary order data.
	 *
	 * @param array<string, mixed> $order_info Order information array
	 */
	private function loadOrderData(array $order_info): void {
		$transaction_info = \Wallee\Entity\TransactionInfo::loadByOrderId($this->registry, $order_info['order_id']);
		$order_model = \WalleeHelper::instance($this->registry)->getOrderModel();

		$this->coupon_total = 0.0;
		$this->fixed_taxes = [];
		$this->products = $order_model->getOrderProducts($order_info['order_id']);
		$this->loadVoucherData($order_model, $order_info['order_id']);
		$this->loadShippingData($order_info, $transaction_info);
		$this->loadTotalsData($order_model, $order_info, $transaction_info);
	}

	/**
	 * Gets the sub total from the totals array.
	 *
	 * @return float
	 */
	private function getSubTotal(): float {
		foreach ($this->total as $total) {
			if ($total['code'] === 'sub_total') {
				return (float)$total['value'];
			}
		}
		return 0.0;
	}

	/**
	 * Gets the line items from the current session.
	 *
	 * @return array<LineItemCreate>
	 * @throws \RuntimeException When session data is invalid
	 */
	public function getItemsFromSession(): array {
		$this->tax = $this->registry->get('tax');
		$session = $this->registry->get('session');

		if (isset($session->data['shipping_country_id'], $session->data['shipping_zone_id'])) {
			$this->tax->setShippingAddress($session->data['shipping_country_id'], $session->data['shipping_zone_id']);
		}
		if (isset($session->data['payment_country_id'], $session->data['payment_zone_id'])) {
			$this->tax->setPaymentAddress($session->data['payment_country_id'], $session->data['payment_zone_id']);
		}

		$this->products = $this->registry->get('cart')->getProducts();
		$this->voucher = null;

		if (!empty($session->data['vouchers'])) {
			$voucher = current($session->data['vouchers']);
			$this->voucher = $voucher ?: null;
		}

		$this->shipping = !empty($session->data['shipping_method']) ? $session->data['shipping_method'] : null;

		\WalleeHelper::instance($this->registry)->xfeeproDisableIncVat();
		$this->total = \WalleeVersionHelper::getSessionTotals($this->registry);
		\WalleeHelper::instance($this->registry)->xfeeProRestoreIncVat();

		$this->sub_total = $this->getSubTotal();

		if (isset($session->data['coupon'], $session->data['customer_id'])) {
			$this->coupon = $this->getCoupon(
				$session->data['coupon'],
				$this->sub_total,
				$session->data['customer_id']
			);
		} else {
			$this->coupon = null;
		}

		return $this->createLineItems(\WalleeHelper::instance($this->registry)->getCurrency());
	}

	/**
	 * Creates line items from the current state.
	 *
	 * @param string $currency_code
	 * @return array<LineItemCreate>
	 * @throws \RuntimeException
	 */
	private function createLineItems(string $currency_code): array {
		$items = [];
		$calculated_total = 0.0;

		foreach ($this->products as $product) {
			$items[] = $item = $this->createLineItemFromProduct($product);
			$calculated_total += $item->getAmountIncludingTax();
		}

		if ($this->voucher) {
			$items[] = $item = $this->createLineItemFromVoucher();
			$calculated_total += $item->getAmountIncludingTax();
		}

		if ($this->coupon) {
			$items[] = $item = $this->createLineItemFromCoupon();
			$calculated_total += $item->getAmountIncludingTax();
		}

		if ($this->shipping) {
			$items[] = $item = $this->createLineItemFromShipping();
			$calculated_total += $item->getAmountIncludingTax();
		}

		$expected_total = 0.0;
		// attempt to add 3rd party totals
		foreach ($this->total as $total) {
			if (strncmp($total['code'], 'xfee', strlen('xfee')) === 0) {
				$items[] = $item = $this->createXFeeLineItem($total);
				$calculated_total += $item->getAmountIncludingTax();
			} elseif (!in_array($total['code'], [
				'total',
				'shipping',
				'sub_total',
				'coupon',
				'tax'
			], true)) {
				if ($total['value'] != 0) {
					$items[] = $item = $this->createLineItemFromTotal($total);
					$calculated_total += $item->getAmountIncludingTax();
				}
			} elseif ($total['code'] === 'total') {
				$expected_total = (float)$total['value'];
			}
		}

		foreach ($this->fixed_taxes as $key => $tax) {
			$items[] = $item = $this->createLineItemFromFee($tax, $key);
			$calculated_total += $item->getAmountIncludingTax();
		}

		// only check amount if currency is base currency. Otherwise, rounding errors are expected to occur due to Opencart standard
		if ($this->registry->get('currency')->getValue($currency_code) === 1.0) {
			$expected_total = \WalleeHelper::instance($this->registry)->formatAmount($expected_total);
			$calculated_total = \WalleeHelper::instance($this->registry)->formatAmount($calculated_total);

			if (abs($expected_total - $calculated_total) > 0.0001) {
				$items[] = $this->createRoundingAdjustmentLineItem($expected_total, $calculated_total);
			}
		}

		return $items;
	}

	/**
	 * Creates a rounding adjustment line item.
	 *
	 * @param float $expected Expected total amount
	 * @param float $calculated Calculated total amount
	 * @return LineItemCreate
	 */
	private function createRoundingAdjustmentLineItem(float $expected, float $calculated): LineItemCreate {
		$difference = $expected - $calculated;
		$line_item = new LineItemCreate();
		
		$line_item->setName(\WalleeHelper::instance($this->registry)->getTranslation('rounding_adjustment_item_name'));
		$line_item->setSku('rounding-adjustment');
		$line_item->setUniqueId('rounding-adjustment');
		$line_item->setQuantity(1);
		$line_item->setType(LineItemType::FEE);
		$line_item->setAmountIncludingTax(\WalleeHelper::instance($this->registry)->formatAmount($difference));
		
		return $this->cleanLineItem($line_item);
	}

	/**
	 * Creates a line item from a fee.
	 *
	 * @param array<string, mixed> $fee
	 * @param string $id
	 * @return LineItemCreate
	 */
	private function createLineItemFromFee(array $fee, string $id): LineItemCreate {
		$line_item = new LineItemCreate();

		$line_item->setName($fee['name']);
		$line_item->setSku($fee['code']);
		$line_item->setUniqueId($id);
		$line_item->setQuantity((int)$fee['quantity']);
		$line_item->setType(LineItemType::FEE);
		$line_item->setAmountIncludingTax(\WalleeHelper::instance($this->registry)->formatAmount($fee['amount']));

		return $this->cleanLineItem($line_item);
	}

	/**
	 * Creates a line item from a total.
	 *
	 * @param array<string, mixed> $total
	 * @return LineItemCreate
	 */
	private function createLineItemFromTotal(array $total): LineItemCreate {
		$line_item = new LineItemCreate();

		$line_item->setName($total['title']);
		$line_item->setSku($total['code']);
		$line_item->setUniqueId($total['code']);
		$line_item->setQuantity(1);
		$line_item->setType(LineItemType::FEE);
		$line_item->setAmountIncludingTax(\WalleeHelper::instance($this->registry)->formatAmount($total['value']));

		return $this->cleanLineItem($line_item);
	}

	/**
	 * Creates a line item from an XFee entry.
	 *
	 * @param array<string, mixed> $total
	 * @return LineItemCreate
	 */
	private function createXFeeLineItem(array $total): LineItemCreate {
		$line_item = new LineItemCreate();
		$line_item->setName($total['title']);
		$line_item->setSku($total['code']);
		$line_item->setUniqueId($this->createUniqueIdFromXfee($total));
		$line_item->setQuantity(1);
		$line_item->setType($total['value'] < 0 ? LineItemType::DISCOUNT : LineItemType::FEE);
		
		$amount = \WalleeHelper::instance($this->registry)->roundXfeeAmount($total['value']);
		$line_item->setAmountIncludingTax(\WalleeHelper::instance($this->registry)->formatAmount($amount));
		
		$taxClass = $this->getXfeeTaxClass($total);
		if ($taxClass) {
			$tax_amount = $this->addTaxesToLineItem($line_item, $total['value'], $taxClass);
			$line_item->setAmountIncludingTax(
				\WalleeHelper::instance($this->registry)->formatAmount($total['value'] + $tax_amount)
			);
		}
		
		return $this->cleanLineItem($line_item);
	}

	/**
	 * Creates a unique ID for an XFee entry.
	 *
	 * @param array<string, mixed> $total
	 * @return string
	 */
	private function createUniqueIdFromXfee(array $total): string {
		if (isset($total['xcode'])) {
			return $total['xcode'];
		}
		return substr($total['code'] . preg_replace("/\W/", "-", $total['title']), 0, 200);
	}

	/**
	 * Gets the tax class for an XFee entry.
	 *
	 * @param array<string, mixed> $total
	 * @return int|null
	 */
	private function getXfeeTaxClass(array $total): ?int {
		$config = $this->registry->get('config');
		if ($total['code'] === 'xfee') {
			for ($i = 0; $i < 12; $i++) {
				if ($config->get('xfee_name' . $i) === $total['title']) {
					return (int)$config->get('xfee_tax_class_id' . $i);
				}
			}
		} elseif ($total['code'] === 'xfeepro') {
			$i = substr($total['xcode'], strlen('xfeepro.xfeepro'));
			$xfeepro = $this->getXfeePro();
			return isset($xfeepro['tax_class_id'][$i]) ? (int)$xfeepro['tax_class_id'][$i] : null;
		}
		return null;
	}

	/**
	 * Gets the XFeePro configuration.
	 *
	 * @return array<string, mixed>
	 */
	private function getXfeePro(): array {
		if ($this->xfeepro === null) {
			$config = $this->registry->get('config');
			$this->xfeepro = unserialize(base64_decode($config->get('xfeepro')));
		}
		return $this->xfeepro;
	}

	/**
	 * Creates a line item from a product.
	 *
	 * @param array<string, mixed> $product Product data array
	 * @return LineItemCreate
	 * @throws \RuntimeException When product data is invalid
	 */
	private function createLineItemFromProduct(array $product): LineItemCreate {
		$line_item = new LineItemCreate();
		$line_item->setName($this->fixLength($product['name'], 150));
		$line_item->setUniqueId($this->createUniqueIdFromProduct($product));
		$line_item->setSku($this->fixLength($product['model'], 200));
		$line_item->setQuantity($product['quantity']);
		$line_item->setType(LineItemType::PRODUCT);

		$tax_amount = $this->addTaxesToLineItem($line_item, $product['total'], $this->getTaxClassByProductId($product['product_id']));
		$line_item->setAmountIncludingTax(\WalleeHelper::instance($this->registry)->formatAmount($product['total'] + $tax_amount));

		return $this->cleanLineItem($line_item);
	}

	/**
	 * Creates a unique ID for a product.
	 *
	 * @param array<string, mixed> $product
	 * @return string
	 */
	private function createUniqueIdFromProduct(array $product): string {
		$unique_id = 'product-' . $product['product_id'];
		if (isset($product['option'])) {
			$options = [];
			foreach ($product['option'] as $option) {
				$options[] = $option['product_option_id'] . '-' . $option['product_option_value_id'];
			}
			sort($options);
			$unique_id .= '-' . implode('-', $options);
		}
		return $unique_id;
	}

	/**
	 * Creates a line item from a coupon.
	 *
	 * @return LineItemCreate
	 * @throws \RuntimeException When coupon data is invalid
	 */
	private function createLineItemFromCoupon(): LineItemCreate {
		if (!$this->coupon) {
			throw new \RuntimeException('Ungültige Gutscheindaten.');
		}

		$line_item = new LineItemCreate();
		$line_item->setName($this->coupon['name']);
		$line_item->setUniqueId('coupon');
		$line_item->setSku('coupon');
		$line_item->setQuantity(1);
		$line_item->setType(LineItemType::DISCOUNT);
		$line_item->setAmountIncludingTax(\WalleeHelper::instance($this->registry)->formatAmount(-$this->coupon_total));
		return $this->cleanLineItem($line_item);
	}

	/**
	 * Creates a line item from a voucher.
	 *
	 * @return LineItemCreate
	 * @throws \RuntimeException When voucher data is invalid
	 */
	private function createLineItemFromVoucher(): LineItemCreate {
		if (!$this->voucher) {
			throw new \RuntimeException('Ungültige Gutscheindaten.');
		}

		$line_item = new LineItemCreate();
		$line_item->setName($this->voucher['description']);
		$line_item->setUniqueId('voucher');
		$line_item->setSku('voucher');
		$line_item->setQuantity(1);
		$line_item->setType(LineItemType::DISCOUNT);
		$line_item->setAmountIncludingTax(\WalleeHelper::instance($this->registry)->formatAmount(-$this->voucher['amount']));
		return $this->cleanLineItem($line_item);
	}

	/**
	 * Creates a line item from shipping.
	 *
	 * @return LineItemCreate
	 * @throws \RuntimeException When shipping data is invalid
	 */
	private function createLineItemFromShipping(): LineItemCreate {
		if (!$this->shipping) {
			throw new \RuntimeException('Ungültige Versanddaten.');
		}

		$line_item = new LineItemCreate();
		$line_item->setName($this->shipping['title']);
		$line_item->setUniqueId('shipping');
		$line_item->setSku('shipping');
		$line_item->setQuantity(1);
		$line_item->setType(LineItemType::SHIPPING);

		$tax_amount = $this->addTaxesToLineItem($line_item, $this->shipping['cost'], $this->shipping['tax_class_id']);
		$line_item->setAmountIncludingTax(
			\WalleeHelper::instance($this->registry)->formatAmount($this->shipping['cost'] + $tax_amount)
		);

		return $this->cleanLineItem($line_item);
	}

	/**
	 * Adds taxes to a line item.
	 *
	 * @param LineItemCreate $line_item Line item to update
	 * @param float $total Total amount
	 * @param int|null $tax_class_id Tax class ID
	 * @return float Total tax amount
	 */
	private function addTaxesToLineItem(LineItemCreate $line_item, float $total, ?int $tax_class_id): float {
		$tax_amount = 0.0;
		$taxes = [];

		if ($tax_class_id !== null) {
			$tax_rates = $this->tax->getRates($total, $tax_class_id);
			foreach ($tax_rates as $tax_rate) {
				$tax = new TaxCreate();
				$tax->setRate($tax_rate['rate']);
				$tax->setTitle($tax_rate['name']);
				$taxes[] = $tax;
				$tax_amount += $tax_rate['amount'];
			}
		}

		$line_item->setTaxes($taxes);
		return $tax_amount;
	}

	/**
	 * Gets the coupon information.
	 *
	 * @param string|null $code Coupon code
	 * @param float $sub_total Subtotal amount
	 * @param int $customer_id Customer ID
	 * @return array<string, mixed>|null Coupon information array or null if invalid
	 * @throws \RuntimeException When coupon validation fails
	 */
	private function getCoupon(?string $code, float $sub_total, int $customer_id): ?array {
		if ($code === null) {
			return null;
		}

		$this->registry->get('load')->model('extension/total/coupon');
		$coupon = $this->registry->get('model_extension_total_coupon')->getCoupon($code);

		if (!$coupon) {
			return null;
		}

		if ($coupon['total'] > $sub_total) {
			return null;
		}

		$coupon_total = 0.0;
		$products = $this->products;

		foreach ($products as $product) {
			$discount = $this->calculateProductDiscount($product, $coupon);
			$coupon_total += $discount;
		}

		if ($coupon['shipping'] && isset($this->shipping)) {
			$coupon_total += $this->shipping['cost'];
		}

		if (!$this->validateCouponUsage($coupon, $customer_id)) {
			return null;
		}

		$this->coupon_total = $coupon_total;
		return $coupon;
	}

	/**
	 * Calculates the discount for a product.
	 *
	 * @param array<string, mixed> $product Product data
	 * @param array<string, mixed> $coupon Coupon data
	 * @return float Calculated discount
	 */
	private function calculateProductDiscount(array $product, array $coupon): float {
		$discount = 0.0;
		if (!$coupon['product']) {
			$status = true;
		} else {
			$status = in_array($product['product_id'], $coupon['product'], true);
		}

		if ($status) {
			if ($coupon['type'] === 'F') {
				$discount = $coupon['discount'] / count($this->products);
			} elseif ($coupon['type'] === 'P') {
				$discount = $product['total'] / 100 * $coupon['discount'];
			}
		}

		return $discount;
	}

	/**
	 * Validates coupon usage limits.
	 *
	 * @param array<string, mixed> $coupon Coupon data
	 * @param int $customer_id Customer ID
	 * @return bool True if coupon usage is valid
	 */
	private function validateCouponUsage(array $coupon, int $customer_id): bool {
		$coupon_history_total = $this->getTotalCouponHistoriesByCoupon($coupon);
		if ($coupon['uses_total'] > 0 && ($coupon_history_total >= $coupon['uses_total'])) {
			return false;
		}

		if ($coupon['logged'] && !$customer_id) {
			return false;
		}

		if ($customer_id) {
			$customer_total = $this->getTotalCouponHistoriesByCustomerId($coupon, $customer_id);
			if ($coupon['uses_customer'] > 0 && ($customer_total >= $coupon['uses_customer'])) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Gets the total number of times a coupon has been used.
	 *
	 * @param array<string, mixed> $coupon Coupon data
	 * @return int Total usage count
	 */
	private function getTotalCouponHistoriesByCoupon(array $coupon): int {
		return (int)$this->registry->get('model_extension_total_coupon')->getTotalCouponHistoriesByCoupon($coupon['coupon_id']);
	}

	/**
	 * Gets the total number of times a customer has used a coupon.
	 *
	 * @param array<string, mixed> $coupon Coupon data
	 * @param int $customer_id Customer ID
	 * @return int Customer usage count
	 */
	private function getTotalCouponHistoriesByCustomerId(array $coupon, int $customer_id): int {
		return (int)$this->registry->get('model_extension_total_coupon')->getTotalCouponHistoriesByCustomerId($coupon['coupon_id'], $customer_id);
	}

	/**
	 * Cleans a line item by removing invalid characters.
	 *
	 * @param LineItemCreate $line_item
	 * @return LineItemCreate
	 */
	private function cleanLineItem(LineItemCreate $line_item): LineItemCreate {
		$line_item->setName($this->removeNonAscii($line_item->getName()));
		$line_item->setSku($this->removeNonAscii($line_item->getSku()));
		return $line_item;
	}

	/**
	 * Gets the tax class ID for a product.
	 *
	 * @param int $product_id
	 * @return int|null
	 */
	private function getTaxClassByProductId(int $product_id): ?int {
		$this->registry->get('load')->model('catalog/product');
		$product_info = $this->registry->get('model_catalog_product')->getProduct($product_id);
		return $product_info ? (int)$product_info['tax_class_id'] : null;
	}
}