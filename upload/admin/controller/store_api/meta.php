<?php
/**
 * DockerCart customer (storefront) REST API — reference data.
 *
 *   GET /store-api/v1/meta/return-reasons    reasons a customer may pick
 *   GET /store-api/v1/meta/return-actions    actions a customer may request
 *   GET /store-api/v1/meta/countries         countries an address can use
 *   GET /store-api/v1/meta/countries/{id}/zones  zones of a country
 *
 * Read-only dictionaries so a client can build a valid POST /v1/returns or
 * POST /v1/addresses payload without shipping the whole world in the app.
 *
 * @package    DockerCart
 * @subpackage StoreApi
 */

declare(strict_types=1);

class ControllerStoreApiMeta extends \Dockercart\Api\Controller {
	public function returnReasons() {
		$this->load->model('localisation/return_reason');

		$rows = [];

		foreach ($this->model_localisation_return_reason->getReturnReasons() as $reason) {
			$rows[] = [
				'return_reason_id' => (int)$reason['return_reason_id'],
				'name'             => (string)$reason['name'],
			];
		}

		$this->respond($rows);
	}

	public function returnActions() {
		$this->load->model('localisation/return_action');

		$rows = [];

		foreach ($this->model_localisation_return_action->getReturnActions() as $action) {
			$rows[] = [
				'return_action_id' => (int)$action['return_action_id'],
				'name'             => (string)$action['name'],
			];
		}

		$this->respond($rows);
	}

	/**
	 * Enabled countries, ordered by name.
	 */
	public function countries() {
		$this->load->model('localisation/country');

		$rows = [];

		foreach ($this->model_localisation_country->getCountries() as $country) {
			if (!(int)$country['status']) {
				continue;
			}

			$rows[] = [
				'country_id'        => (int)$country['country_id'],
				'name'              => (string)$country['name'],
				'iso_code_2'        => (string)$country['iso_code_2'],
				'iso_code_3'        => (string)$country['iso_code_3'],
				'postcode_required' => (bool)(int)$country['postcode_required'],
			];
		}

		$this->respond($rows);
	}

	/**
	 * Enabled zones of a country (empty list for countries without zones).
	 */
	public function zones() {
		$country_id = $this->param('country_id');

		if ($country_id <= 0) {
			throw new \Dockercart\Api\HttpException(\Dockercart\Api\Error::NOT_FOUND);
		}

		$this->load->model('localisation/zone');

		$rows = [];

		foreach ($this->model_localisation_zone->getZonesByCountryId($country_id) as $zone) {
			if (isset($zone['status']) && !(int)$zone['status']) {
				continue;
			}

			$rows[] = [
				'zone_id'    => (int)$zone['zone_id'],
				'country_id' => (int)$zone['country_id'],
				'name'       => (string)$zone['name'],
				'code'       => (string)($zone['code'] ?? ''),
			];
		}

		$this->respond($rows);
	}
}
