<?php
/**
 * DockerCart NovaPost Shipping Model (Catalog)
 * Calculates shipping quotes using NovaPost API.
 */

$novapostAutoloader = DIR_STORAGE . 'vendor/autoload.php';
if (is_file($novapostAutoloader)) {
	require_once $novapostAutoloader;
}

class ModelExtensionShippingDockercartNovapost extends Model {

	const SUPPORTED_COUNTRIES = [
		'CZ' => 'Czech Republic',
		'DE' => 'Germany',
		'EE' => 'Estonia',
		'ES' => 'Spain',
		'FR' => 'France',
		'GB' => 'United Kingdom',
		'HU' => 'Hungary',
		'IT' => 'Italy',
		'LT' => 'Lithuania',
		'LV' => 'Latvia',
		'MD' => 'Moldova',
		'NL' => 'Netherlands',
		'PL' => 'Poland',
		'RO' => 'Romania',
		'SK' => 'Slovakia',
		'UA' => 'Ukraine',
	];

	const DIVISION_CATEGORIES = [
		'CargoBranch',
		'PostBranch',
		'Postomat',
		'PUDO',
	];

	const DELIVERY_TYPES = [
		'branch'  => 'Branch (В отделение)',
		'locker'  => 'Locker (В почтомат)',
		'courier' => 'Courier (Курьером)',
	];

	public function getQuote(array $address): array {
		$this->load->language('extension/shipping/dockercart_novapost');

		if (!$this->config->get('shipping_dockercart_novapost_status')) {
			return [];
		}

		$cartWeight = $this->cart->getWeight();
		$cartTotal = $this->cart->getSubTotal();

		if ($cartWeight <= 0) {
			$cartWeight = 1;
		}

		$countryCode = $this->getCountryCode($address['country_id'] ?? 0);
		if (empty($countryCode)) {
			return [];
		}

		$calculationMethod = $this->config->get('shipping_dockercart_novapost_calculation_method') ?: 'tariff';

		if ($calculationMethod === 'api') {
			return $this->getApiQuote($address, $cartWeight, $cartTotal, $countryCode);
		}

		return $this->getTariffQuote($cartWeight, $cartTotal, $countryCode);
	}

	private function getTariffQuote(float $cartWeight, float $cartTotal, string $countryCode): array {
		$quote_data = [];
		$taxClassId = $this->config->get('shipping_dockercart_novapost_tax_class_id') ?: 0;

		$deliveryTypes = [
			'branch'  => 'text_branch',
			'locker'  => 'text_locker',
			'courier' => 'text_courier',
		];

		foreach ($deliveryTypes as $type => $langKey) {
			if (!$this->hasDivisionsForType($countryCode, $type)) {
				continue;
			}

			$tariff = $this->getApplicableTariff($countryCode, $type, $cartWeight);
			$cost = 0;
			$text = '';

			if ($tariff) {
				$cost = (float)$tariff['cost'];

				if ($tariff['free_shipping_from'] !== null && $cartTotal >= (float)$tariff['free_shipping_from']) {
					$cost = 0;
				}
			}

			$title = $this->language->get($langKey);

			if ($tariff && $cost <= 0) {
				$title .= ' (' . $this->language->get('text_free') . ')';
			}

			if (!$tariff) {
				$text = $this->language->get('text_carrier_rate');
			} else {
				$text = $this->currency->format(
					$this->tax->calculate($cost, $taxClassId, $this->config->get('config_tax')),
					$this->session->data['currency']
				);
			}

			$quote_data[$type] = [
				'code'         => 'dockercart_novapost.' . $type,
				'title'        => $title,
				'cost'         => $cost,
				'tax_class_id' => $taxClassId,
				'text'         => $text,
			];
		}

		if (!$quote_data) {
			return [];
		}

		return [
			'code'       => 'dockercart_novapost',
			'title'      => $this->language->get('text_title'),
			'quote'      => $quote_data,
			'sort_order' => $this->config->get('shipping_dockercart_novapost_sort_order'),
			'error'      => false,
		];
	}

	private function getApiQuote(array $address, float $cartWeight, float $cartTotal, string $countryCode): array {
		$apiKey = $this->config->get('shipping_dockercart_novapost_api_key');
		if (empty($apiKey)) {
			return [];
		}

		$sandbox = (bool)$this->config->get('shipping_dockercart_novapost_sandbox');
		$quote_data = [];

		try {
			$factory = new \NovaDigital\NovaPost\NovaPostApiFactory();
			$novaPostApi = $factory(apiKey: $apiKey, useSandbox: $sandbox);

			$payload = [
				'sender' => [
					'countryCode' => $countryCode,
					'addressParts' => [
						'city'   => $address['city'] ?? '',
						'street' => $address['address_1'] ?? '',
					],
				],
				'recipient' => [
					'countryCode' => $countryCode,
					'addressParts' => [
						'city'   => $address['city'] ?? '',
						'street' => $address['address_1'] ?? '',
					],
				],
				'parcels' => [
					[
						'cargoCategory' => 'parcel',
						'parcelDescription' => 'Order #' . ($this->session->data['order_id'] ?? 'quote'),
						'insuranceCost'   => $cartTotal,
						'rowNumber'       => 1,
						'width'           => 300,
						'length'          => 300,
						'height'          => 300,
						'actualWeight'    => $cartWeight * 1000,
					],
				],
			];

			$calculation = $novaPostApi->shipments()->calculate($payload);

			if (!empty($calculation['cost'])) {
				$cost = (float)$calculation['cost'];
				$taxClassId = $this->config->get('shipping_dockercart_novapost_tax_class_id') ?: 0;

				$title = $this->language->get('text_title');
				if (!empty($calculation['deliveryDays'])) {
					$title .= ' (' . $calculation['deliveryDays'] . ' ' . $this->language->get('text_days') . ')';
				}

				$quote_data['novapost'] = [
					'code'         => 'dockercart_novapost.novapost',
					'title'        => $title,
					'cost'         => $cost,
					'tax_class_id' => $taxClassId,
					'text'         => $this->currency->format(
						$this->tax->calculate($cost, $taxClassId, $this->config->get('config_tax')),
						$this->session->data['currency']
					),
				];
			}
		} catch (\Exception $e) {
			$this->log->write('NovaPost shipping API error: ' . $e->getMessage());
			return [];
		}

		if (!$quote_data) {
			return [];
		}

		return [
			'code'       => 'dockercart_novapost',
			'title'      => $this->language->get('text_title'),
			'quote'      => $quote_data,
			'sort_order' => $this->config->get('shipping_dockercart_novapost_sort_order'),
			'error'      => false,
		];
	}

	public function getDivisionsByCity(string $cityName, string $countryCode = '', ?int $languageId = null): array {
		if ($languageId === null) {
			$languageId = (int)$this->config->get('config_language_id');
		}
		$sql = "SELECT m.*,
			COALESCE(d.`name`, m.`name`) AS `name`,
			COALESCE(d.`short_address`, m.`short_address`) AS `short_address`,
			COALESCE(d.`full_address`, m.`full_address`) AS `full_address`,
			COALESCE(NULLIF(d.`city_name`, ''), m.`city_name`) AS `city_name`,
			COALESCE(d.`region_name`, m.`region_name`) AS `region_name`
			FROM `" . DB_PREFIX . "dockercart_novapost_division` m
			LEFT JOIN `" . DB_PREFIX . "dockercart_novapost_division_description` d
				ON d.`division_id` = m.`division_id` AND d.`language_id` = '" . (int)$languageId . "'
			WHERE (m.city_name = '" . $this->db->escape($cityName) . "' OR d.city_name = '" . $this->db->escape($cityName) . "')
			AND m.enabled = '1'";
		if ($countryCode !== '') {
			$sql .= " AND m.country_code = '" . $this->db->escape($countryCode) . "'";
		}
		$result = $this->db->query($sql);
		$rows = $result->rows;

		usort($rows, function (array $a, array $b): int {
			$partsA = explode('/', $a['number']);
			$partsB = explode('/', $b['number']);
			$aNum = (int)($partsA[1] ?? $partsA[0]);
			$bNum = (int)($partsB[1] ?? $partsB[0]);
			return $aNum - $bNum;
		});

		return $rows;
	}

	public function searchDivisions(string $query, string $countryCode = '', int $limit = 20, ?int $languageId = null): array {
		if ($languageId === null) {
			$languageId = (int)$this->config->get('config_language_id');
		}
		$sql = "SELECT m.*,
			COALESCE(d.`name`, m.`name`) AS `name`,
			COALESCE(d.`short_address`, m.`short_address`) AS `short_address`,
			COALESCE(d.`full_address`, m.`full_address`) AS `full_address`,
			COALESCE(NULLIF(d.`city_name`, ''), m.`city_name`) AS `city_name`,
			COALESCE(d.`region_name`, m.`region_name`) AS `region_name`
			FROM `" . DB_PREFIX . "dockercart_novapost_division` m
			LEFT JOIN `" . DB_PREFIX . "dockercart_novapost_division_description` d
				ON d.`division_id` = m.`division_id` AND d.`language_id` = '" . (int)$languageId . "'
			WHERE m.enabled = '1'";
		if ($countryCode !== '') {
			$sql .= " AND m.country_code = '" . $this->db->escape($countryCode) . "'";
		}
		$sql .= " LIMIT " . (int)$limit;
		$result = $this->db->query($sql);
		$rows = $result->rows;

		usort($rows, function (array $a, array $b): int {
			$partsA = explode('/', $a['number']);
			$partsB = explode('/', $b['number']);
			$aNum = (int)($partsA[1] ?? $partsA[0]);
			$bNum = (int)($partsB[1] ?? $partsB[0]);
			return $aNum - $bNum;
		});

		return $rows;
	}

	public function getDistinctCities(string $countryCode = ''): array {
		$languageId = (int)$this->config->get('config_language_id');
		$sql = "SELECT DISTINCT COALESCE(NULLIF(d.city_name, ''), m.city_name) AS city_name
			FROM `" . DB_PREFIX . "dockercart_novapost_division` m
			LEFT JOIN `" . DB_PREFIX . "dockercart_novapost_division_description` d
				ON d.division_id = m.division_id AND d.language_id = '" . $languageId . "'
			WHERE m.enabled = '1'";
		if ($countryCode !== '') {
			$sql .= " AND m.country_code = '" . $this->db->escape($countryCode) . "'";
		}
		$sql .= " ORDER BY COALESCE(NULLIF(d.city_name, ''), m.city_name) ASC";
		$query = $this->db->query($sql);
		return $query->rows;
	}

	public function searchCities(string $query, string $countryCode = '', int $limit = 15, string $zoneId = ''): array {
		$languageId = (int)$this->config->get('config_language_id');
		$escapedQuery = $this->db->escape($query);
		$sql = "SELECT DISTINCT COALESCE(NULLIF(d.city_name, ''), m.city_name) AS city_name
			FROM `" . DB_PREFIX . "dockercart_novapost_division` m
			LEFT JOIN `" . DB_PREFIX . "dockercart_novapost_division_description` d
				ON d.division_id = m.division_id AND d.language_id = '" . $languageId . "'
			WHERE m.enabled = '1'
			AND (m.city_name LIKE '%" . $escapedQuery . "%' OR d.city_name LIKE '%" . $escapedQuery . "%')";
		if ($countryCode !== '') {
			$sql .= " AND m.country_code = '" . $this->db->escape($countryCode) . "'";
		}
		if ($zoneId !== '') {
			$zoneId = (int)$zoneId;
			$sql .= " AND (
				NOT EXISTS (SELECT 1 FROM `" . DB_PREFIX . "dockercart_novapost_region_map`)
				OR m.parent_region_id IN (
					SELECT novapost_region_id FROM `" . DB_PREFIX . "dockercart_novapost_region_map` WHERE oc_zone_id = '" . $zoneId . "' AND city_name = ''
				)
				OR m.city_ref IN (
					SELECT novapost_region_id FROM `" . DB_PREFIX . "dockercart_novapost_region_map` WHERE oc_zone_id = '" . $zoneId . "' AND city_name != ''
				)
			)";
		}
		$sql .= " LIMIT 100";
		$result = $this->db->query($sql);
		$rows = $result->rows;

		// Filter: keep only rows where query matches the proper name (after stripping lowercase prefix)
		$strippedQuery = self::stripPrefix($query);
		$rows = array_filter($rows, function (array $row) use ($strippedQuery): bool {
			return mb_stripos(self::stripPrefix($row['city_name']), $strippedQuery) !== false;
		});

		// Sort by proper name (after stripping prefix)
		usort($rows, function (array $a, array $b): int {
			return strcasecmp(self::stripPrefix($a['city_name']), self::stripPrefix($b['city_name']));
		});

		return array_slice(array_values($rows), 0, $limit);
	}

	private function getCountryCode(int $countryId): string {
		if ($countryId <= 0) {
			return '';
		}
		$query = $this->db->query("SELECT iso_code_2 FROM `" . DB_PREFIX . "country` WHERE country_id = '" . (int)$countryId . "' AND status = '1'");
		return $query->num_rows ? $query->row['iso_code_2'] : '';
	}

	private function getApplicableTariff(string $countryCode, string $deliveryType, float $weight): ?array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "dockercart_novapost_tariff`
			WHERE country_code = '" . $this->db->escape($countryCode) . "'
			AND delivery_type = '" . $this->db->escape($deliveryType) . "'
			AND weight_from <= '" . (float)$weight . "'
			AND weight_to > '" . (float)$weight . "'
			AND status = '1'
			ORDER BY sort_order ASC, weight_from ASC
			LIMIT 1");
		return $query->num_rows ? $query->row : null;
	}

	private function hasDivisionsForType(string $countryCode, string $deliveryType): bool {
		if ($deliveryType === 'courier') {
			if ($countryCode === 'UA') {
				return true;
			}
			$query = $this->db->query("SELECT division_id FROM `" . DB_PREFIX . "dockercart_novapost_division`
				WHERE country_code = '" . $this->db->escape($countryCode) . "'
				AND enabled = '1'
				LIMIT 1");
			return $query->num_rows > 0;
		}

		$categories = [];
		if ($deliveryType === 'branch') {
			$categories = ['CargoBranch', 'PostBranch'];
		} elseif ($deliveryType === 'locker') {
			$categories = ['Postomat', 'PUDO'];
		}

		if (empty($categories)) {
			return false;
		}

		$escapedCats = [];
		foreach ($categories as $cat) {
			$escapedCats[] = "'" . $this->db->escape($cat) . "'";
		}
		$catList = implode(',', $escapedCats);
		$query = $this->db->query("SELECT division_id FROM `" . DB_PREFIX . "dockercart_novapost_division`
			WHERE country_code = '" . $this->db->escape($countryCode) . "'
			AND category IN (" . $catList . ")
			AND enabled = '1'
			LIMIT 1");
		return $query->num_rows > 0;
	}

	private static function stripPrefix(string $name): string {
		if (preg_match('/[А-ЯA-ZЄЇІҐ]/u', $name, $m, PREG_OFFSET_CAPTURE)) {
			return substr($name, $m[0][1]);
		}

		return $name;
	}
}
