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
 * This service provides methods to handle line items for transactions.
 */
class LineItem extends AbstractService {
	private ?\Cart\Tax $tax = null;
	private array $fixed_taxes = [];
	private ?float $sub_total = null;
	private ?array $products = null;
	private ?array $shipping = null;
	private ?array $coupon = null;
	private float $coupon_total = 0;
	private ?array $voucher = null;
	private ?array $total = null;
	private ?array $xfeepro = null;

	public static function instance(\Registry $registry){
		return new self($registry);
	}

	/**
	 * Gets the current order items, with all successful refunds applied.
	 *
	 * @param array $order_info The order information
	 * @param int $transaction_id The transaction ID
	 * @param int $space_id The space ID
	 * @return \Wallee\Sdk\Model\LineItemCreate[]
	 */
	public function getReducedItemsFromOrder(array $order_info, int $transaction_id, int $space_id): array {
		$this->tax = \WalleeVersionHelper::newTax($this->registry);
		$this->tax->setShippingAddress($order_info['shipping_country_id'], $order_info['shipping_zone_id']);
		$this->tax->setPaymentAddress($order_info['payment_country_id'], $order_info['payment_zone_id']);
		$this->coupon_total = 0;

		\WalleeHelper::instance($this->registry)->xfeeproDisableIncVat();
		$line_items = $this->getItemsFromOrder($order_info);
		\WalleeHelper::instance($this->registry)->xfeeproRestoreIncVat();

		// get all successfully reduced items
		$refund_jobs = \Wallee\Entity\RefundJob::loadByOrder($this->registry, $order_info['order_id']);
		$reduction_items = [];
		foreach ($refund_jobs as $refund) {
			if ($refund->getState() != \Wallee\Entity\RefundJob::STATE_FAILED_CHECK &&
					$refund->getState() != \Wallee\Entity\RefundJob::STATE_FAILED_DONE) {
				foreach ($refund->getReductionItems() as $already_reduced) {
					if (!isset($reduction_items[$already_reduced->getLineItemUniqueId()])) {
						$reduction_items[$already_reduced->getLineItemUniqueId()] = [
							'quantity' => 0,
							'unit_price' => 0
						];
					}
					$reduction_items[$already_reduced->getLineItemUniqueId()]['quantity'] += $already_reduced->getQuantityReduction();
					$reduction_items[$already_reduced->getLineItemUniqueId()]['unit_price'] += $already_reduced->getUnitPriceReduction();
				}
			}
		}

		// remove them from available items
		foreach ($line_items as $key => $line_item) {
			if (isset($reduction_items[$line_item->getUniqueId()])) {
				if ($reduction_items[$line_item->getUniqueId()]['quantity'] == $line_item->getQuantity()) {
					unset($line_items[$key]);
				}
				else {
					$unit_price = $line_item->getAmountIncludingTax() / $line_item->getQuantity();
					$unit_price -= $reduction_items[$line_item->getUniqueId()]['unit_price'];
					$line_item->setQuantity($line_item->getQuantity() - $reduction_items[$line_item->getUniqueId()]['quantity']);
					$line_item->setAmountIncludingTax(\WalleeHelper::instance($this->registry)->formatAmount($unit_price * $line_item->getQuantity()));
				}
			}
		}
		return $line_items;
	}

	/**
	 * Gets the line items from an order.
	 *
	 * @param array $order_info The order information
	 * @return \Wallee\Sdk\Model\LineItemCreate[]
	 */
	public function getItemsFromOrder(array $order_info): array {
		$this->tax = \WalleeVersionHelper::newTax($this->registry);
		$this->tax->setShippingAddress($order_info['shipping_country_id'], $order_info['shipping_zone_id']);
		$this->tax->setPaymentAddress($order_info['payment_country_id'], $order_info['payment_zone_id']);

		$transaction_info = \Wallee\Entity\TransactionInfo::loadByOrderId($this->registry, $order_info['order_id']);
		$order_model = \WalleeHelper::instance($this->registry)->getOrderModel();

		$this->coupon_total = 0;
		$this->fixed_taxes = [];
		$this->products = $order_model->getOrderProducts($order_info['order_id']);
		$voucher = $order_model->getOrderVouchers($order_info['order_id']);
		// only one voucher possible (see extension total voucher)
		if (!empty($voucher)) {
			$this->voucher = $voucher[0];
		}
		else {
			$this->voucher = false;
		}

		$shipping_info = \Wallee\Entity\ShippingInfo::loadByTransaction($this->registry, $transaction_info->getSpaceId(),
				$transaction_info->getTransactionId());
		if ($shipping_info->getId()) {
			$this->shipping = [
				'title' => $order_info['shipping_method'],
				'code' => $order_info['shipping_code'],
				'cost' => $shipping_info->getCost(),
				'tax_class_id' => $shipping_info->getTaxClassId()
			];
		}
		else {
			$this->shipping = false;
		}
		$this->total = $order_model->getOrderTotals($order_info['order_id']);

		$sub_total = 0;
		foreach ($this->total as $total) {
			if ($total['code'] == 'sub_total') {
				$sub_total = $total['value'];
				break;
			}
		}
		$this->sub_total = $sub_total;

		$this->coupon = $this->getCoupon($transaction_info->getCouponCode(), $sub_total, $order_info['customer_id']);

		return $this->createLineItems($order_info['currency_code']);
	}

	/**
	 * Gets the line items from the current session.
	 *
	 * @return \Wallee\Sdk\Model\LineItemCreate[]
	 */
	public function getItemsFromSession(): array {
		$this->tax = $this->registry->get('tax');

		$session = $this->registry->get('session');
		if (isset($session->data['shipping_country_id']) && isset($session->data['shipping_country_id'])) {
			$this->tax->setShippingAddress($session->data['shipping_country_id'], $session->data['shipping_zone_id']);
		}
		if (isset($session->data['payment_country_id']) && isset($session->data['payment_zone_id'])) {
			$this->tax->setPaymentAddress($session->data['payment_country_id'], $session->data['payment_zone_id']);
		}
		$this->products = $this->registry->get('cart')->getProducts();

		if (!empty($this->registry->get('session')->data['vouchers'])) {
			$voucher = current($this->registry->get('session')->data['vouchers']);
		}
		if (!empty($voucher)) {
			$this->voucher = $voucher[0];
		}
		else {
			$this->voucher = false;
		}

		if (!empty($this->registry->get('session')->data['shipping_method'])) {
			$this->shipping = $this->registry->get('session')->data['shipping_method'];
		}
		else {
			$this->shipping = false;
		}

		\WalleeHelper::instance($this->registry)->xfeeproDisableIncVat();
		$this->total = \WalleeVersionHelper::getSessionTotals($this->registry);
		\WalleeHelper::instance($this->registry)->xfeeProRestoreIncVat();

		$sub_total = 0;
		foreach ($this->total as $total) {
			if ($total['code'] == 'sub_total') {
				$sub_total = $total['value'];
				break;
			}
		}
		$this->sub_total = $sub_total;

		if (isset($this->registry->get('session')->data['coupon']) && isset($this->registry->get('session')->data['customer_id'])) {
			$this->coupon = $this->getCoupon($this->registry->get('session')->data['coupon'], $sub_total,
					$this->registry->get('session')->data['customer_id']);
		}
		else {
			$this->coupon = false;
		}

		return $this->createLineItems(\WalleeHelper::instance($this->registry)->getCurrency());
	}

	/**
	 * Creates line items from the current state.
	 *
	 * @param string $currency_code The currency code
	 * @return \Wallee\Sdk\Model\LineItemCreate[]
	 * @throws \Exception
	 */
	private function createLineItems(string $currency_code): array {
		$items = [];
		$calculated_total = 0;
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

		$expected_total = 0;
		// attempt to add 3rd party totals
		foreach ($this->total as $total) {
			if (strncmp($total['code'], 'xfee', strlen('xfee')) === 0) {
				$items[] = $item = $this->createXFeeLineItem($total);
				$calculated_total += $item->getAmountIncludingTax();
			}
			else if (!in_array($total['code'], [
				'total',
				'shipping',
				'sub_total',
				'coupon',
				'tax'
			])) {
				if ($total['value'] != 0) {
					$items[] = $item = $this->createLineItemFromTotal($total);
					$calculated_total += $item->getAmountIncludingTax();
				}
			}
			else if ($total['code'] == 'total') {
				$expected_total = $total['value'];
			}
		}

		foreach ($this->fixed_taxes as $key => $tax) {
			$items[] = $item = $this->createLineItemFromFee($tax, $key);
			$calculated_total += $item->getAmountIncludingTax();
		}

		// only check amount if currency is base currency. Otherwise, rounding errors are expected to occur due to Opencart standard
		if ($this->registry->get('currency')->getValue($currency_code) == 1) {
			$expected_total = \WalleeHelper::instance($this->registry)->formatAmount($expected_total);

			if (!\WalleeHelper::instance($this->registry)->areAmountsEqual($calculated_total, $expected_total, $currency_code)) {
				if ($this->registry->get('config')->get('wallee_rounding_adjustment')) {
					$items[] = $this->createRoundingAdjustmentLineItem($expected_total, $calculated_total);
				}
				else {
					\WalleeHelper::instance($this->registry)->log(
							"Invalid order total calculated. Calculated total: $calculated_total, Expected total: $expected_total.",
							\WalleeHelper::LOG_ERROR);
					\WalleeHelper::instance($this->registry)->log(['Products' => $this->products], \WalleeHelper::LOG_ERROR);
					\WalleeHelper::instance($this->registry)->log(['Voucher' => $this->voucher], \WalleeHelper::LOG_ERROR);
					\WalleeHelper::instance($this->registry)->log(['Coupon' => $this->coupon], \WalleeHelper::LOG_ERROR);
					\WalleeHelper::instance($this->registry)->log(['Totals' => $this->total], \WalleeHelper::LOG_ERROR);
					\WalleeHelper::instance($this->registry)->log(['Fixed taxes' => $this->fixed_taxes], \WalleeHelper::LOG_ERROR);
					\WalleeHelper::instance($this->registry)->log(['Shipping' => $this->shipping], \WalleeHelper::LOG_ERROR);
					\WalleeHelper::instance($this->registry)->log(['wallee Items' => $items], \WalleeHelper::LOG_ERROR);

					throw new \Exception("Invalid order total.");
				}
			}
		}
		return $items;
	}

	/**
	 * Creates a rounding adjustment line item.
	 *
	 * @param float $expected The expected total
	 * @param float $calculated The calculated total
	 * @return \Wallee\Sdk\Model\LineItemCreate
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
	 * @param array $fee The fee data
	 * @param string $id The unique ID
	 * @return \Wallee\Sdk\Model\LineItemCreate
	 */
	private function createLineItemFromFee(array $fee, string $id): LineItemCreate {
		$line_item = new LineItemCreate();

		$line_item->setName($fee['name']);
		$line_item->setSku($fee['code']);
		$line_item->setUniqueId($id);
		$line_item->setQuantity($fee['quantity']);
		$line_item->setType(LineItemType::FEE);
		$line_item->setAmountIncludingTax(\WalleeHelper::instance($this->registry)->formatAmount($fee['amount']));

		return $this->cleanLineItem($line_item);
	}

	/**
	 * Creates a line item from a total.
	 *
	 * @param array $total The total data
	 * @return \Wallee\Sdk\Model\LineItemCreate
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
	 * Creates a line item from an XFee.
	 *
	 * @param array $total The XFee data
	 * @return \Wallee\Sdk\Model\LineItemCreate
	 */
	private function createXFeeLineItem(array $total): LineItemCreate {
		$line_item = new LineItemCreate();
		$line_item->setName($total['title']);
		$line_item->setSku($total['code']);
		$line_item->setUniqueId($this->createUniqueIdFromXfee($total));
		$line_item->setQuantity(1);
		$line_item->setType(LineItemType::FEE);
		if ($total['value'] < 0) {
			$line_item->setType(LineItemType::DISCOUNT);
		}
		$line_item->setAmountIncludingTax(
				\WalleeHelper::instance($this->registry)->formatAmount(
						\WalleeHelper::instance($this->registry)->roundXfeeAmount($total['value'])));
		$taxClass = $this->getXfeeTaxClass($total);
		if ($taxClass) {
			$tax_amount = $this->addTaxesToLineItem($line_item, $total['value'], $taxClass);
			$line_item->setAmountIncludingTax(\WalleeHelper::instance($this->registry)->formatAmount($total['value'] + $tax_amount));
		}
		return $this->cleanLineItem($line_item);
	}

	/**
	 * Creates a unique ID from an XFee.
	 *
	 * @param array $total The XFee data
	 * @return string
	 */
	private function createUniqueIdFromXfee(array $total): string {
		if (isset($total['xcode'])) {
			return $total['xcode'];
		}
		return substr($total['code'] . preg_replace("/\W/", "-", $total['title']), 0, 200);
	}

	/**
	 * Gets the tax class for an XFee.
	 *
	 * @param array $total The XFee data
	 * @return int|null
	 */
	private function getXfeeTaxClass(array $total): ?int {
		$config = $this->registry->get('config');
		if ($total['code'] == 'xfee') {
			for ($i = 0; $i < 12; $i++) {
				if ($config->get('xfee_name' . $i) == $total['title']) {
					return $config->get('xfee_tax_class_id' . $i);
				}
			}
		}
		else if ($total['code'] == 'xfeepro') {
			$i = substr($total['xcode'], strlen('xfeepro.xfeepro'));
			$xfeepro = $this->getXfeePro();
			return $xfeepro['tax_class_id'][$i];
		}
		return null;
	}

	/**
	 * Gets the XFee Pro configuration.
	 *
	 * @return array|null
	 */
	private function getXfeePro(): ?array {
		if ($this->xfeepro === null) {
			$config = $this->registry->get('config');
			$this->xfeepro = unserialize(base64_decode($config->get('xfeepro')));
		}
		return $this->xfeepro;
	}

	/**
	 * Creates a line item from a product.
	 *
	 * @param array $product The product data
	 * @return \Wallee\Sdk\Model\LineItemCreate
	 */
	private function createLineItemFromProduct(array $product): LineItemCreate {
		$line_item = new LineItemCreate();
		$amount_excluding_tax = $product['total'];

		$product['tax_class_id'] = $this->getTaxClassByProductId($product['product_id']);

		if ($this->coupon && (!$this->coupon['product'] || in_array($product['product_id'], $this->coupon['product']))) {
			if ($this->coupon['type'] == 'F') {
				if(empty($this->coupon['product'])) {
					$discount = $this->coupon['discount'] * ($product['total'] / $this->sub_total);
				}else {
					$discount = $this->coupon['discount'] / count($this->coupon['product']);
				}
			}
			elseif ($this->coupon['type'] == 'P') {
				$discount = $product['total'] / 100 * $this->coupon['discount'];
			}
			$this->coupon_total -= $discount;
			$line_item->setAttributes([
				"coupon" => new \Wallee\Sdk\Model\LineItemAttributeCreate([
					'label' => $this->coupon['name'],
					'value' => $discount
				])
			]);
		}

		$line_item->setName($product['name']);
		$line_item->setQuantity($product['quantity']);
		$line_item->setShippingRequired(isset($product['shipping']) && $product['shipping']);
		if (isset($product['sku'])) {
			$line_item->setSku($product['sku']);
		}
		else {
			$line_item->setSku($product['model']);
		}
		$line_item->setUniqueId($this->createUniqueIdFromProduct($product));
		$line_item->setType(LineItemType::PRODUCT);

		$tax_amount = $this->addTaxesToLineItem($line_item, $amount_excluding_tax, $product['tax_class_id']);
		$line_item->setAmountIncludingTax(\WalleeHelper::instance($this->registry)->formatAmount($amount_excluding_tax + $tax_amount));

		return $this->cleanLineItem($line_item);
	}

	/**
	 * Creates a unique ID from a product.
	 *
	 * @param array $product The product data
	 * @return string
	 */
	private function createUniqueIdFromProduct(array $product): string {
		$id = $product['product_id'];
		if (isset($product['option'])) {
			foreach ($product['option'] as $option) {
				$hasValue = false;
				if (isset($option['product_option_id'])) {
					$id .= '_po-' . $option['product_option_id'];
					if (isset($option['product_option_value_id'])) {
						$id .= '=' . $option['product_option_value_id'];
					}
				}
				if (isset($option['option_id']) && isset($option['option_value_id'])) {
					$id .= '_o-' . $option['option_id'];
					if (isset($option['option_value_id']) && !empty($option['option_value_id'])) {
						$id .= '=' . $option['option_value_id'];
					}
				}
				if (isset($option['value']) && !$hasValue) {
					$id .= '_v=' . $option['value'];
				}
			}
		}
		return $id;
	}

	/**
	 * Creates a line item from a coupon.
	 *
	 * @return \Wallee\Sdk\Model\LineItemCreate
	 */
	private function createLineItemFromCoupon(): LineItemCreate {
		$line_item = new LineItemCreate();

		$line_item->setName($this->coupon['name']);
		$line_item->setQuantity(1);
		$line_item->setType(LineItemType::DISCOUNT);
		$line_item->setSKU($this->coupon['code']);
		$line_item->setUniqueId($this->coupon['coupon_id']);
		$line_item->setAmountIncludingTax(\WalleeHelper::instance($this->registry)->formatAmount($this->coupon_total));

		return $this->cleanLineItem($line_item);
	}

	/**
	 * Creates a line item from a voucher.
	 *
	 * @return \Wallee\Sdk\Model\LineItemCreate
	 */
	private function createLineItemFromVoucher(): LineItemCreate {
		$line_item = new LineItemCreate();

		$line_item->setName($this->voucher['name']);
		$line_item->setQuantity(1);
		$line_item->setType(LineItemType::DISCOUNT);
		$line_item->setSKU($this->voucher['code']);
		$line_item->setUniqueId($this->voucher['code']);
		$line_item->setAmountIncludingTax(\WalleeHelper::instance($this->registry)->formatAmount($this->voucher['amount']));

		return $this->cleanLineItem($line_item);
	}

	/**
	 * Creates a line item from shipping.
	 *
	 * @return \Wallee\Sdk\Model\LineItemCreate
	 */
	private function createLineItemFromShipping(): LineItemCreate {
		$line_item = new LineItemCreate();

		$amount_excluding_tax = $this->shipping['cost'];

		if ($this->coupon && $this->coupon['shipping']) {
			$amount_excluding_tax = 0;
		}

		$line_item->setName($this->shipping['title']);
		$line_item->setSku($this->shipping['code']);
		$line_item->setUniqueId($this->shipping['code']);
		$line_item->setType(LineItemType::SHIPPING);
		$line_item->setQuantity(1);

		$tax_amount = $this->addTaxesToLineItem($line_item, $amount_excluding_tax, $this->shipping['tax_class_id']);
		$line_item->setAmountIncludingTax(\WalleeHelper::instance($this->registry)->formatAmount($amount_excluding_tax + $tax_amount));

		return $this->cleanLineItem($line_item);
	}

	/**
	 * Adds taxes to a line item.
	 *
	 * @param \Wallee\Sdk\Model\LineItemCreate $line_item The line item
	 * @param float $total The total amount
	 * @param int $tax_class_id The tax class ID
	 * @return float The total tax amount
	 */
	private function addTaxesToLineItem(LineItemCreate $line_item, float $total, int $tax_class_id): float {
		$tax_amount = 0;
		$rates = $this->tax->getRates($total, $tax_class_id);
		$taxes = [];
		foreach ($rates as $rate) {
			// P = percentage
			if ($rate['type'] == 'P') {
				$tax_amount += $rate['amount'];
				$taxes[] = new TaxCreate([
					'rate' => $rate['rate'],
					'title' => $rate['name']
				]);
			}
			// F = fixed
			else if ($rate['type'] == 'F') {
				$key = preg_replace("/[^\w_]/", "", $rate['name']);
				$amount = $rate['amount'] * $line_item->getQuantity();

				if (isset($this->fixed_taxes[$key])) {
					$this->fixed_taxes[$key]['amount'] += $amount;
					$this->fixed_taxes[$key]['quantity'] += $line_item->getQuantity();
				}
				else {
					$this->fixed_taxes[$key] = [
						'code' => $key,
						'name' => $rate['name'],
						'amount' => $amount,
						'quantity' => $line_item->getQuantity()
					];
				}
			}
		}
		$line_item->setTaxes($taxes);
		return $tax_amount;
	}

	/**
	 * Cleans the given line item for it to meet the API's requirements.
	 *
	 * @param \Wallee\Sdk\Model\LineItemCreate $lineItem
	 * @return \Wallee\Sdk\Model\LineItemCreate
	 */
	private function cleanLineItem(LineItemCreate $line_item){
		$line_item->setSku($this->fixLength($line_item->getSku(), 200));
		$line_item->setName($this->fixLength($line_item->getName(), 40));
		return $line_item;
	}

	/**
	 * Gets the tax class by product ID.
	 *
	 * @param int $product_id The product ID
	 * @return int The tax class ID
	 */
	private function getTaxClassByProductId(int $product_id): int {
		$table = DB_PREFIX . 'product';
		$product_id = $this->registry->get('db')->escape($product_id);
		$query = "SELECT tax_class_id FROM $table WHERE product_id='$product_id';";
		$result = $this->registry->get('db')->query($query);
		return (int)$result->row['tax_class_id'];
	}

	/**
	 * Gets the total number of coupon histories for a given coupon code.
	 *
	 * @param string $coupon The coupon code
	 * @return int The total number of coupon histories
	 */
	private function getTotalCouponHistoriesByCoupon(string $coupon): int {
		$query = $this->registry->get('db')->query(
			sprintf(
				"SELECT COUNT(*) AS total FROM `%scoupon_history` ch LEFT JOIN `%scoupon` c ON (ch.coupon_id = c.coupon_id) WHERE c.code = '%s'",
				DB_PREFIX,
				DB_PREFIX,
				$this->registry->get('db')->escape($coupon)
			)
		);
		return (int)$query->row['total'];
	}

	/**
	 * Gets the total number of coupon histories for a given coupon code and customer ID.
	 *
	 * @param string $coupon The coupon code
	 * @param int $customer_id The customer ID
	 * @return int The total number of coupon histories
	 */
	private function getTotalCouponHistoriesByCustomerId(string $coupon, int $customer_id): int {
		$query = $this->registry->get('db')->query(
			sprintf(
				"SELECT COUNT(*) AS total FROM `%scoupon_history` ch LEFT JOIN `%scoupon` c ON (ch.coupon_id = c.coupon_id) WHERE c.code = '%s' AND ch.customer_id = '%d'",
				DB_PREFIX,
				DB_PREFIX,
				$this->registry->get('db')->escape($coupon),
				$customer_id
			)
		);
		return (int)$query->row['total'];
	}

	/**
	 * Gets coupon information by code.
	 *
	 * @param string|null $code The coupon code
	 * @param float $sub_total The subtotal amount
	 * @param int $customer_id The customer ID
	 * @return array<string, mixed> The coupon information or empty array if invalid
	 */
	private function getCoupon(?string $code, float $sub_total, int $customer_id): array {
		if ($code === null) {
			return [];
		}

		$db = $this->registry->get('db');
		$status = true;

		$coupon_query = $db->query(sprintf(
			"SELECT * FROM `%scoupon` WHERE code = '%s' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) AND status = '1'",
			DB_PREFIX,
			$db->escape($code)
		));

		if (!$coupon_query->num_rows) {
			return [];
		}

		if ($coupon_query->row['total'] > $sub_total) {
			return [];
		}

		$coupon_total = $this->getTotalCouponHistoriesByCoupon($code);
		if ($coupon_query->row['uses_total'] > 0 && ($coupon_total >= $coupon_query->row['uses_total'])) {
			return [];
		}

		if ($coupon_query->row['logged'] && !$customer_id) {
			return [];
		}

		if ($customer_id) {
			$customer_total = $this->getTotalCouponHistoriesByCustomerId($code, $customer_id);
			if ($coupon_query->row['uses_customer'] > 0 && ($customer_total >= $coupon_query->row['uses_customer'])) {
				return [];
			}
		}

		// Get eligible products
		$coupon_product_data = $this->getCouponProducts((int)$coupon_query->row['coupon_id']);
		
		// Get eligible categories
		$coupon_category_data = $this->getCouponCategories((int)$coupon_query->row['coupon_id']);

		$product_data = [];
		if ($coupon_product_data || $coupon_category_data) {
			foreach ($this->products as $product) {
				if (in_array($product['product_id'], $coupon_product_data, true)) {
					$product_data[] = $product['product_id'];
					continue;
				}

				foreach ($coupon_category_data as $category_id) {
					$coupon_category_query = $db->query(sprintf(
						"SELECT COUNT(*) AS total FROM `%sproduct_to_category` WHERE `product_id` = '%d' AND category_id = '%d'",
						DB_PREFIX,
						(int)$product['product_id'],
						(int)$category_id
					));

					if ($coupon_category_query->row['total']) {
						$product_data[] = $product['product_id'];
						break;
					}
				}
			}

			if (empty($product_data)) {
				return [];
			}
		}

		return [
			'coupon_id' => $coupon_query->row['coupon_id'],
			'code' => $coupon_query->row['code'],
			'name' => $coupon_query->row['name'],
			'type' => $coupon_query->row['type'],
			'discount' => $coupon_query->row['discount'],
			'shipping' => $coupon_query->row['shipping'],
			'total' => $coupon_query->row['total'],
			'product' => $product_data,
			'date_start' => $coupon_query->row['date_start'],
			'date_end' => $coupon_query->row['date_end'],
			'uses_total' => $coupon_query->row['uses_total'],
			'uses_customer' => $coupon_query->row['uses_customer'],
			'status' => $coupon_query->row['status'],
			'date_added' => $coupon_query->row['date_added']
		];
	}

	/**
	 * Gets the products that are eligible for a coupon.
	 *
	 * @param int $coupon_id The coupon ID
	 * @return array<int> Array of product IDs
	 */
	private function getCouponProducts(int $coupon_id): array {
		$coupon_product_data = [];
		$coupon_product_query = $this->registry->get('db')->query(sprintf(
			"SELECT * FROM `%scoupon_product` WHERE coupon_id = '%d'",
			DB_PREFIX,
			$coupon_id
		));

		foreach ($coupon_product_query->rows as $product) {
			$coupon_product_data[] = (int)$product['product_id'];
		}

		return $coupon_product_data;
	}

	/**
	 * Gets the categories that are eligible for a coupon.
	 *
	 * @param int $coupon_id The coupon ID
	 * @return array<int> Array of category IDs
	 */
	private function getCouponCategories(int $coupon_id): array {
		$coupon_category_data = [];
		$coupon_category_query = $this->registry->get('db')->query(sprintf(
			"SELECT * FROM `%scoupon_category` cc LEFT JOIN `%scategory_path` cp ON (cc.category_id = cp.path_id) WHERE cc.coupon_id = '%d'",
			DB_PREFIX,
			DB_PREFIX,
			$coupon_id
		));

		foreach ($coupon_category_query->rows as $category) {
			$coupon_category_data[] = (int)$category['category_id'];
		}

		return $coupon_category_data;
	}
}