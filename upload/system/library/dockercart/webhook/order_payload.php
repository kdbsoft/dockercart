<?php
/**
 * DockerCart webhooks — full order payload builder.
 *
 * Only used when a subscription opted into `full_payload = 1`. The object is
 * deliberately PII-free: no customer name/e-mail/phone, no addresses, no
 * free-text order/payment comments and no IP/user-agent. Integrations that need
 * those fetch the order through GET /api/v1/orders/{id}, which is permission
 * checked and audited.
 *
 * Built directly from the DB so catalog and admin emit sites produce exactly
 * the same shape (the catalog order model has no payment query).
 *
 * @package    DockerCart
 * @subpackage Webhook
 */

declare(strict_types=1);

namespace Dockercart\Webhook;

/** @property \DB $db */
final class OrderPayload {
	/** @var \Registry */
	private $registry;

	public function __construct($registry) {
		$this->registry = $registry;
	}

	public function __get($key) {
		return $this->registry->get($key);
	}

	/**
	 * @return array<string, mixed> empty when the order does not exist
	 */
	public function full(int $order_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order` WHERE `order_id` = '" . (int)$order_id . "'");

		if (!$query->num_rows) {
			return [];
		}

		$order = $query->row;

		$language = $this->language($order);

		$options = [];

		if ($this->tableExists('order_option')) {
			$option_query = $this->db->query("SELECT `order_product_id`, `name`, `value`, `type` FROM `" . DB_PREFIX . "order_option` WHERE `order_id` = '" . (int)$order_id . "' ORDER BY `order_option_id` ASC");

			foreach ($option_query->rows as $option) {
				$options[(int)$option['order_product_id']][] = [
					'name'  => (string)$option['name'],
					'value' => (string)$option['value'],
					'type'  => (string)$option['type'],
				];
			}
		}

		$products = [];

		$product_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_product` WHERE `order_id` = '" . (int)$order_id . "' ORDER BY `order_product_id` ASC");

		foreach ($product_query->rows as $product) {
			$products[] = [
				'order_product_id' => (int)$product['order_product_id'],
				'product_id'       => (int)$product['product_id'],
				'variant_id'       => (int)($product['variant_id'] ?? 0),
				'variant_sku'      => (string)($product['variant_sku'] ?? ''),
				'name'             => (string)$product['name'],
				'model'            => (string)($product['model'] ?? ''),
				'quantity'         => (float)$product['quantity'],
				'price'            => self::money($product['price']),
				'total'            => self::money($product['total']),
				'tax'              => self::money($product['tax'] ?? 0),
				'reward'           => (int)($product['reward'] ?? 0),
				'warehouse_id'     => (int)($product['warehouse_id'] ?? 0),
				'warehouse_name'   => (string)($product['warehouse_name'] ?? ''),
				'estimate_date'    => self::date($product['estimate_date'] ?? ''),
				'options'          => $options[(int)$product['order_product_id']] ?? [],
			];
		}

		$totals = [];

		$total_query = $this->db->query("SELECT `code`, `title`, `value`, `sort_order` FROM `" . DB_PREFIX . "order_total` WHERE `order_id` = '" . (int)$order_id . "' ORDER BY `sort_order` ASC");

		foreach ($total_query->rows as $total) {
			$totals[] = [
				'code'       => (string)$total['code'],
				'title'      => (string)$total['title'],
				'value'      => self::money($total['value']),
				'sort_order' => (int)$total['sort_order'],
			];
		}

		$payments = [];

		if ($this->tableExists('order_payment')) {
			$payment_query = $this->db->query("SELECT `order_payment_id`, `amount`, `payment_method`, `payment_code`, `reference`, `date_added` FROM `" . DB_PREFIX . "order_payment` WHERE `order_id` = '" . (int)$order_id . "' ORDER BY `date_added` ASC, `order_payment_id` ASC");

			foreach ($payment_query->rows as $payment) {
				$payments[] = [
					'order_payment_id' => (int)$payment['order_payment_id'],
					'amount'           => self::money($payment['amount']),
					'payment_method'   => (string)$payment['payment_method'],
					'payment_code'     => (string)$payment['payment_code'],
					'reference'        => (string)($payment['reference'] ?? ''),
					'date_added'       => self::datetime($payment['date_added'] ?? ''),
				];
			}
		}

		return [
			'order_id'         => (int)$order['order_id'],
			'invoice_no'       => (int)($order['invoice_no'] ?? 0),
			'invoice_prefix'   => (string)($order['invoice_prefix'] ?? ''),
			'store_id'         => (int)$order['store_id'],
			'store_name'       => (string)($order['store_name'] ?? ''),
			'customer_id'      => (int)$order['customer_id'],
			'customer_group_id' => (int)($order['customer_group_id'] ?? 0),
			'order_status_id'  => (int)$order['order_status_id'],
			'currency_code'    => (string)$order['currency_code'],
			'currency_value'   => (float)$order['currency_value'],
			'total'            => self::money($order['total']),
			'paid_amount'      => self::money($order['paid_amount'] ?? 0),
			'payment_code'     => (string)($order['payment_code'] ?? ''),
			'shipping_code'    => (string)($order['shipping_code'] ?? ''),
			'tracking_number'  => (string)($order['tracking_number'] ?? ''),
			'language_id'      => (int)($order['language_id'] ?? 0),
			'language_code'    => $language['code'],
			'language_name'    => $language['name'],
			'date_added'       => self::datetime($order['date_added'] ?? ''),
			'date_modified'    => self::datetime($order['date_modified'] ?? ''),
			'products'         => $products,
			'totals'           => $totals,
			'payments'         => $payments,
		];
	}

	/**
	 * Language the order was placed in.
	 *
	 * Product names, option values and total titles in this payload are the
	 * snapshots saved when the order was created — they are historical data in
	 * the *customer's* language and must not be re-translated (that would rewrite
	 * an invoice). `language_code` tells the receiver which language it is.
	 *
	 * @param array<string, mixed> $order
	 * @return array{code: string, name: string}
	 */
	private function language(array $order): array {
		$language_id = (int)($order['language_id'] ?? 0);

		if ($language_id > 0 && $this->tableExists('language')) {
			$query = $this->db->query("SELECT `code`, `name` FROM `" . DB_PREFIX . "language` WHERE `language_id` = '" . $language_id . "'");

			if ($query->num_rows) {
				return [
					'code' => (string)$query->row['code'],
					'name' => (string)$query->row['name'],
				];
			}
		}

		return ['code' => '', 'name' => ''];
	}

	private static function money($value): string {
		return number_format((float)$value, 4, '.', '');
	}

	private static function datetime($value): ?string {
		$value = trim((string)$value);

		if ($value === '' || $value === '0000-00-00 00:00:00') {
			return null;
		}

		$timestamp = strtotime($value);

		return $timestamp === false ? null : date('c', $timestamp);
	}

	private static function date($value): ?string {
		$value = trim((string)$value);

		if ($value === '' || $value === '0000-00-00') {
			return null;
		}

		$timestamp = strtotime($value);

		return $timestamp === false ? null : date('Y-m-d', $timestamp);
	}

	/**
	 * `oc_order_option` / `oc_order_payment` exist in every supported schema,
	 * but the guard keeps a stripped-down custom schema from fataling a webhook.
	 */
	private function tableExists(string $table): bool {
		$query = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . $table) . "'");

		return $query->num_rows > 0;
	}
}
