<?php
/**
 * DockerCart admin REST API — reference data.
 *
 *   GET /v1/meta/languages
 *   GET /v1/meta/stores
 *   GET /v1/meta/currencies
 *   GET /v1/meta/tax-classes
 *   GET /v1/meta/customer-groups
 *   GET /v1/meta/order-statuses
 *   GET /v1/meta/return-statuses
 *   GET /v1/meta/return-reasons
 *   GET /v1/meta/return-actions
 *   GET /v1/meta/sections
 *
 * An integrator cannot fill a product payload (language codes, store ids,
 * tax_class_id, customer_group_id) without this data. No specific permission is
 * required beyond a valid key — a key that may read nothing else can still read
 * the dictionaries.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

use Dockercart\Api\Routes;
use Dockercart\Api\Serializer;

class ControllerApiMeta extends \Dockercart\Api\Controller {
	public function languages() {
		$rows = [];

		foreach ($this->languageMap()->all() as $language) {
			$rows[] = [
				'language_id' => $language['language_id'],
				'code'        => $language['code'],
				'name'        => $language['name'],
				'locale'      => $language['locale'],
				'is_default'  => $language['language_id'] === $this->languageMap()->defaultId(),
			];
		}

		$this->respond($rows);
	}

	public function stores() {
		$this->load->model('setting/store');

		$rows = [[
			'store_id' => 0,
			'name'     => (string)$this->config->get('config_name'),
			'url'      => (string)$this->config->get('config_url'),
			'ssl'      => (string)$this->config->get('config_ssl'),
		]];

		foreach ($this->model_setting_store->getStores() as $store) {
			$rows[] = [
				'store_id' => (int)$store['store_id'],
				'name'     => (string)$store['name'],
				'url'      => (string)$store['url'],
				'ssl'      => (string)($store['ssl'] ?? ''),
			];
		}

		$this->respond($rows);
	}

	public function currencies() {
		$this->load->model('localisation/currency');

		$default_code = (string)$this->config->get('config_currency');

		$rows = [];

		foreach ($this->model_localisation_currency->getCurrencies() as $currency) {
			$rows[] = [
				'currency_id'   => (int)$currency['currency_id'],
				'title'         => (string)$currency['title'],
				'code'          => (string)$currency['code'],
				'symbol_left'   => (string)($currency['symbol_left'] ?? ''),
				'symbol_right'  => (string)($currency['symbol_right'] ?? ''),
				'decimal_place' => (int)($currency['decimal_place'] ?? 2),
				'value'         => (float)$currency['value'],
				'status'        => (int)$currency['status'],
				'is_default'    => (string)$currency['code'] === $default_code,
			];
		}

		$this->respond($rows);
	}

	public function taxClasses() {
		$this->load->model('localisation/tax_class');

		$rows = [];

		foreach ($this->model_localisation_tax_class->getTaxClasses() as $tax_class) {
			$rows[] = [
				'tax_class_id' => (int)$tax_class['tax_class_id'],
				'title'        => (string)$tax_class['title'],
				'description'  => (string)($tax_class['description'] ?? ''),
			];
		}

		$this->respond($rows);
	}

	public function customerGroups() {
		$this->load->model('customer/customer_group');
		$this->load->model('localisation/language');

		$default_id = (int)$this->config->get('config_customer_group_id');

		$rows = [];

		foreach ($this->model_customer_customer_group->getCustomerGroups() as $group) {
			$rows[] = [
				'customer_group_id' => (int)$group['customer_group_id'],
				'name'              => (string)$group['name'],
				'description'       => (string)($group['description'] ?? ''),
				'approval'          => (int)($group['approval'] ?? 0),
				'is_default'        => (int)$group['customer_group_id'] === $default_id,
			];
		}

		$this->respond($rows);
	}

	public function orderStatuses() {		$this->load->model('localisation/order_status');

		$rows = [];

		foreach ($this->model_localisation_order_status->getOrderStatuses() as $status) {
			$rows[] = [
				'order_status_id' => (int)$status['order_status_id'],
				'name'            => (string)$status['name'],
			];
		}

		$this->respond($rows);
	}

	public function returnStatuses() {
		$this->load->model('localisation/return_status');

		$rows = [];

		foreach ($this->model_localisation_return_status->getReturnStatuses() as $status) {
			$rows[] = [
				'return_status_id' => (int)$status['return_status_id'],
				'name'             => (string)$status['name'],
			];
		}

		$this->respond($rows);
	}

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
	 * Endpoint catalogue grouped by API section, with the permission each one
	 * requires. Powers the "what can this key do" preview and integration
	 * planning; it contains no secrets.
	 */
	public function sections() {
		$sections = [];

		foreach (Routes::table() as $entry) {
			$segment = explode('/', trim($entry['path'], '/'));
			$section = $segment[1] ?? 'misc';

			$sections[$section]['section'] = $section;
			$sections[$section]['endpoints'][] = [
				'method'     => $entry['method'],
				'path'       => $entry['path'],
				'read'       => $entry['read'],
				'write'      => $entry['write'],
			];
		}

		$this->respond(array_values($sections));
	}
}
