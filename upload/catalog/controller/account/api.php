<?php
/**
 * DockerCart storefront — customer API keys (Account > API).
 *
 * Self-service key management for a registered customer whose API access is
 * enabled (per customer group, overridable per customer). The customer can
 * create up to config_api_customer_max_keys keys, rotate/revoke/delete them and
 * narrow each key to a subset of the effective scopes. The plain token is shown
 * exactly once.
 *
 * @package    DockerCart
 * @subpackage Catalog
 */

declare(strict_types=1);

class ControllerAccountApi extends Controller {
	public function index() {
		if (!$this->customer->isLogged()) {
			$this->session->data['redirect'] = $this->url->link('account/api', '', true);

			$this->response->redirect($this->url->link('account/login', '', true));

			return;
		}

		$access = \Dockercart\Api\CustomerAccess::resolve($this->db, (int)$this->customer->getId());

		if (!$access['enabled']) {
			$this->response->redirect($this->url->link('account/account', '', true));

			return;
		}

		$this->load->language('account/api');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_account'),
			'href' => $this->url->link('account/account', '', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_api'),
			'href' => $this->url->link('account/api', '', true)
		);

		$data['text_api'] = $this->language->get('text_api');
		$data['text_api_help'] = $this->language->get('text_api_help');
		$data['text_scope_products_read'] = $this->language->get('text_scope_products_read');
		$data['text_scope_orders_read'] = $this->language->get('text_scope_orders_read');
		$data['text_scope_returns_read'] = $this->language->get('text_scope_returns_read');
		$data['text_scope_returns_write'] = $this->language->get('text_scope_returns_write');
		$data['text_scope_checkout_write'] = $this->language->get('text_scope_checkout_write');

		if (isset($this->session->data['success'])) {
			$data['success'] = (string)$this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['error_warning'] = '';

		if (isset($this->session->data['error_warning'])) {
			$data['error_warning'] = (string)$this->session->data['error_warning'];

			unset($this->session->data['error_warning']);
		}

		$this->load->model('account/api_key');

		$customer_id = (int)$this->customer->getId();

		$data['api_keys'] = array();

		foreach ($this->model_account_api_key->getKeys($customer_id) as $key) {
			$data['api_keys'][] = array(
				'api_key_id'     => (int)$key['api_key_id'],
				'name'           => (string)$key['name'],
				'comment'        => (string)($key['comment'] ?? ''),
				'prefix'         => (string)$key['prefix'],
				'status_value'   => (int)$key['status'],
				'status'         => (int)$key['status'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
				'is_expired'     => !empty($key['expires_at']) && strtotime((string)$key['expires_at']) <= time(),
				'expires_at'     => $this->formatDate((string)($key['expires_at'] ?? '')),
				'last_used_at'   => $this->formatDate((string)($key['last_used_at'] ?? '')),
				'requests_24h'   => (int)$key['requests_24h'],
				'requests_total' => (int)$key['requests_total'],
				'scopes'         => $this->scopeNames(\Dockercart\Api\CustomerScopes::decode($key['scopes'] ?? null), $access['scopes']),
			);
		}

		$data['csrf_token'] = $this->csrfToken();
		$data['action'] = $this->url->link('account/api/add', '', true);
		$data['rotate_action'] = $this->url->link('account/api/rotate', '', true);
		$data['status_action'] = $this->url->link('account/api/status', '', true);
		$data['delete_action'] = $this->url->link('account/api/delete', '', true);
		$data['docs'] = $this->url->link('account/api_docs', '', true);
		$data['api_base_url'] = rtrim((string)HTTP_SERVER, '/') . '/store-api/v1';
		$data['api_max_keys'] = $this->model_account_api_key->getMaxKeys();
		$data['api_can_add'] = $this->model_account_api_key->canAdd($customer_id);

		// Scope picker limited to the customer's effective scopes.
		$data['customer_scopes'] = array();

		foreach ($access['scopes'] as $scope) {
			$label_key = \Dockercart\Api\CustomerScopes::labels()[$scope] ?? '';

			$data['customer_scopes'][$scope] = $label_key !== '' ? $this->language->get($label_key) : $scope;
		}

		$data['new_token'] = '';

		if (!empty($this->session->data['api_new_token'])) {
			$data['new_token'] = (string)$this->session->data['api_new_token'];

			unset($this->session->data['api_new_token']);
		}

		$data['account_menu'] = $this->load->controller('common/account_menu');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/api_list', $data));
	}

	/**
	 * Creates a key for the logged-in customer.
	 */
	public function add() {
		if (!$this->customer->isLogged()) {
			$this->response->redirect($this->url->link('account/login', '', true));

			return;
		}

		$this->load->language('account/api');
		$this->load->model('account/api_key');

		if ($this->request->server['REQUEST_METHOD'] !== 'POST' || !$this->validateCsrf()) {
			$this->response->redirect($this->url->link('account/api', '', true));

			return;
		}

		$customer_id = (int)$this->customer->getId();

		$access = \Dockercart\Api\CustomerAccess::resolve($this->db, $customer_id);

		if (!$access['enabled']) {
			$this->response->redirect($this->url->link('account/account', '', true));

			return;
		}

		if (!$this->model_account_api_key->canAdd($customer_id)) {
			$this->session->data['error_warning'] = sprintf($this->language->get('error_key_limit'), $this->model_account_api_key->getMaxKeys());

			$this->response->redirect($this->url->link('account/api', '', true));

			return;
		}

		$name = trim((string)($this->request->post['name'] ?? ''));

		if (!\Dockercart\Api\KeyRules::isValidName($name)) {
			$this->session->data['error_warning'] = $this->language->get('error_name');

			$this->response->redirect($this->url->link('account/api', '', true));

			return;
		}

		$expires_at = trim((string)($this->request->post['expires_at'] ?? ''));

		if (!\Dockercart\Api\KeyRules::isValidExpires($expires_at)) {
			$this->session->data['error_warning'] = $this->language->get('error_expires');

			$this->response->redirect($this->url->link('account/api', '', true));

			return;
		}

		$allowlist = trim((string)($this->request->post['ip_allowlist'] ?? ''));

		if (!\Dockercart\Api\KeyRules::isValidIpAllowlist($allowlist)) {
			$this->session->data['error_warning'] = $this->language->get('error_ip_allowlist');

			$this->response->redirect($this->url->link('account/api', '', true));

			return;
		}

		$scopes = \Dockercart\Api\CustomerScopes::sanitize($this->request->post['scopes'] ?? []);
		$scopes = array_values(array_intersect($access['scopes'], $scopes));

		$created = $this->model_account_api_key->addKey($customer_id, [
			'name'         => $name,
			'comment'      => trim((string)($this->request->post['comment'] ?? '')),
			'expires_at'   => $expires_at !== '' ? $expires_at . ' 23:59:59' : '',
			'ip_allowlist' => $allowlist,
			'rate_limit'   => 0,
			'scopes'       => $scopes,
		]);

		$this->session->data['api_new_token'] = $created['token'];
		$this->session->data['success'] = sprintf($this->language->get('text_created'), $name);

		$this->response->redirect($this->url->link('account/api', '', true));
	}

	/**
	 * Issues a new token for one of the customer's keys.
	 */
	public function rotate() {
		$this->mutate('rotate');
	}

	/**
	 * Revokes / enables one of the customer's keys.
	 */
	public function status() {
		$this->mutate('status');
	}

	/**
	 * Deletes one of the customer's keys.
	 */
	public function delete() {
		$this->mutate('delete');
	}

	/**
	 * Shared POST handler for rotate / status / delete. Every branch is scoped to
	 * the logged-in customer by the model's WHERE clause.
	 */
	private function mutate(string $action): void {
		if (!$this->customer->isLogged()) {
			$this->response->redirect($this->url->link('account/login', '', true));

			return;
		}

		$this->load->language('account/api');
		$this->load->model('account/api_key');

		if ($this->request->server['REQUEST_METHOD'] !== 'POST' || !$this->validateCsrf()) {
			$this->response->redirect($this->url->link('account/api', '', true));

			return;
		}

		$customer_id = (int)$this->customer->getId();
		$api_key_id = (int)($this->request->post['api_key_id'] ?? 0);

		$key = $this->model_account_api_key->getKey($customer_id, $api_key_id);

		if (!$key) {
			$this->session->data['error_warning'] = $this->language->get('error_key');

			$this->response->redirect($this->url->link('account/api', '', true));

			return;
		}

		if ($action === 'rotate') {
			$this->session->data['api_new_token'] = $this->model_account_api_key->rotateKey($customer_id, $api_key_id);
			$this->session->data['success'] = sprintf($this->language->get('text_rotated'), (string)$key['name']);
		} elseif ($action === 'status') {
			$status = (int)($this->request->post['status'] ?? 0);

			$this->model_account_api_key->setStatus($customer_id, $api_key_id, $status);

			$this->session->data['success'] = $status
				? sprintf($this->language->get('text_enabled_success'), (string)$key['name'])
				: sprintf($this->language->get('text_revoked'), (string)$key['name']);
		} else {
			$this->model_account_api_key->deleteKey($customer_id, $api_key_id);

			$this->session->data['success'] = sprintf($this->language->get('text_deleted'), (string)$key['name']);
		}

		$this->response->redirect($this->url->link('account/api', '', true));
	}

	/**
	 * Human-readable scope names of a key: an empty key scope list means the full
	 * effective set.
	 *
	 * @param string[] $key_scopes
	 * @param string[] $effective
	 * @return string[]
	 */
	private function scopeNames(array $key_scopes, array $effective): array {
		$scopes = $key_scopes ? array_values(array_intersect($effective, $key_scopes)) : $effective;

		$names = [];

		foreach ($scopes as $scope) {
			$label_key = \Dockercart\Api\CustomerScopes::labels()[$scope] ?? '';

			$names[] = $label_key !== '' ? $this->language->get($label_key) : $scope;
		}

		return $names;
	}

	private function formatDate(string $value): string {
		if ($value === '' || $value === '0000-00-00 00:00:00' || $value === '0000-00-00') {
			return '';
		}

		$timestamp = strtotime($value);

		if ($timestamp === false) {
			return '';
		}

		return date($this->language->get('date_format_short'), $timestamp);
	}
}
