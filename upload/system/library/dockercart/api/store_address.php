<?php
/**
 * DockerCart customer (storefront) REST API — address book.
 *
 * Addresses belong to exactly one customer and every read is filtered by the
 * authenticated principal, so an id from another account is a 404, never a 403
 * (ids are not disclosed). The stored row is the panel/storefront shape
 * (oc_address + country/zone snapshot columns), and the API representation adds
 * the resolved country/zone names so a client never has to join them itself.
 *
 * Address validation is the storefront's rule set (account/address): required
 * first/last name, address line and city, an existing enabled country, a zone
 * that belongs to that country and a postcode when the country requires one.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

final class StoreAddress {
	/**
	 * Free-text fields of an address and the column budget of each (characters).
	 * They are escaped before they are stored — see stored().
	 */
	private const TEXT_LIMITS = [
		'firstname' => 32,
		'lastname'  => 32,
		'company'   => 60,
		'address_1' => 128,
		'address_2' => 128,
		'city'      => 128,
		'postcode'  => 10,
	];

	private \Registry $registry;

	private int $customer_id;

	public function __construct(\Registry $registry, int $customer_id) {
		$this->registry = $registry;
		$this->customer_id = $customer_id;
	}

	/**
	 * The customer's address book, default address first.
	 *
	 * @return array<int, array>
	 */
	public function all(): array {
		$query = $this->db()->query("SELECT a.*, c.`name` AS country, c.`iso_code_2`, c.`iso_code_3`, c.`address_format`, c.`postcode_required`, z.`name` AS zone, z.`code` AS zone_code FROM `" . DB_PREFIX . "address` a LEFT JOIN `" . DB_PREFIX . "country` c ON (a.`country_id` = c.`country_id`) LEFT JOIN `" . DB_PREFIX . "zone` z ON (a.`zone_id` = z.`zone_id`) WHERE a.`customer_id` = '" . (int)$this->customer_id . "' ORDER BY a.`address_id` ASC");

		$rows = [];

		foreach ($query->rows as $row) {
			$rows[] = $this->represent($row, $this->isDefault($row));
		}

		return $rows;
	}

	/**
	 * One address of the customer, 404 otherwise.
	 */
	public function get(int $address_id): array {
		$row = $this->requireRow($address_id);

		return $this->represent($row, $this->isDefault($row));
	}

	/**
	 * Normalizes an inline address payload (validates and enriches it with the
	 * country/zone snapshot) without persisting it.
	 */
	public function normalize(array $data): array {
		$validator = new Validator();

		$normalized = $validator->validate($data, [
			'firstname'    => ['string', 'max:32'],
			'lastname'     => ['string', 'max:32'],
			'company'      => ['string', 'max:60'],
			'address_1'    => ['required', 'string', 'min:3', 'max:128'],
			'address_2'    => ['string', 'max:128'],
			'city'         => ['required', 'string', 'min:2', 'max:128'],
			'postcode'     => ['string', 'max:10'],
			'country_id'   => ['required', 'int', 'min:1'],
			'zone_id'      => ['int', 'min:0'],
			'custom_field' => ['array'],
		]);

		$errors = [];

		// The panel and the storefront escape free text on input (Request::clean())
		// and the panel theme renders it unescaped (autoescape is off), so the
		// stored value must not contain live markup. The API bypasses
		// Request::clean() (raw JSON), so it escapes here — otherwise an address
		// typed through the API would execute script in the order/customer pages of
		// the panel. Field limits match the rules above (they are validated on the
		// escaped value, exactly like the panel's form).
		foreach (self::TEXT_LIMITS as $field => $max) {
			if (isset($normalized[$field]) && $normalized[$field] !== null) {
				$normalized[$field] = Serializer::encodeTextWithin($field, $normalized[$field], $max);
			}
		}

		$customer = $this->customerRow();

		$firstname = trim((string)($normalized['firstname'] ?? '')) !== '' ? (string)$normalized['firstname'] : (string)($customer['firstname'] ?? '');
		$lastname = trim((string)($normalized['lastname'] ?? '')) !== '' ? (string)$normalized['lastname'] : (string)($customer['lastname'] ?? '');

		if ($firstname === '') {
			$errors['firstname'] = ['required'];
		}

		if ($lastname === '') {
			$errors['lastname'] = ['required'];
		}

		$country = $this->country((int)$normalized['country_id']);

		if (!$country) {
			$errors['country_id'] = ['unknown or disabled country'];
		}

		$zone_id = (int)($normalized['zone_id'] ?? 0);
		$zone = $zone_id > 0 ? $this->zone($zone_id) : null;

		if ($zone && $country && (int)$zone['country_id'] !== (int)$country['country_id']) {
			$errors['zone_id'] = ['does not belong to the selected country'];
		}

		$postcode = trim((string)($normalized['postcode'] ?? ''));

		if ($country && !empty($country['postcode_required']) && $postcode === '' && !isset($errors['postcode'])) {
			$errors['postcode'] = ['required for this country'];
		}

		if ($country && $this->countryHasZones((int)$country['country_id']) && $zone_id <= 0) {
			$errors['zone_id'] = ['required for this country'];
		}

		if ($errors) {
			throw new HttpException(Error::VALIDATION_FAILED, $errors);
		}

		return [
			'address_id'     => 0,
			'customer_id'    => $this->customer_id,
			'firstname'      => $firstname,
			'lastname'       => $lastname,
			'company'        => (string)($normalized['company'] ?? ''),
			'address_1'      => (string)$normalized['address_1'],
			'address_2'      => (string)($normalized['address_2'] ?? ''),
			'city'           => (string)$normalized['city'],
			'postcode'       => $postcode,
			'country_id'     => (int)$country['country_id'],
			'country'        => (string)$country['name'],
			'iso_code_2'     => (string)$country['iso_code_2'],
			'iso_code_3'     => (string)$country['iso_code_3'],
			'address_format' => (string)$country['address_format'],
			'zone_id'        => $zone ? (int)$zone['zone_id'] : 0,
			'zone'           => $zone ? (string)$zone['name'] : '',
			'zone_code'      => $zone ? (string)$zone['code'] : '',
			'custom_field'   => (array)($normalized['custom_field'] ?? []),
		];
	}

	/**
	 * Persists a normalized address and returns it with its new id.
	 */
	public function store(array $address, bool $make_default = false): array {
		$db = $this->db();

		$db->query("INSERT INTO `" . DB_PREFIX . "address` SET `customer_id` = '" . (int)$this->customer_id . "', `firstname` = '" . $db->escape((string)$address['firstname']) . "', `lastname` = '" . $db->escape((string)$address['lastname']) . "', `company` = '" . $db->escape((string)$address['company']) . "', `address_1` = '" . $db->escape((string)$address['address_1']) . "', `address_2` = '" . $db->escape((string)$address['address_2']) . "', `city` = '" . $db->escape((string)$address['city']) . "', `postcode` = '" . $db->escape((string)$address['postcode']) . "', `country_id` = '" . (int)$address['country_id'] . "', `zone_id` = '" . (int)$address['zone_id'] . "', `custom_field` = '" . $db->escape((string)json_encode((array)$address['custom_field'])) . "'");

		$address_id = (int)$db->getLastId();

		if ($make_default || $this->defaultAddressId() <= 0) {
			$db->query("UPDATE `" . DB_PREFIX . "customer` SET `address_id` = '" . $address_id . "' WHERE `customer_id` = '" . (int)$this->customer_id . "'");
		}

		return $this->get($address_id);
	}

	/**
	 * Creates an address from the API payload.
	 */
	public function create(array $data): array {
		return $this->store($this->normalize($data), !empty($data['default']));
	}

	/**
	 * Replaces an address of the customer.
	 */
	public function update(int $address_id, array $data): array {
		$existing = $this->requireRow($address_id);

		// The stored row is escaped for the panel, while the API works in plain text
		// (represent() decodes). Using the stored row as the merge base would escape
		// every field the client did not touch a second time, so a partial PUT of a
		// name like `AT&T` would degrade to `AT&amp;amp;T` — and an address with
		// markup would grow until it no longer fits the column.
		$merged = array_merge($this->represent($existing, false), $data);

		$address = $this->normalize($merged);

		$db = $this->db();

		$db->query("UPDATE `" . DB_PREFIX . "address` SET `firstname` = '" . $db->escape((string)$address['firstname']) . "', `lastname` = '" . $db->escape((string)$address['lastname']) . "', `company` = '" . $db->escape((string)$address['company']) . "', `address_1` = '" . $db->escape((string)$address['address_1']) . "', `address_2` = '" . $db->escape((string)$address['address_2']) . "', `city` = '" . $db->escape((string)$address['city']) . "', `postcode` = '" . $db->escape((string)$address['postcode']) . "', `country_id` = '" . (int)$address['country_id'] . "', `zone_id` = '" . (int)$address['zone_id'] . "', `custom_field` = '" . $db->escape((string)json_encode((array)$address['custom_field'])) . "' WHERE `address_id` = '" . (int)$address_id . "' AND `customer_id` = '" . (int)$this->customer_id . "'");

		if (!empty($data['default'])) {
			$this->db()->query("UPDATE `" . DB_PREFIX . "customer` SET `address_id` = '" . (int)$address_id . "' WHERE `customer_id` = '" . (int)$this->customer_id . "'");
		}

		return $this->get($address_id);
	}

	public function delete(int $address_id): void {
		$this->requireRow($address_id);

		$db = $this->db();

		$db->query("DELETE FROM `" . DB_PREFIX . "address` WHERE `address_id` = '" . (int)$address_id . "' AND `customer_id` = '" . (int)$this->customer_id . "'");

		$db->query("UPDATE `" . DB_PREFIX . "customer` SET `address_id` = '0' WHERE `customer_id` = '" . (int)$this->customer_id . "' AND `address_id` = '" . (int)$address_id . "'");
	}

	/**
	 * Resolves a checkout address input: `{"address_id": N}` selects a saved
	 * address, anything else is an inline address that is validated (and, with
	 * `save: true`, added to the address book).
	 */
	public function resolve(array $input, string $field): array {
		if (isset($input['address_id'])) {
			$address_id = (int)$input['address_id'];

			if ($address_id <= 0) {
				throw new HttpException(Error::VALIDATION_FAILED, [$field . '.address_id' => ['must be a positive integer']]);
			}

			$row = $this->requireRow($address_id);

			return $this->represent($row, $this->isDefault($row));
		}

		$save = !empty($input['save']);

		unset($input['save'], $input['default']);

		$address = $this->normalize($input);

		if ($save) {
			return $this->store($address, false);
		}

		return $this->represent($address, $this->isDefault($address));
	}

	/**
	 * The customer's default address, or null when none is saved.
	 */
	public function defaultAddress(): ?array {
		$address_id = $this->defaultAddressId();

		if ($address_id <= 0) {
			return null;
		}

		$query = $this->db()->query("SELECT * FROM `" . DB_PREFIX . "address` WHERE `address_id` = '" . (int)$address_id . "' AND `customer_id` = '" . (int)$this->customer_id . "'");

		return $query->num_rows ? $this->represent($query->row, true) : null;
	}

	/**
	 * Escaped form of an address, ready to be persisted.
	 *
	 * resolve() answers with the reader's (decoded) view, but a checkout row is not
	 * a response: it is read back by ModelCheckoutOrder::addOrder() and lands in
	 * oc_order, which the panel renders unescaped. Restoring the escaped form before
	 * it is written keeps the API order byte-identical to a storefront one.
	 */
	public function stored(array $address): array {
		foreach (self::TEXT_LIMITS as $field => $max) {
			if (isset($address[$field]) && $address[$field] !== null) {
				$address[$field] = Serializer::encodeTextWithin($field, $address[$field], $max);
			}
		}

		return $address;
	}

	/**
	 * API representation of an address (row or normalized array).
	 */
	public function represent(array $address, bool $is_default): array {
		return [
			'address_id'     => (int)($address['address_id'] ?? 0),
			'firstname'      => Serializer::decodeText((string)($address['firstname'] ?? '')),
			'lastname'       => Serializer::decodeText((string)($address['lastname'] ?? '')),
			'company'        => Serializer::decodeText((string)($address['company'] ?? '')),
			'address_1'      => Serializer::decodeText((string)($address['address_1'] ?? '')),
			'address_2'      => Serializer::decodeText((string)($address['address_2'] ?? '')),
			'city'           => Serializer::decodeText((string)($address['city'] ?? '')),
			'postcode'       => Serializer::decodeText((string)($address['postcode'] ?? '')),
			'country_id'     => (int)($address['country_id'] ?? 0),
			'country'        => (string)($address['country'] ?? ''),
			'iso_code_2'     => (string)($address['iso_code_2'] ?? ''),
			'iso_code_3'     => (string)($address['iso_code_3'] ?? ''),
			'address_format' => (string)($address['address_format'] ?? ''),
			'zone_id'        => (int)($address['zone_id'] ?? 0),
			'zone'           => (string)($address['zone'] ?? ''),
			'zone_code'      => (string)($address['zone_code'] ?? ''),
			'custom_field'   => is_array($address['custom_field'] ?? null) ? $address['custom_field'] : $this->decodeCustomField((string)($address['custom_field'] ?? '')),
			'is_default'     => $is_default,
		];
	}

	private function requireRow(int $address_id): array {
		if ($address_id <= 0) {
			throw new HttpException(Error::NOT_FOUND);
		}

		$query = $this->db()->query("SELECT a.*, c.`name` AS country, c.`iso_code_2`, c.`iso_code_3`, c.`address_format`, c.`postcode_required`, z.`name` AS zone, z.`code` AS zone_code FROM `" . DB_PREFIX . "address` a LEFT JOIN `" . DB_PREFIX . "country` c ON (a.`country_id` = c.`country_id`) LEFT JOIN `" . DB_PREFIX . "zone` z ON (a.`zone_id` = z.`zone_id`) WHERE a.`address_id` = '" . (int)$address_id . "' AND a.`customer_id` = '" . (int)$this->customer_id . "'");

		if (!$query->num_rows) {
			throw new HttpException(Error::NOT_FOUND);
		}

		return $query->row;
	}

	private function country(int $country_id): ?array {
		if ($country_id <= 0) {
			return null;
		}

		$query = $this->db()->query("SELECT * FROM `" . DB_PREFIX . "country` WHERE `country_id` = '" . (int)$country_id . "' AND `status` = '1'");

		return $query->num_rows ? $query->row : null;
	}

	private function zone(int $zone_id): ?array {
		$query = $this->db()->query("SELECT * FROM `" . DB_PREFIX . "zone` WHERE `zone_id` = '" . (int)$zone_id . "' AND `status` = '1'");

		return $query->num_rows ? $query->row : null;
	}

	private function countryHasZones(int $country_id): bool {
		$query = $this->db()->query("SELECT `zone_id` FROM `" . DB_PREFIX . "zone` WHERE `country_id` = '" . (int)$country_id . "' AND `status` = '1' LIMIT 1");

		return (bool)$query->num_rows;
	}

	private function defaultAddressId(): int {
		return (int)($this->customerRow()['address_id'] ?? 0);
	}

	/**
	 * An inline (not yet stored) address is never the default one.
	 */
	private function isDefault(array $address): bool {
		$address_id = (int)($address['address_id'] ?? 0);

		return $address_id > 0 && $address_id === $this->defaultAddressId();
	}

	private function customerRow(): array {
		$query = $this->db()->query("SELECT `customer_id`, `firstname`, `lastname`, `address_id` FROM `" . DB_PREFIX . "customer` WHERE `customer_id` = '" . (int)$this->customer_id . "'");

		return $query->num_rows ? $query->row : [];
	}

	private function decodeCustomField(string $value): array {
		$decoded = json_decode($value, true);

		return is_array($decoded) ? $decoded : [];
	}

	private function db() {
		return $this->registry->get('db');
	}
}
