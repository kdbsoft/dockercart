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

	public function getQuote(array $address): array {
		$this->load->language('extension/shipping/dockercart_novapost');

		if (!$this->config->get('shipping_dockercart_novapost_status')) {
			return [];
		}

		$quote_data = [];

		$cartWeight = $this->cart->getWeight();
		$cartTotal = $this->cart->getSubTotal();

		if ($cartWeight <= 0) {
			return [];
		}

		$apiKey = $this->config->get('shipping_dockercart_novapost_api_key');
		if (empty($apiKey)) {
			return [];
		}

		$sandbox = (bool)$this->config->get('shipping_dockercart_novapost_sandbox');

		try {
			$factory = new \NovaDigital\NovaPost\NovaPostApiFactory();
			$novaPostApi = $factory(apiKey: $apiKey, useSandbox: $sandbox);

			$countryCode = $this->getCountryCode($address['country_id'] ?? 0);
			if (empty($countryCode)) {
				return [];
			}

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
			$this->log->write('NovaPost shipping calculation error: ' . $e->getMessage());
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
			COALESCE(d.`city_name`, m.`city_name`) AS `city_name`,
			COALESCE(d.`region_name`, m.`region_name`) AS `region_name`
			FROM `" . DB_PREFIX . "dockercart_novapost_division` m
			LEFT JOIN `" . DB_PREFIX . "dockercart_novapost_division_description` d
				ON d.`division_id` = m.`division_id` AND d.`language_id` = '" . (int)$languageId . "'
			WHERE (m.city_name = '" . $this->db->escape($cityName) . "' OR d.city_name = '" . $this->db->escape($cityName) . "')
			AND m.enabled = '1'";
		if ($countryCode !== '') {
			$sql .= " AND m.country_code = '" . $this->db->escape($countryCode) . "'";
		}
		$sql .= " ORDER BY COALESCE(d.`name`, m.`name`) ASC";
		$query = $this->db->query($sql);
		return $query->rows;
	}

	public function searchDivisions(string $query, string $countryCode = '', int $limit = 20, ?int $languageId = null): array {
		if ($languageId === null) {
			$languageId = (int)$this->config->get('config_language_id');
		}
		$sql = "SELECT m.*,
			COALESCE(d.`name`, m.`name`) AS `name`,
			COALESCE(d.`short_address`, m.`short_address`) AS `short_address`,
			COALESCE(d.`full_address`, m.`full_address`) AS `full_address`,
			COALESCE(d.`city_name`, m.`city_name`) AS `city_name`,
			COALESCE(d.`region_name`, m.`region_name`) AS `region_name`
			FROM `" . DB_PREFIX . "dockercart_novapost_division` m
			LEFT JOIN `" . DB_PREFIX . "dockercart_novapost_division_description` d
				ON d.`division_id` = m.`division_id` AND d.`language_id` = '" . (int)$languageId . "'
			WHERE m.enabled = '1'
			AND (m.name LIKE '%" . $this->db->escape($query) . "%'
				OR d.name LIKE '%" . $this->db->escape($query) . "%'
				OR m.short_address LIKE '%" . $this->db->escape($query) . "%'
				OR d.short_address LIKE '%" . $this->db->escape($query) . "%'
				OR m.city_name LIKE '%" . $this->db->escape($query) . "%'
				OR d.city_name LIKE '%" . $this->db->escape($query) . "%')";
		if ($countryCode !== '') {
			$sql .= " AND m.country_code = '" . $this->db->escape($countryCode) . "'";
		}
		$sql .= " ORDER BY COALESCE(d.`name`, m.`name`) ASC LIMIT " . (int)$limit;
		$result = $this->db->query($sql);
		return $result->rows;
	}

	private function getCountryCode(int $countryId): string {
		if ($countryId <= 0) {
			return '';
		}
		$query = $this->db->query("SELECT iso_code_2 FROM `" . DB_PREFIX . "country` WHERE country_id = '" . (int)$countryId . "' AND status = '1'");
		return $query->num_rows ? $query->row['iso_code_2'] : '';
	}
}
