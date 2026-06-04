<?php
/**
 * DockerCart NovaPost Shipping Module — Catalog Event Controller
 * Injects NovaPost city/division selection into the DockerCart checkout.
 */
class ControllerExtensionShippingDockercartNovapost extends Controller {

	/**
	 * Event: catalog/view/common/header/after
	 * Injects NovaPost checkout CSS + JS.
	 */
	public function eventHeaderAfter(&$route, &$data, &$output): void {
		if (!$this->config->get('shipping_dockercart_novapost_status')) {
			return;
		}

		$this->load->language('extension/shipping/dockercart_novapost');

		// Pre-fill country_code and zone_id from session if available
		$countryCode = '';
		$zoneId = 0;
		if (!empty($this->session->data['shipping_address']['country_id'])) {
			$countryId = (int)$this->session->data['shipping_address']['country_id'];
			$query = $this->db->query("SELECT iso_code_2 FROM `" . DB_PREFIX . "country` WHERE country_id = '" . $countryId . "' AND status = '1'");
			$countryCode = $query->num_rows ? $query->row['iso_code_2'] : '';
			$zoneId = $this->session->data['shipping_address']['zone_id'] ?? 0;
		}

		// Build zone->city map for auto-fill (resolve localized city name)
		$languageId = (int)$this->config->get('config_language_id');
		$zoneCityMap = [];
		$zoneMapQuery = $this->db->query("
			SELECT rm.oc_zone_id,
				COALESCE(
					(SELECT COALESCE(NULLIF(d.city_name, ''), m.city_name)
					 FROM `" . DB_PREFIX . "dockercart_novapost_division` m
					 LEFT JOIN `" . DB_PREFIX . "dockercart_novapost_division_description` d
						ON d.division_id = m.division_id AND d.language_id = '" . (int)$languageId . "'
					 WHERE m.city_ref = rm.novapost_region_id
					 LIMIT 1),
					rm.city_name
				) AS city_name
			FROM `" . DB_PREFIX . "dockercart_novapost_region_map` rm
			WHERE rm.city_name != '' AND rm.oc_zone_id > 0
		");
		foreach ($zoneMapQuery->rows as $zmRow) {
			$zoneCityMap[(int)$zmRow['oc_zone_id']] = $zmRow['city_name'];
		}

		$npInit = json_encode([
			'country_code'  => $countryCode,
			'zone_id'       => $zoneId,
			'zone_city_map' => $zoneCityMap,
			'search_url'    => HTTP_SERVER . 'index.php?route=extension/shipping/dockercart_novapost/searchDivisions',
			'save_url'      => HTTP_SERVER . 'index.php?route=extension/shipping/dockercart_novapost/saveFields',
			'labels'        => [
				'select_city'           => $this->language->get('text_select_city'),
				'select_division'       => $this->language->get('text_select_division'),
				'search_city'           => $this->language->get('text_search_city'),
				'division'              => $this->language->get('text_division'),
				'nothing_found'         => $this->language->get('text_nothing_found'),
				'no_divisions_for_city' => $this->language->get('text_no_divisions_for_city'),
				'delivery_address'      => $this->language->get('text_delivery_address'),
				'city_invalid'          => $this->language->get('text_city_invalid'),
				'division_invalid'      => $this->language->get('text_division_invalid'),
				'city_required'         => $this->language->get('text_city_required'),
				'address_required'      => $this->language->get('text_address_required'),
			],
		]);

		$inject = '<link rel="stylesheet" href="catalog/view/theme/dockercart/stylesheet/nova_post_checkout.css?v=' . DOCKERCART_VERSION . '" />' . "\n";
		$inject .= '<script>window._novapost = ' . $npInit . ';</script>' . "\n";
		$inject .= '<script src="catalog/view/javascript/dockercart_novapost_checkout.js?v=' . DOCKERCART_VERSION . '" defer></script>' . "\n";
		$output = str_replace('</head>', $inject . '</head>', $output);
	}

	/**
	 * Event: catalog/controller/checkout/dockercart_checkout/shipping_address/after
	 * Injects novapost data (cities, search url) into the JSON response.
	 */
	public function eventShippingAddressAfter(&$route, &$data, &$output): void {
		if (!$this->config->get('shipping_dockercart_novapost_status')) {
			return;
		}

		$output = $output ?: $this->response->getOutput();
		if (empty($output)) {
			return;
		}

		$json = json_decode($output, true);
		if (!$json || empty($json['shipping_methods'])) {
			return;
		}

		// Only inject if NovaPost is available as a shipping method
		if (!isset($json['shipping_methods']['dockercart_novapost'])) {
			return;
		}

		// Resolve country from session shipping address or the first available
		$countryCode = '';
		$address = [];

		if (!empty($this->session->data['shipping_address']['country_id'])) {
			$countryId = (int)$this->session->data['shipping_address']['country_id'];
			$query = $this->db->query("SELECT iso_code_2 FROM `" . DB_PREFIX . "country` WHERE country_id = '" . $countryId . "' AND status = '1'");
			$countryCode = $query->num_rows ? $query->row['iso_code_2'] : '';
			$address = $this->session->data['shipping_address'];
		}

		// Build list of available delivery types based on what quotes exist
		$availableTypes = [];
		foreach ($json['shipping_methods']['dockercart_novapost']['quote'] ?? [] as $code => $quote) {
			$parts = explode('.', $quote['code']);
			$availableTypes[] = end($parts);
		}

		// Load language for frontend labels
		$this->load->language('extension/shipping/dockercart_novapost');

		// Build zone->city map for auto-fill (resolve localized city name)
		$languageId = (int)$this->config->get('config_language_id');
		$zoneCityMap = [];
		$zoneMapQuery = $this->db->query("
			SELECT rm.oc_zone_id,
				COALESCE(
					(SELECT COALESCE(NULLIF(d.city_name, ''), m.city_name)
					 FROM `" . DB_PREFIX . "dockercart_novapost_division` m
					 LEFT JOIN `" . DB_PREFIX . "dockercart_novapost_division_description` d
						ON d.division_id = m.division_id AND d.language_id = '" . (int)$languageId . "'
					 WHERE m.city_ref = rm.novapost_region_id
					 LIMIT 1),
					rm.city_name
				) AS city_name
			FROM `" . DB_PREFIX . "dockercart_novapost_region_map` rm
			WHERE rm.city_name != '' AND rm.oc_zone_id > 0
		");
		foreach ($zoneMapQuery->rows as $zmRow) {
			$zoneCityMap[(int)$zmRow['oc_zone_id']] = $zmRow['city_name'];
		}

		$json['novapost'] = [
			'enabled'       => true,
			'country_code'  => $countryCode,
			'zone_id'       => $address['zone_id'] ?? 0,
			'zone_city_map' => $zoneCityMap,
			'delivery_types' => $availableTypes,
			'search_url'    => HTTP_SERVER . 'index.php?route=extension/shipping/dockercart_novapost/searchDivisions',
			'save_url'      => HTTP_SERVER . 'index.php?route=extension/shipping/dockercart_novapost/saveFields',
			'labels'        => [
				'select_city'           => $this->language->get('text_select_city'),
				'select_division'       => $this->language->get('text_select_division'),
				'search_city'           => $this->language->get('text_search_city'),
				'division'              => $this->language->get('text_division'),
				'nothing_found'         => $this->language->get('text_nothing_found'),
				'no_divisions_for_city' => $this->language->get('text_no_divisions_for_city'),
				'delivery_address'      => $this->language->get('text_delivery_address'),
				'city_invalid'          => $this->language->get('text_city_invalid'),
				'division_invalid'      => $this->language->get('text_division_invalid'),
				'city_required'         => $this->language->get('text_city_required'),
				'address_required'      => $this->language->get('text_address_required'),
			],
		];

		$output = json_encode($json);
	}

	/**
	 * AJAX: Save NovaPost fields to session.
	 * POST body (JSON): { novapost_city, novapost_division, novapost_division_name }
	 */
	public function saveFields(): void {
		$input = file_get_contents('php://input');
		$body = json_decode($input, true);

		if ($body) {
			if (isset($body['novapost_city'])) {
				$this->session->data['novapost_city'] = strip_tags($body['novapost_city']);
			}
			if (isset($body['novapost_division'])) {
				$this->session->data['novapost_division'] = strip_tags($body['novapost_division']);
			}
			if (isset($body['novapost_division_name'])) {
				$this->session->data['novapost_division_name'] = strip_tags($body['novapost_division_name']);
			}
		}

		// Clear NovaPost data if a different method was sent
		if ($body && isset($body['shipping_method'])) {
			$shipping = explode('.', $body['shipping_method']);
			if (!isset($shipping[0]) || $shipping[0] !== 'dockercart_novapost') {
				unset($this->session->data['novapost_city']);
				unset($this->session->data['novapost_division']);
				unset($this->session->data['novapost_division_name']);
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode(['success' => true]));
	}

	/**
	 * AJAX: Search NovaPost divisions or cities.
	 * GET params:
	 *   city_query    — search cities by name prefix
	 *   city          — get divisions for a specific city
	 *   country       — ISO country code
	 *   query         — search divisions by keyword
	 *   delivery_type — branch/locker/courier
	 */
	public function searchDivisions(): void {
		$this->load->model('extension/shipping/dockercart_novapost');

		$countryCode = $this->request->get['country'] ?? '';
		$countryId = $this->request->get['country_id'] ?? '';
		$cityQuery = $this->request->get['city_query'] ?? '';
		$city = $this->request->get['city'] ?? '';
		$query = $this->request->get['query'] ?? '';
		$deliveryType = $this->request->get['delivery_type'] ?? '';
		$zoneId = $this->request->get['zone_id'] ?? '';

		// Resolve country code from country_id if ISO code not provided directly
		if ($countryCode === '' && $countryId !== '') {
			$queryCnt = $this->db->query("SELECT iso_code_2 FROM `" . DB_PREFIX . "country` WHERE country_id = '" . (int)$countryId . "' AND status = '1'");
			$countryCode = $queryCnt->num_rows ? $queryCnt->row['iso_code_2'] : '';
		}

		// Map delivery_type to categories
		$categories = [];
		if ($deliveryType === 'branch') {
			$categories = ['CargoBranch', 'PostBranch'];
		} elseif ($deliveryType === 'locker') {
			$categories = ['Postomat', 'PUDO'];
		}

		$results = [];

		if ($cityQuery) {
			// Search cities (filtered by zone/region if zone_id is provided)
			$results = $this->model_extension_shipping_dockercart_novapost->searchCities($cityQuery, $countryCode, 15, $zoneId);
		} elseif ($city) {
			// Get divisions for a specific city
			$results = $this->model_extension_shipping_dockercart_novapost->getDivisionsByCity($city, $countryCode);

			// Filter by category if specified
			if ($categories && $results) {
				$results = array_filter($results, function ($d) use ($categories) {
					return in_array($d['category'], $categories, true);
				});
				$results = array_values($results);
			}
		} elseif ($query) {
			// Free-text search across divisions
			$results = $this->model_extension_shipping_dockercart_novapost->searchDivisions($query, $countryCode, 30);

			// Filter by category if specified
			if ($categories && $results) {
				$results = array_filter($results, function ($d) use ($categories) {
					return in_array($d['category'], $categories, true);
				});
				$results = array_values($results);
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($results));
	}
}
