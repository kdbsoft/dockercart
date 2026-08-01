<?php
declare(strict_types=1);

/**
 * OrderLocalizer
 *
 * Resolves localized order snapshots (payment/shipping method titles, product
 * and option names, totals) into the display language at render time, falling
 * back to the stored snapshot when the referenced entity or translation is
 * gone. The stored snapshot itself is never modified.
 *
 * Display language is the current session language (config_language_id) —
 * the admin UI language in admin, the customer's storefront language in
 * catalog.
 *
 * @property \DB       $db
 * @property \Config   $config
 * @property \Language $language
 */
class OrderLocalizer {
	private $registry;
	private $tableExistsCache = null;

	public function __construct($registry) {
		$this->registry = $registry;
	}

	public function __get($key) {
		return $this->registry->get($key);
	}

	/**
	 * Localized payment method title for an order row.
	 */
	public function paymentMethodTitle(array $order): string {
		if (!empty($order['payment_code'])) {
			$title = $this->resolveUniversalMethodTitle('payment', (string)$order['payment_code']);

			if ($title !== null) {
				return $title;
			}
		}

		return (string)($order['payment_method'] ?? '');
	}

	/**
	 * Localized shipping method title for an order row.
	 */
	public function shippingMethodTitle(array $order): string {
		if (!empty($order['shipping_code'])) {
			$title = $this->resolveUniversalMethodTitle('shipping', (string)$order['shipping_code']);

			if ($title !== null) {
				return $title;
			}

			$title = $this->resolveNovapostMethodTitle((string)$order['shipping_code']);

			if ($title !== null) {
				return $title;
			}
		}

		return (string)($order['shipping_method'] ?? '');
	}

	/**
	 * Localized payment method title for an oc_order_payment row
	 * (has payment_code / payment_method).
	 */
	public function paymentEntryTitle(array $payment): string {
		return $this->paymentMethodTitle($payment);
	}

	/**
	 * Localized product name, falling back to the stored order snapshot.
	 */
	public function productName(array $order_product): string {
		if (!empty($order_product['product_id'])) {
			$query = $this->db->query("SELECT name FROM " . DB_PREFIX . "product_description WHERE product_id = '" . (int)$order_product['product_id'] . "' AND language_id = '" . $this->languageId() . "'");

			if ($query->num_rows) {
				return (string)$query->row['name'];
			}
		}

		return (string)($order_product['name'] ?? '');
	}

	/**
	 * Localized option name, falling back to the stored order snapshot.
	 */
	public function optionName(array $option): string {
		if (!empty($option['product_option_id'])) {
			$query = $this->db->query("SELECT od.name FROM " . DB_PREFIX . "product_option po LEFT JOIN " . DB_PREFIX . "option_description od ON (po.option_id = od.option_id) WHERE po.product_option_id = '" . (int)$option['product_option_id'] . "' AND od.language_id = '" . $this->languageId() . "'");

			if ($query->num_rows) {
				return (string)$query->row['name'];
			}
		}

		return (string)($option['name'] ?? '');
	}

	/**
	 * Localized option value. Only select-like types carry a translatable
	 * product_option_value_id; free text (text, date, file, ...) is kept as
	 * stored in the order.
	 */
	public function optionValue(array $option): string {
		$type = (string)($option['type'] ?? '');

		if (in_array($type, ['select', 'radio', 'checkbox', 'color', 'image'], true) && !empty($option['product_option_value_id'])) {
			$query = $this->db->query("SELECT ovd.name FROM " . DB_PREFIX . "product_option_value pov LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (pov.option_value_id = ovd.option_value_id) WHERE pov.product_option_value_id = '" . (int)$option['product_option_value_id'] . "' AND ovd.language_id = '" . $this->languageId() . "'");

			if ($query->num_rows) {
				return (string)$query->row['name'];
			}
		}

		return (string)($option['value'] ?? '');
	}

	/**
	 * Localized order total title.
	 *
	 * Known total codes are re-rendered from their extension language file in
	 * the display language; coupon/reward/voucher keep the parenthesized token
	 * (coupon code / points) from the stored title; the shipping total mirrors
	 * the resolved shipping method title; tax titles fall back to the stored
	 * snapshot because the tax rate id is not kept in oc_order_total.
	 */
	public function totalTitle(array $total, string $shipping_method_title = ''): string {
		$code = (string)($total['code'] ?? '');
		$stored = (string)($total['title'] ?? '');

		if ($code === 'shipping') {
			return $shipping_method_title !== '' ? $shipping_method_title : $stored;
		}

		$key_map = [
			'sub_total'    => 'text_sub_total',
			'total'        => 'text_total',
			'handling'     => 'text_handling',
			'low_order_fee' => 'text_low_order_fee',
			'credit'       => 'text_credit',
			'coupon'       => 'text_coupon',
			'reward'       => 'text_reward',
			'voucher'      => 'text_voucher',
		];

		if (!isset($key_map[$code])) {
			return $stored;
		}

		$key = $key_map[$code];
		$title = $this->loadLanguageKey('extension/total/' . $code, $key);

		if ($title === null) {
			return $stored;
		}

		if (in_array($code, ['coupon', 'reward', 'voucher'], true)) {
			$token = $this->extractBracketToken($stored);

			if ($token === '') {
				return $stored;
			}

			$title = sprintf($title, $token);
		}

		return $title;
	}

	/**
	 * Localized payment/shipping country name, falling back to the stored
	 * order snapshot. Uses the multilingual oc_country_description table when
	 * present, otherwise the plain oc_country.name.
	 */
	public function countryName(array $order, string $type): string {
		$country_id = (int)($order[$type . '_country_id'] ?? 0);
		$stored = (string)($order[$type . '_country'] ?? '');

		if ($country_id > 0) {
			if ($this->hasTable('country_description')) {
				$query = $this->db->query("SELECT COALESCE(cd.name, c.name) AS name FROM " . DB_PREFIX . "country c LEFT JOIN " . DB_PREFIX . "country_description cd ON (c.country_id = cd.country_id AND cd.language_id = '" . $this->languageId() . "') WHERE c.country_id = '" . $country_id . "'");
			} else {
				$query = $this->db->query("SELECT name FROM " . DB_PREFIX . "country WHERE country_id = '" . $country_id . "'");
			}

			if ($query->num_rows && !empty($query->row['name'])) {
				return (string)$query->row['name'];
			}
		}

		return $stored;
	}

	/**
	 * Localized payment/shipping zone name, falling back to the stored order
	 * snapshot. Uses the multilingual oc_zone_description table when present,
	 * otherwise the plain oc_zone.name.
	 */
	public function zoneName(array $order, string $type): string {
		$zone_id = (int)($order[$type . '_zone_id'] ?? 0);
		$stored = (string)($order[$type . '_zone'] ?? '');

		if ($zone_id > 0) {
			if ($this->hasTable('zone_description')) {
				$query = $this->db->query("SELECT COALESCE(zd.name, z.name) AS name FROM " . DB_PREFIX . "zone z LEFT JOIN " . DB_PREFIX . "zone_description zd ON (z.zone_id = zd.zone_id AND zd.language_id = '" . $this->languageId() . "') WHERE z.zone_id = '" . $zone_id . "'");
			} else {
				$query = $this->db->query("SELECT name FROM " . DB_PREFIX . "zone WHERE zone_id = '" . $zone_id . "'");
			}

			if ($query->num_rows && !empty($query->row['name'])) {
				return (string)$query->row['name'];
			}
		}

		return $stored;
	}

	/**
	 * Resolve a checkout-time "payment method" history entry (written by the
	 * universal payment confirm as comment_key 'order_payment_method') into the
	 * display language. Returns null when the entry is not such a marker or the
	 * method can no longer be resolved — the caller then falls back to the
	 * stored comment text.
	 */
	public function historyComment(array $entry): ?string {
		if ((string)($entry['comment_key'] ?? '') !== 'order_payment_method') {
			return null;
		}

		$params = json_decode((string)($entry['comment_params'] ?? ''), true);

		if (!is_array($params) || empty($params['code'])) {
			return null;
		}

		$data = $this->universalMethodData('payment', (string)$params['code']);

		if ($data === null) {
			return null;
		}

		$comment = (string)$data['name'];

		if (!empty($data['description'])) {
			$comment .= "\n\n" . $data['description'];
		}

		return $comment;
	}

	/**
	 * Resolve "dockercart_universal.dockercart_universal_{id}" method titles
	 * from the multilingual description tables. Returns null when the code is
	 * not a universal method or the method has no description in the display
	 * language.
	 */
	private function resolveUniversalMethodTitle(string $type, string $code): ?string {
		$data = $this->universalMethodData($type, $code);

		if ($data === null) {
			return null;
		}

		$title = (string)$data['name'];

		if ($type === 'shipping' && !empty($data['delivery_time'])) {
			$title .= ' (' . $data['delivery_time'] . ')';
		}

		return $title;
	}

	/**
	 * Fetch the localized name/description/delivery_time of a universal method
	 * in the display language, or null when unresolvable.
	 */
	private function universalMethodData(string $type, string $code): ?array {
		if (strpos($code, 'dockercart_universal.') !== 0) {
			return null;
		}

		if (!preg_match('/(\d+)$/', $code, $matches)) {
			return null;
		}

		$method_id = (int)$matches[1];

		if ($type === 'shipping') {
			$query = $this->db->query("SELECT name, description, delivery_time FROM " . DB_PREFIX . "dockercart_universal_shipping_description WHERE method_id = '" . $method_id . "' AND language_id = '" . $this->languageId() . "'");
		} else {
			$query = $this->db->query("SELECT name, description FROM " . DB_PREFIX . "dockercart_universal_payment_description WHERE method_id = '" . $method_id . "' AND language_id = '" . $this->languageId() . "'");
		}

		if (!$query->num_rows) {
			return null;
		}

		return $query->row;
	}

	/**
	 * Resolve "dockercart_novapost.{branch|locker|courier}" titles from the
	 * shipping extension language file. Returns null when the code is not a
	 * novapost code or the file/keys are unavailable.
	 */
	private function resolveNovapostMethodTitle(string $code): ?string {
		if (strpos($code, 'dockercart_novapost.') !== 0) {
			return null;
		}

		$lang_keys = [
			'branch'  => 'delivery_branch',
			'locker'  => 'delivery_locker',
			'courier' => 'delivery_courier',
		];

		$suffix = substr($code, strlen('dockercart_novapost.'));

		if (!isset($lang_keys[$suffix])) {
			return null;
		}

		if (!$this->language->load('extension/shipping/dockercart_novapost')) {
			return null;
		}

		$key = $lang_keys[$suffix];
		$title = $this->language->get($key);

		if (empty($title) || $title === $key) {
			return null;
		}

		return $title;
	}

	/**
	 * Extract the "token" between parentheses from a stored total title
	 * (coupon code, reward points, voucher code).
	 */
	private function extractBracketToken(string $title): string {
		if (preg_match('/\(([^)]+)\)/', $title, $matches)) {
			return trim($matches[1]);
		}

		return '';
	}

	/**
	 * Load a language key from a language file, preferring the session language
	 * object and falling back to the storefront language directory (total
	 * extension files live in catalog/language, which is unreachable through
	 * the admin's own Language instance).
	 */
	private function loadLanguageKey(string $file, string $key): ?string {
		$this->language->load($file);

		$title = $this->language->get($key);

		if ($title !== $key && $title !== '') {
			return $title;
		}

		if (!defined('DIR_CATALOG') || !is_dir(DIR_CATALOG . 'language')) {
			return null;
		}

		$language_code = $this->languageCode();

		if ($language_code === '') {
			$language_code = (string)$this->config->get('config_language');
		}

		$path = DIR_CATALOG . 'language/' . $language_code . '/' . $file . '.php';

		if (!is_file($path)) {
			return null;
		}

		$_ = array();
		require($path);

		if (!isset($_[$key]) || $_[$key] === '') {
			return null;
		}

		return (string)$_[$key];
	}

	/**
	 * Language code (e.g. 'en-gb') for the display language.
	 */
	private function languageCode(): string {
		$query = $this->db->query("SELECT code FROM " . DB_PREFIX . "language WHERE language_id = '" . $this->languageId() . "' AND status = '1'");

		if ($query->num_rows) {
			return (string)$query->row['code'];
		}

		return '';
	}

	/**
	 * Whether a localisation description table exists (oc_country_description,
	 * oc_zone_description, ...), cached per instance.
	 */
	private function hasTable(string $table): bool {
		if ($this->tableExistsCache === null) {
			$this->tableExistsCache = [];
		}

		if (!array_key_exists($table, $this->tableExistsCache)) {
			$query = $this->db->query("SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $this->db->escape(DB_PREFIX . $table) . "'");
			$this->tableExistsCache[$table] = (bool)$query->row['total'];
		}

		return $this->tableExistsCache[$table];
	}

	private function languageId(): int {
		return (int)$this->config->get('config_language_id');
	}
}
