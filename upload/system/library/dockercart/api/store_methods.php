<?php
/**
 * DockerCart customer (storefront) REST API — shipping quotes and payment methods.
 *
 * Runs the storefront's own shipping/payment extensions (upload/catalog/model/
 * extension/{shipping,payment}/*) over the API cart + the checkout address, so a
 * client is offered exactly the methods the website offers for the same cart.
 *
 * Unlike the checkout page there is no per-session cache: quotes are recomputed
 * on every call from the address the client sends, which also means a stale
 * quote can never be confirmed — StoreCheckout re-resolves the chosen code
 * before it builds the order.
 *
 * The checkout module's display overrides (custom titles/descriptions in the
 * one-page checkout settings) are not applied: the API returns what the
 * extension itself returns.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

final class StoreMethods {
	private \Registry $registry;

	/**
	 * Per-request memo: the same address is quoted several times while a checkout
	 * state is assembled (tax hydrate → state → validation), and every quote runs
	 * the extension model.
	 *
	 * @var array<string, array>
	 */
	private array $cache = [];

	public function __construct(\Registry $registry) {
		$this->registry = $registry;
	}

	/* ─────────────────────────────── shipping ─────────────────────────────── */

	/**
	 * Every available shipping quote as a flat list; `code` is the full
	 * "module.key" value the client sends back as `shipping_code`.
	 *
	 * @return array<int, array>
	 */
	public function shippingQuotes(array $address): array {
		if (!$this->cart()->hasShipping()) {
			return [];
		}

		$address = $this->assertAddress($address, 'shipping_address');

		$cache_key = 'shipping:' . md5((string)json_encode($address));

		if (isset($this->cache[$cache_key])) {
			return $this->cache[$cache_key];
		}

		$rows = [];

		foreach ($this->extensionCodes('shipping') as $code) {
			$model = $this->load('extension/shipping/' . $code);

			$quote = $model->getQuote($address);

			if (!$quote || empty($quote['quote']) || !is_array($quote['quote'])) {
				continue;
			}

			$sort = (int)($quote['sort_order'] ?? 0);

			foreach ($quote['quote'] as $item) {
				if (empty($item['code'])) {
					continue;
				}

				$cost = $item['cost'] === null ? null : (float)$item['cost'];

				$rows[] = [
					'code'         => (string)$item['code'],
					'module'       => $code,
					'title'        => (string)($item['title'] ?? ''),
					'description'  => (string)($item['description'] ?? ''),
					'cost'         => $cost === null ? null : Serializer::money($cost),
					'tax_class_id' => (int)($item['tax_class_id'] ?? 0),
					'text'         => $cost === null ? '' : $this->amountText($cost, (int)($item['tax_class_id'] ?? 0)),
					'sort_order'   => (int)($item['sort_order'] ?? $sort),
					'quote'        => $this->quoteShape($item, $cost, $sort),
				];
			}
		}

		usort($rows, static function ($left, $right) {
			return (int)$left['sort_order'] <=> (int)$right['sort_order'];
		});

		$this->cache[$cache_key] = $rows;

		return $rows;
	}

	/**
	 * The selected shipping method in the shape the storefront keeps in the
	 * session (total/shipping reads cost, title, tax_class_id, text from there).
	 */
	public function shippingMethod(array $address, string $code): array {
		foreach ($this->shippingQuotes($address) as $row) {
			if ($row['code'] === $code) {
				return $row['quote'];
			}
		}

		throw new HttpException(Error::VALIDATION_FAILED, [
			'shipping_code' => ['is not an available shipping method for this address'],
		]);
	}

	/* ─────────────────────────────── payment ─────────────────────────────── */

	/**
	 * Available payment methods, flattened exactly like the storefront does it
	 * (one entry per sub-method, keyed by the full "module.key" code).
	 *
	 * @return array<int, array>
	 */
	public function paymentMethods(array $address, float $total): array {
		$address = $this->assertAddress($address, 'payment_address');

		$cache_key = 'payment:' . md5((string)json_encode($address)) . ':' . $total;

		if (isset($this->cache[$cache_key])) {
			return $this->cache[$cache_key];
		}

		$rows = [];

		foreach ($this->extensionCodes('payment') as $code) {
			$model = $this->load('extension/payment/' . $code);

			$method = $model->getMethod($address, $total);

			foreach ($this->normalizePayment($method, $code) as $item) {
				$rows[] = $item;
			}
		}

		usort($rows, static function ($left, $right) {
			return (int)$left['sort_order'] <=> (int)$right['sort_order'];
		});

		$this->cache[$cache_key] = $rows;

		return $rows;
	}

	/**
	 * The selected payment method in the session shape.
	 */
	public function paymentMethod(array $address, float $total, string $code): array {
		foreach ($this->paymentMethods($address, $total) as $row) {
			if ($row['code'] === $code) {
				return $row['method'];
			}
		}

		throw new HttpException(Error::VALIDATION_FAILED, [
			'payment_code' => ['is not an available payment method for this cart'],
		]);
	}

	/**
	 * Per-payment-method stock hold window (null = use the global setting).
	 */
	public function reserveMinutes(?string $code): ?int {
		$reservation = new \DockercartStockReservation($this->registry);

		return $reservation->getMethodTtlMinutes($code);
	}

	/* ─────────────────────────────── helpers ─────────────────────────────── */

	/**
	 * Storefront normalisation: a grouped quote[] response becomes one entry per
	 * sub-method, a single-method response becomes one entry keyed by the module
	 * code.
	 *
	 * @return array<int, array{code: string, module: string, title: string, description: string, sort_order: int, method: array}>
	 */
	private function normalizePayment($method, string $module): array {
		$rows = [];

		if (!is_array($method) || !$method) {
			return $rows;
		}

		if (!empty($method['quote']) && is_array($method['quote'])) {
			foreach ($method['quote'] as $quote) {
				if (!is_array($quote) || empty($quote['code'])) {
					continue;
				}

				$quote['title'] = (string)($quote['title'] ?? ($method['title'] ?? ''));
				$quote['terms'] = (string)($quote['terms'] ?? ($method['terms'] ?? ''));
				$quote['sort_order'] = (int)($quote['sort_order'] ?? ($method['sort_order'] ?? 0));

				$rows[] = $this->paymentRow($quote, $module);
			}

			return $rows;
		}

		$method['code'] = (string)($method['code'] ?? $module);
		$method['title'] = (string)($method['title'] ?? '');
		$method['terms'] = (string)($method['terms'] ?? '');
		$method['sort_order'] = (int)($method['sort_order'] ?? 0);

		$rows[] = $this->paymentRow($method, $module);

		return $rows;
	}

	private function paymentRow(array $method, string $module): array {
		return [
			'code'             => (string)$method['code'],
			'module'           => $module,
			'title'            => (string)$method['title'],
			'description'      => (string)($method['terms'] ?? ''),
			'sort_order'       => (int)$method['sort_order'],
			'stock_hold_minutes' => $this->reserveMinutes((string)$method['code']),
			'method'           => $method,
		];
	}

	/**
	 * Session-shaped shipping quote (what total/shipping reads).
	 */
	private function quoteShape(array $item, ?float $cost, int $sort_order): array {
		return [
			'code'         => (string)$item['code'],
			'title'        => (string)($item['title'] ?? ''),
			'cost'         => $cost ?? 0.0,
			'tax_class_id' => (int)($item['tax_class_id'] ?? 0),
			'text'         => (string)($item['text'] ?? ''),
			'description'  => (string)($item['description'] ?? ''),
			'sort_order'   => (int)($item['sort_order'] ?? $sort_order),
		];
	}

	/**
	 * Enabled extension codes of a type, in their configured sort order.
	 *
	 * @return string[]
	 */
	private function extensionCodes(string $type): array {
		$db = $this->db();
		$config = $this->config();

		$query = $db->query("SELECT `code` FROM `" . DB_PREFIX . "extension` WHERE `type` = '" . $db->escape($type) . "' GROUP BY `code`");

		$codes = [];

		foreach ($query->rows as $row) {
			$code = (string)$row['code'];

			if ($config->get($type . '_' . $code . '_status')) {
				$codes[$code] = (float)$config->get($type . '_' . $code . '_sort_order');
			}
		}

		asort($codes, SORT_NUMERIC);

		return array_keys($codes);
	}

	/**
	 * A quote/method needs a real country + zone; the extensions translate them
	 * into geo-zone lookups, so a missing one silently hides every method.
	 */
	private function assertAddress(array $address, string $field): array {
		$errors = [];

		if (empty($address['country_id'])) {
			$errors[$field . '.country_id'] = ['required'];
		}

		if (empty($address['zone_id'])) {
			$errors[$field . '.zone_id'] = ['required'];
		}

		if ($errors) {
			throw new HttpException(Error::VALIDATION_FAILED, $errors);
		}

		return $address;
	}

	private function amountText(float $amount, int $tax_class_id): string {
		$value = (float)$this->registry->get('tax')->calculate($amount, $tax_class_id, (bool)$this->config()->get('config_tax'));

		return (string)$this->registry->get('currency')->format($value, (string)$this->config()->get('config_currency'));
	}

	/**
	 * @return mixed the catalog extension model
	 */
	private function load(string $route) {
		return CatalogModels::load($this->registry, $route);
	}

	private function cart(): \Cart\Cart {
		return $this->registry->get('cart');
	}

	private function config() {
		return $this->registry->get('config');
	}

	private function db() {
		return $this->registry->get('db');
	}
}
