<?php
/**
 * DockerCart admin — API keys (Settings → API → Keys).
 *
 * Creates, edits, rotates, revokes and deletes the bearer tokens used by the
 * REST API (see upload/api/index.php). A key either belongs to an admin user
 * (then it acts with the current group of that user) or to a user group
 * (a shared key), so the panel's permission model stays the only source of
 * truth.
 *
 * @package    DockerCart
 * @subpackage Admin
 */

declare(strict_types=1);

class ControllerSettingApiKey extends Controller {
	private array $error = [];

	public function index() {
		$this->load->language('setting/api_key');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/api_key');
		$this->load->model('setting/setting');

		$filter_user_id = isset($this->request->get['user_id']) ? (int)$this->request->get['user_id'] : 0;

		$groups = $this->model_setting_api_key->getGroups();

		$group_names = [];

		foreach ($groups as $group) {
			$group_names[(int)$group['user_group_id']] = (string)$group['name'];
		}

		$data['keys'] = [];

		foreach ($this->model_setting_api_key->getKeys(['user_id' => $filter_user_id]) as $key) {
			$owner_id = (int)($key['user_id'] ?? 0);

			// An owned key acts with the *current* group of its owner.
			$group_id = $owner_id ? (int)($key['owner_group_id'] ?? 0) : (int)$key['user_group_id'];

			$data['keys'][] = [
				'api_key_id'   => (int)$key['api_key_id'],
				'name'         => (string)$key['name'],
				'comment'      => (string)($key['comment'] ?? ''),
				'prefix'       => (string)$key['prefix'],
				'owner_id'     => $owner_id,
				'owner'        => $owner_id ? (string)($key['owner_username'] ?? '') : $this->language->get('text_shared'),
				'owner_link'   => $owner_id ? $this->url->link('user/user/edit', 'user_token=' . $this->session->data['user_token'] . '&user_id=' . $owner_id, true) : '',
				'group'        => $group_names[$group_id] ?? $this->language->get('text_unknown_group'),
				'status'       => (int)$key['status'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
				'status_value' => (int)$key['status'],
				'expires_at'   => $this->formatDate((string)($key['expires_at'] ?? '')),
				'is_expired'   => !empty($key['expires_at']) && strtotime((string)$key['expires_at']) <= time(),
				'last_used_at' => $this->formatDate((string)($key['last_used_at'] ?? '')),
				'last_used_ip' => (string)($key['last_used_ip'] ?? ''),
				'requests_24h' => (int)$key['requests_24h'],
				'requests_total' => (int)$key['requests_total'],
				'ip_allowlist' => (string)($key['ip_allowlist'] ?? ''),
				'rate_limit'   => (int)$key['rate_limit'],
				'edit'         => $this->url->link('setting/api_key/edit', 'user_token=' . $this->session->data['user_token'] . '&api_key_id=' . (int)$key['api_key_id'], true),
				'rotate'       => $this->url->link('setting/api_key/rotate', 'user_token=' . $this->session->data['user_token'] . '&api_key_id=' . (int)$key['api_key_id'], true),
				'revoke'       => $this->url->link('setting/api_key/revoke', 'user_token=' . $this->session->data['user_token'] . '&api_key_id=' . (int)$key['api_key_id'], true),
				'enable'       => $this->url->link('setting/api_key/enable', 'user_token=' . $this->session->data['user_token'] . '&api_key_id=' . (int)$key['api_key_id'], true),
				'delete'       => $this->url->link('setting/api_key/delete', 'user_token=' . $this->session->data['user_token'] . '&api_key_id=' . (int)$key['api_key_id'], true),
			];
		}

		$data['add'] = $this->url->link('setting/api_key/add', 'user_token=' . $this->session->data['user_token'], true);
		$data['log'] = $this->url->link('setting/api_log', 'user_token=' . $this->session->data['user_token'], true);
		$data['docs'] = $this->url->link('setting/api_docs', 'user_token=' . $this->session->data['user_token'], true);
		$data['settings_action'] = $this->url->link('setting/api_key/settings', 'user_token=' . $this->session->data['user_token'], true);
		$data['filter_action'] = $this->url->link('setting/api_key', 'user_token=' . $this->session->data['user_token'], true);

		$data['filter_user_id'] = $filter_user_id;

		$data['users'] = [];

		$data['filter_owner_name'] = '';

		foreach ($this->model_setting_api_key->getUsers() as $user) {
			$data['users'][] = [
				'user_id'       => (int)$user['user_id'],
				'username'      => (string)$user['username'],
				'full_name'     => trim((string)$user['firstname'] . ' ' . (string)$user['lastname']),
				'group_name'    => (string)($user['group_name'] ?? ''),
				'user_group_id' => (int)$user['user_group_id'],
			];

			if ($filter_user_id && (int)$user['user_id'] === $filter_user_id) {
				$data['filter_owner_name'] = (string)$user['username'];
			}
		}

		$data['total_keys'] = $this->model_setting_api_key->getTotalKeys();

		$data['config_api_status'] = $this->config->has('config_api_status') ? (int)$this->config->get('config_api_status') : 0;
		$data['config_api_rate_limit'] = $this->config->has('config_api_rate_limit') ? (int)$this->config->get('config_api_rate_limit') : 120;
		$data['config_api_log_retention_days'] = $this->config->has('config_api_log_retention_days') ? (int)$this->config->get('config_api_log_retention_days') : 30;
		$data['config_api_require_if_match'] = $this->config->has('config_api_require_if_match') ? (int)$this->config->get('config_api_require_if_match') : 0;

		$data['api_base_url'] = rtrim((string)HTTP_CATALOG, '/') . '/api/v1';

		$data['can_edit'] = $this->user->hasPermission('modify', 'setting/api_key');

		if (isset($this->session->data['success'])) {
			$data['success'] = (string)$this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['error_warning'] = isset($this->session->data['error_warning']) ? (string)$this->session->data['error_warning'] : '';

		unset($this->session->data['error_warning']);

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('setting/api_key_list', $data));
	}

	public function add() {
		$this->load->language('setting/api_key');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/api_key');

		if ($this->request->server['REQUEST_METHOD'] === 'POST' && $this->validateForm()) {
			$created = $this->model_setting_api_key->addKey($this->request->post);

			// The plain token is shown exactly once, right after creation.
			$this->session->data['success'] = sprintf($this->language->get('text_created'), $this->request->post['name']);

			$this->session->data['api_new_token'] = $created['token'];
			$this->session->data['api_new_token_key'] = $created['api_key_id'];

			$this->response->redirect($this->url->link('setting/api_key/edit', 'user_token=' . $this->session->data['user_token'] . '&api_key_id=' . $created['api_key_id'], true));
		}

		$this->getForm();
	}

	public function edit() {
		$this->load->language('setting/api_key');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/api_key');

		$api_key_id = isset($this->request->get['api_key_id']) ? (int)$this->request->get['api_key_id'] : 0;

		if ($this->request->server['REQUEST_METHOD'] === 'POST' && $this->validateForm()) {
			$this->model_setting_api_key->editKey($api_key_id, $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('setting/api_key', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->getForm();
	}

	public function rotate() {
		$this->load->language('setting/api_key');

		if (!$this->user->hasPermission('modify', 'setting/api_key')) {
			$this->session->data['error_warning'] = $this->language->get('error_permission');
			$this->response->redirect($this->url->link('setting/api_key', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->load->model('setting/api_key');

		$api_key_id = isset($this->request->get['api_key_id']) ? (int)$this->request->get['api_key_id'] : 0;

		$key = $this->model_setting_api_key->getKey($api_key_id);

		if (!$key) {
			$this->session->data['error_warning'] = $this->language->get('error_key');
			$this->response->redirect($this->url->link('setting/api_key', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->session->data['api_new_token'] = $this->model_setting_api_key->rotateKey($api_key_id);
		$this->session->data['api_new_token_key'] = $api_key_id;

		$this->session->data['success'] = sprintf($this->language->get('text_rotated'), $key['name']);

		$this->response->redirect($this->url->link('setting/api_key/edit', 'user_token=' . $this->session->data['user_token'] . '&api_key_id=' . $api_key_id, true));
	}

	public function revoke() {
		$this->setStatus(0, 'text_revoked');
	}

	public function enable() {
		$this->setStatus(1, 'text_enabled_success');
	}

	public function delete() {
		$this->load->language('setting/api_key');

		if (!$this->user->hasPermission('modify', 'setting/api_key')) {
			$this->session->data['error_warning'] = $this->language->get('error_permission');
		} else {
			$this->load->model('setting/api_key');

			$api_key_id = isset($this->request->get['api_key_id']) ? (int)$this->request->get['api_key_id'] : 0;

			$key = $this->model_setting_api_key->getKey($api_key_id);

			if ($key) {
				$this->model_setting_api_key->deleteKey($api_key_id);

				$this->session->data['success'] = sprintf($this->language->get('text_deleted'), $key['name']);
			} else {
				$this->session->data['error_warning'] = $this->language->get('error_key');
			}
		}

		$this->response->redirect($this->url->link('setting/api_key', 'user_token=' . $this->session->data['user_token'], true));
	}

	/**
	 * Saves the three global API settings (config_api_*). updateSetting() upserts
	 * only these keys — editSetting('config', …) would wipe every other store
	 * setting (see AGENTS.md).
	 */
	public function settings() {
		$this->load->language('setting/api_key');

		if (!$this->user->hasPermission('modify', 'setting/api_key') || $this->request->server['REQUEST_METHOD'] !== 'POST') {
			$this->session->data['error_warning'] = $this->language->get('error_permission');

			$this->response->redirect($this->url->link('setting/api_key', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->load->model('setting/setting');

		$this->model_setting_setting->updateSetting('config', [
			'config_api_status'             => (int)($this->request->post['config_api_status'] ?? 0),
			'config_api_rate_limit'         => max(0, (int)($this->request->post['config_api_rate_limit'] ?? 120)),
			'config_api_log_retention_days' => max(0, (int)($this->request->post['config_api_log_retention_days'] ?? 30)),
			'config_api_require_if_match'   => (int)($this->request->post['config_api_require_if_match'] ?? 0),
		]);

		$this->session->data['success'] = $this->language->get('text_settings_saved');

		$this->response->redirect($this->url->link('setting/api_key', 'user_token=' . $this->session->data['user_token'], true));
	}

	private function setStatus(int $status, string $success_key): void {
		$this->load->language('setting/api_key');

		if (!$this->user->hasPermission('modify', 'setting/api_key')) {
			$this->session->data['error_warning'] = $this->language->get('error_permission');
		} else {
			$this->load->model('setting/api_key');

			$api_key_id = isset($this->request->get['api_key_id']) ? (int)$this->request->get['api_key_id'] : 0;

			$key = $this->model_setting_api_key->getKey($api_key_id);

			if ($key) {
				$this->model_setting_api_key->setStatus($api_key_id, $status);

				$this->session->data['success'] = sprintf($this->language->get($success_key), $key['name']);
			} else {
				$this->session->data['error_warning'] = $this->language->get('error_key');
			}
		}

		$this->response->redirect($this->url->link('setting/api_key', 'user_token=' . $this->session->data['user_token'], true));
	}

	private function getForm(): void {
		$api_key_id = isset($this->request->get['api_key_id']) ? (int)$this->request->get['api_key_id'] : 0;

		$key = $api_key_id ? $this->model_setting_api_key->getKey($api_key_id) : [];

		if ($api_key_id && !$key) {
			$this->session->data['error_warning'] = $this->language->get('error_key');

			$this->response->redirect($this->url->link('setting/api_key', 'user_token=' . $this->session->data['user_token'], true));

			return;
		}

		$data['text_form'] = $api_key_id ? $this->language->get('text_edit') : $this->language->get('text_add');

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

		if (isset($this->session->data['success'])) {
			$data['success'] = (string)$this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		// Newly issued token — shown once, never stored in plain text.
		if (!empty($this->session->data['api_new_token']) && (int)($this->session->data['api_new_token_key'] ?? 0) === $api_key_id) {
			$data['new_token'] = (string)$this->session->data['api_new_token'];

			unset($this->session->data['api_new_token'], $this->session->data['api_new_token_key']);
		} else {
			$data['new_token'] = '';
		}

		$data['api_key_id'] = $api_key_id;

		if (isset($this->request->post['name'])) {
			$data['name'] = (string)$this->request->post['name'];
		} elseif ($key) {
			$data['name'] = (string)$key['name'];
		} else {
			$data['name'] = '';
		}

		if (isset($this->request->post['comment'])) {
			$data['comment'] = (string)$this->request->post['comment'];
		} elseif ($key) {
			$data['comment'] = (string)($key['comment'] ?? '');
		} else {
			$data['comment'] = '';
		}

		// Owner: an admin user the key belongs to (0 = shared key).
		if (isset($this->request->post['user_id'])) {
			$data['user_id'] = (int)$this->request->post['user_id'];
		} elseif ($key) {
			$data['user_id'] = (int)($key['user_id'] ?? 0);
		} else {
			$data['user_id'] = isset($this->request->get['user_id']) ? (int)$this->request->get['user_id'] : 0;
		}

		if (isset($this->request->post['user_group_id'])) {
			$data['user_group_id'] = (int)$this->request->post['user_group_id'];
		} elseif ($key) {
			$data['user_group_id'] = (int)$key['user_group_id'];
		} else {
			$data['user_group_id'] = 1;
		}

		if (isset($this->request->post['status'])) {
			$data['status'] = (int)$this->request->post['status'];
		} elseif ($key) {
			$data['status'] = (int)$key['status'];
		} else {
			$data['status'] = 1;
		}

		$expires_at = '';

		if (isset($this->request->post['expires_at'])) {
			$expires_at = (string)$this->request->post['expires_at'];
		} elseif ($key && !empty($key['expires_at'])) {
			$expires_at = substr((string)$key['expires_at'], 0, 10);
		}

		$data['expires_at'] = $expires_at;

		if (isset($this->request->post['ip_allowlist'])) {
			$data['ip_allowlist'] = (string)$this->request->post['ip_allowlist'];
		} elseif ($key) {
			$data['ip_allowlist'] = (string)($key['ip_allowlist'] ?? '');
		} else {
			$data['ip_allowlist'] = '';
		}

		if (isset($this->request->post['rate_limit'])) {
			$data['rate_limit'] = (int)$this->request->post['rate_limit'];
		} elseif ($key) {
			$data['rate_limit'] = (int)$key['rate_limit'];
		} else {
			$data['rate_limit'] = 0;
		}

		$data['prefix'] = $key ? (string)$key['prefix'] : '';
		$data['last_used_at'] = $key ? $this->formatDate((string)($key['last_used_at'] ?? '')) : '';
		$data['last_used_ip'] = $key ? (string)($key['last_used_ip'] ?? '') : '';
		$data['date_added'] = $key ? $this->formatDate((string)($key['date_added'] ?? '')) : '';

		// Permission sources + a readable summary of what each group grants.
		$data['groups'] = [];

		foreach ($this->model_setting_api_key->getGroups() as $group) {
			$permission = json_decode((string)($group['permission'] ?? ''), true);

			$access = is_array($permission['access'] ?? null) ? count($permission['access']) : 0;
			$modify = is_array($permission['modify'] ?? null) ? count($permission['modify']) : 0;

			$data['groups'][] = [
				'user_group_id' => (int)$group['user_group_id'],
				'name'          => (string)$group['name'],
				'is_superuser'  => (int)$group['user_group_id'] === 1,
				'access_count'  => $access,
				'modify_count'  => $modify,
			];
		}

		// Possible owners (admin users).
		$data['users'] = [];

		foreach ($this->model_setting_api_key->getUsers() as $user) {
			$data['users'][] = [
				'user_id'       => (int)$user['user_id'],
				'username'      => (string)$user['username'],
				'full_name'     => trim((string)$user['firstname'] . ' ' . (string)$user['lastname']),
				'group_name'    => (string)($user['group_name'] ?? ''),
				'user_group_id' => (int)$user['user_group_id'],
			];
		}

		$data['default_rate_limit'] = $this->config->has('config_api_rate_limit') ? (int)$this->config->get('config_api_rate_limit') : 120;

		$data['action'] = $this->url->link('setting/api_key/' . ($api_key_id ? 'edit' : 'add'), 'user_token=' . $this->session->data['user_token'] . ($api_key_id ? '&api_key_id=' . $api_key_id : ''), true);
		$data['cancel'] = $this->url->link('setting/api_key', 'user_token=' . $this->session->data['user_token'], true);
		$data['rotate'] = $api_key_id ? $this->url->link('setting/api_key/rotate', 'user_token=' . $this->session->data['user_token'] . '&api_key_id=' . $api_key_id, true) : '';
		$data['log'] = $api_key_id ? $this->url->link('setting/api_log', 'user_token=' . $this->session->data['user_token'] . '&api_key_id=' . $api_key_id, true) : '';
		$data['docs'] = $this->url->link('setting/api_docs', 'user_token=' . $this->session->data['user_token'], true);
		$data['api_base_url'] = rtrim((string)HTTP_CATALOG, '/') . '/api/v1';

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('setting/api_key_form', $data));
	}

	private function validateForm(): bool {
		if (!$this->user->hasPermission('modify', 'setting/api_key')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$name = trim((string)($this->request->post['name'] ?? ''));

		if (!\Dockercart\Api\KeyRules::isValidName($name)) {
			$this->error['name'] = $this->language->get('error_name');
		}

		$user_id = (int)($this->request->post['user_id'] ?? 0);

		if ($user_id) {
			// An owned key acts with the group of its owner — the group select is
			// only relevant for shared keys.
			if (!$this->model_setting_api_key->getOwner($user_id)) {
				$this->error['user_id'] = $this->language->get('error_owner');
			}
		} else {
			$user_group_id = (int)($this->request->post['user_group_id'] ?? 0);

			$group_ids = array_map(static function ($group) {
				return (int)$group['user_group_id'];
			}, $this->model_setting_api_key->getGroups());

			if ($user_group_id <= 0 || !in_array($user_group_id, $group_ids, true)) {
				$this->error['user_group_id'] = $this->language->get('error_group');
			}
		}

		$expires_at = trim((string)($this->request->post['expires_at'] ?? ''));

		if (!\Dockercart\Api\KeyRules::isValidExpires($expires_at)) {
			$this->error['expires_at'] = $this->language->get('error_expires');
		}

		$allowlist = trim((string)($this->request->post['ip_allowlist'] ?? ''));

		if (!\Dockercart\Api\KeyRules::isValidIpAllowlist($allowlist)) {
			$this->error['ip_allowlist'] = $this->language->get('error_ip_allowlist');
		}

		if (isset($this->request->post['rate_limit']) && (int)$this->request->post['rate_limit'] < 0) {
			$this->error['rate_limit'] = $this->language->get('error_rate_limit');
		}

		if ($this->error && !isset($this->error['warning'])) {
			$this->error['warning'] = $this->language->get('error_warning');
		}

		// Normalize the payload for the model.
		if (!$this->error) {
			$this->request->post['name'] = $name;
			$this->request->post['user_id'] = $user_id;
			$this->request->post['expires_at'] = $expires_at !== '' ? $expires_at . ' 23:59:59' : '';
			$this->request->post['ip_allowlist'] = $allowlist;
			$this->request->post['rate_limit'] = (int)($this->request->post['rate_limit'] ?? 0);
			$this->request->post['status'] = (int)($this->request->post['status'] ?? 0);
		}

		return !$this->error;
	}

	private function formatDate(string $value): string {
		if ($value === '' || $value === '0000-00-00 00:00:00' || $value === '0000-00-00') {
			return '';
		}

		$timestamp = strtotime($value);

		if ($timestamp === false) {
			return '';
		}

		return date($this->language->get('datetime_format'), $timestamp);
	}
}
