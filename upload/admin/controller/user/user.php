<?php
class ControllerUserUser extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('user/user');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('user/user');

		$this->getList();
	}

	public function add() {
		$this->load->language('user/user');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('user/user');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_user_user->addUser($this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('user/user', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function edit() {
		$this->load->language('user/user');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('user/user');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_user_user->editUser($this->request->get['user_id'], $this->request->post);

			// The keys of the user follow the new group of the user.
			$this->load->model('setting/api_key');
			$this->model_setting_api_key->syncUserGroup((int)$this->request->get['user_id'], (int)($this->request->post['user_group_id'] ?? 0));

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('user/user', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function delete() {
		$this->load->language('user/user');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('user/user');

		if (isset($this->request->post['selected']) && $this->validateDelete()) {
			foreach ($this->request->post['selected'] as $user_id) {
				$this->model_user_user->deleteUser($user_id);

				// The keys of a deleted user must not survive: they would be
				// orphaned credentials nobody can manage any more.
				$this->load->model('setting/api_key');
				$this->model_setting_api_key->deleteKeysByUser((int)$user_id);
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('user/user', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	/**
	 * Creates an API key for a user straight from the user form.
	 *
	 * The key belongs to the user, so it acts with the permissions of the
	 * current user group of that user (see ModelSettingApiKey::addKey()).
	 */
	public function api_key() {
		$this->load->language('user/user');
		$this->load->language('setting/api_key');

		$user_id = $this->apiKeyUserId();

		if (!$this->user->hasPermission('modify', 'setting/api_key')) {
			$this->session->data['error_warning'] = $this->language->get('error_api_permission');

			$this->apiKeyRedirect($user_id);

			return;
		}

		if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
			$this->apiKeyRedirect($user_id);

			return;
		}

		$this->load->model('user/user');
		$this->load->model('setting/api_key');

		$user_info = $this->model_user_user->getUser($user_id);

		if (!$user_info) {
			$this->session->data['error_warning'] = $this->language->get('error_api_user');

			$this->apiKeyRedirect($user_id);

			return;
		}

		$name = trim((string)($this->request->post['name'] ?? ''));

		if (!\Dockercart\Api\KeyRules::isValidName($name)) {
			$this->session->data['error_warning'] = $this->language->get('error_api_name');

			$this->apiKeyRedirect($user_id);

			return;
		}

		$expires_at = trim((string)($this->request->post['expires_at'] ?? ''));

		if (!\Dockercart\Api\KeyRules::isValidExpires($expires_at)) {
			$this->session->data['error_warning'] = $this->language->get('error_api_expires');

			$this->apiKeyRedirect($user_id);

			return;
		}

		$allowlist = trim((string)($this->request->post['ip_allowlist'] ?? ''));

		if (!\Dockercart\Api\KeyRules::isValidIpAllowlist($allowlist)) {
			$this->session->data['error_warning'] = $this->language->get('error_api_ip_allowlist');

			$this->apiKeyRedirect($user_id);

			return;
		}

		$created = $this->model_setting_api_key->addKey([
			'name'          => $name,
			'comment'       => trim((string)($this->request->post['comment'] ?? '')),
			'user_id'       => $user_id,
			'user_group_id' => (int)$user_info['user_group_id'],
			'status'        => 1,
			'expires_at'    => $expires_at !== '' ? $expires_at . ' 23:59:59' : '',
			'ip_allowlist'  => $allowlist,
			'rate_limit'    => max(0, (int)($this->request->post['rate_limit'] ?? 0)),
		]);

		// The plain token is shown exactly once, right after creation.
		$this->session->data['api_new_token'] = $created['token'];
		$this->session->data['api_new_token_user'] = $user_id;

		$this->session->data['success'] = sprintf($this->language->get('text_api_key_created'), $name);

		$this->apiKeyRedirect($user_id);
	}

	/**
	 * Issues a new token for one of the keys of a user.
	 */
	public function api_key_rotate() {
		$this->load->language('user/user');
		$this->load->language('setting/api_key');

		$user_id = $this->apiKeyUserId();

		if (!$this->user->hasPermission('modify', 'setting/api_key')) {
			$this->session->data['error_warning'] = $this->language->get('error_api_permission');

			$this->apiKeyRedirect($user_id);

			return;
		}

		$this->load->model('setting/api_key');

		$key = $this->apiKeyOwnedKey($user_id);

		if (!$key) {
			$this->session->data['error_warning'] = $this->language->get('error_api_key');

			$this->apiKeyRedirect($user_id);

			return;
		}

		$this->session->data['api_new_token'] = $this->model_setting_api_key->rotateKey((int)$key['api_key_id']);
		$this->session->data['api_new_token_user'] = $user_id;

		$this->session->data['success'] = sprintf($this->language->get('text_api_key_rotated'), (string)$key['name']);

		$this->apiKeyRedirect($user_id);
	}

	/**
	 * Revokes / enables one of the keys of a user.
	 */
	public function api_key_status() {
		$this->load->language('user/user');
		$this->load->language('setting/api_key');

		$user_id = $this->apiKeyUserId();

		if (!$this->user->hasPermission('modify', 'setting/api_key')) {
			$this->session->data['error_warning'] = $this->language->get('error_api_permission');

			$this->apiKeyRedirect($user_id);

			return;
		}

		$this->load->model('setting/api_key');

		$key = $this->apiKeyOwnedKey($user_id);

		if (!$key) {
			$this->session->data['error_warning'] = $this->language->get('error_api_key');

			$this->apiKeyRedirect($user_id);

			return;
		}

		$status = (int)($this->request->get['status'] ?? 0);

		$this->model_setting_api_key->setStatus((int)$key['api_key_id'], $status);

		$this->session->data['success'] = $status
			? sprintf($this->language->get('text_api_key_enabled'), (string)$key['name'])
			: sprintf($this->language->get('text_api_key_revoked'), (string)$key['name']);

		$this->apiKeyRedirect($user_id);
	}

	/**
	 * Deletes one of the keys of a user.
	 */
	public function api_key_delete() {
		$this->load->language('user/user');
		$this->load->language('setting/api_key');

		$user_id = $this->apiKeyUserId();

		if (!$this->user->hasPermission('modify', 'setting/api_key')) {
			$this->session->data['error_warning'] = $this->language->get('error_api_permission');

			$this->apiKeyRedirect($user_id);

			return;
		}

		$this->load->model('setting/api_key');

		$key = $this->apiKeyOwnedKey($user_id);

		if (!$key) {
			$this->session->data['error_warning'] = $this->language->get('error_api_key');

			$this->apiKeyRedirect($user_id);

			return;
		}

		$this->model_setting_api_key->deleteKey((int)$key['api_key_id']);

		$this->session->data['success'] = sprintf($this->language->get('text_api_key_deleted'), (string)$key['name']);

		$this->apiKeyRedirect($user_id);
	}

	/**
	 * The user the API key actions act on.
	 */
	private function apiKeyUserId(): int {
		return isset($this->request->get['user_id']) ? (int)$this->request->get['user_id'] : 0;
	}

	/**
	 * The key of the request — only when it really belongs to the user.
	 */
	private function apiKeyOwnedKey(int $user_id): array {
		$api_key_id = isset($this->request->get['api_key_id']) ? (int)$this->request->get['api_key_id'] : 0;

		if (!$api_key_id || !$user_id) {
			return [];
		}

		$key = $this->model_setting_api_key->getKey($api_key_id);

		if (!$key || (int)($key['user_id'] ?? 0) !== $user_id) {
			return [];
		}

		return $key;
	}

	/**
	 * Back to the form of the user.
	 */
	private function apiKeyRedirect(int $user_id): void {
		$this->response->redirect($this->url->link('user/user/edit', 'user_token=' . $this->session->data['user_token'] . '&user_id=' . $user_id, true));
	}

	/**
	 * A date of the panel in the format of the panel.
	 */
	private function apiKeyDate(string $value): string {
		if ($value === '' || $value === '0000-00-00 00:00:00' || $value === '0000-00-00') {
			return '';
		}

		$timestamp = strtotime($value);

		if ($timestamp === false) {
			return '';
		}

		return date($this->language->get('datetime_format'), $timestamp);
	}

	protected function getList() {
		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'username';
		}

		if (isset($this->request->get['order'])) {
			$order = $this->request->get['order'];
		} else {
			$order = 'ASC';
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['add'] = $this->url->link('user/user/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['delete'] = $this->url->link('user/user/delete', 'user_token=' . $this->session->data['user_token'] . $url, true);

		$data['users'] = array();

		$filter_data = array(
			'sort'  => $sort,
			'order' => $order,
			'start' => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' => $this->config->get('config_limit_admin')
		);

		$user_total = $this->model_user_user->getTotalUsers();

		$results = $this->model_user_user->getUsers($filter_data);

		foreach ($results as $result) {
			$data['users'][] = array(
				'user_id'    => $result['user_id'],
				'username'   => $result['username'],
				'status'     => ($result['status'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled')),
				'date_added' => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'edit'       => $this->url->link('user/user/edit', 'user_token=' . $this->session->data['user_token'] . '&user_id=' . $result['user_id'] . $url, true)
			);
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if (isset($this->request->post['selected'])) {
			$data['selected'] = (array)$this->request->post['selected'];
		} else {
			$data['selected'] = array();
		}

		$url = '';

		if ($order == 'ASC') {
			$url .= '&order=DESC';
		} else {
			$url .= '&order=ASC';
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['sort_username'] = $this->url->link('user/user', 'user_token=' . $this->session->data['user_token'] . '&sort=username' . $url, true);
		$data['sort_status'] = $this->url->link('user/user', 'user_token=' . $this->session->data['user_token'] . '&sort=status' . $url, true);
		$data['sort_date_added'] = $this->url->link('user/user', 'user_token=' . $this->session->data['user_token'] . '&sort=date_added' . $url, true);

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		$pagination = new Pagination();
		$pagination->total = $user_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('user/user', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = $pagination->renderResults($this->language->get('text_pagination'));

		$data['sort'] = $sort;
		$data['order'] = $order;

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('user/user_list', $data));
	}

	protected function getForm() {
		$data['text_form'] = !isset($this->request->get['user_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');
		$data['text_form_subtitle'] = !isset($this->request->get['user_id'])
		    ? $this->language->get('text_add_user_subtitle')
		    : $this->language->get('text_edit_user_subtitle');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['username'])) {
			$data['error_username'] = $this->error['username'];
		} else {
			$data['error_username'] = '';
		}

		if (isset($this->error['password'])) {
			$data['error_password'] = $this->error['password'];
		} else {
			$data['error_password'] = '';
		}

		if (isset($this->error['confirm'])) {
			$data['error_confirm'] = $this->error['confirm'];
		} else {
			$data['error_confirm'] = '';
		}

		if (isset($this->error['firstname'])) {
			$data['error_firstname'] = $this->error['firstname'];
		} else {
			$data['error_firstname'] = '';
		}

		if (isset($this->error['lastname'])) {
			$data['error_lastname'] = $this->error['lastname'];
		} else {
			$data['error_lastname'] = '';
		}

		if (isset($this->error['email'])) {
			$data['error_email'] = $this->error['email'];
		} else {
			$data['error_email'] = '';
		}

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		if (!isset($this->request->get['user_id'])) {
			$data['action'] = $this->url->link('user/user/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		} else {
			$data['action'] = $this->url->link('user/user/edit', 'user_token=' . $this->session->data['user_token'] . '&user_id=' . $this->request->get['user_id'] . $url, true);
		}

		$data['cancel'] = $this->url->link('user/user', 'user_token=' . $this->session->data['user_token'] . $url, true);

		if (isset($this->request->get['user_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$user_info = $this->model_user_user->getUser($this->request->get['user_id']);
		}

		if (isset($this->request->post['username'])) {
			$data['username'] = $this->request->post['username'];
		} elseif (!empty($user_info)) {
			$data['username'] = $user_info['username'];
		} else {
			$data['username'] = '';
		}

		if (isset($this->request->post['user_group_id'])) {
			$data['user_group_id'] = $this->request->post['user_group_id'];
		} elseif (!empty($user_info)) {
			$data['user_group_id'] = $user_info['user_group_id'];
		} else {
			$data['user_group_id'] = '';
		}

		$this->load->model('user/user_group');

		$data['user_groups'] = $this->model_user_user_group->getUserGroups();

		if (isset($this->request->post['password'])) {
			$data['password'] = $this->request->post['password'];
		} else {
			$data['password'] = '';
		}

		if (isset($this->request->post['confirm'])) {
			$data['confirm'] = $this->request->post['confirm'];
		} else {
			$data['confirm'] = '';
		}

		if (isset($this->request->post['firstname'])) {
			$data['firstname'] = $this->request->post['firstname'];
		} elseif (!empty($user_info)) {
			$data['firstname'] = $user_info['firstname'];
		} else {
			$data['firstname'] = '';
		}

		if (isset($this->request->post['lastname'])) {
			$data['lastname'] = $this->request->post['lastname'];
		} elseif (!empty($user_info)) {
			$data['lastname'] = $user_info['lastname'];
		} else {
			$data['lastname'] = '';
		}

		if (isset($this->request->post['email'])) {
			$data['email'] = $this->request->post['email'];
		} elseif (!empty($user_info)) {
			$data['email'] = $user_info['email'];
		} else {
			$data['email'] = '';
		}

		if (isset($this->request->post['image'])) {
			$data['image'] = $this->request->post['image'];
		} elseif (!empty($user_info)) {
			$data['image'] = $user_info['image'];
		} else {
			$data['image'] = '';
		}

		$this->load->model('tool/image');

		if (isset($this->request->post['image']) && is_file(DIR_IMAGE . $this->request->post['image'])) {
			$data['thumb'] = $this->model_tool_image->resize($this->request->post['image'], 100, 100);
		} elseif (!empty($user_info) && $user_info['image'] && is_file(DIR_IMAGE . $user_info['image'])) {
			$data['thumb'] = $this->model_tool_image->resize($user_info['image'], 100, 100);
		} else {
			$data['thumb'] = $this->model_tool_image->resize('no_image.png', 100, 100);
		}
		
		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', 100, 100);

		if (isset($this->request->post['status'])) {
			$data['status'] = $this->request->post['status'];
		} elseif (!empty($user_info)) {
			$data['status'] = $user_info['status'];
		} else {
			$data['status'] = 0;
		}

		// API keys of the user — shown only to operators who may manage keys.
		$data['api_can_view'] = $this->user->hasPermission('access', 'setting/api_key');
		$data['api_can_edit'] = $this->user->hasPermission('modify', 'setting/api_key');
		$data['api_keys'] = [];
		$data['api_create_action'] = '';
		$data['api_new_token'] = '';
		$data['api_base_url'] = rtrim((string)HTTP_CATALOG, '/') . '/api/v1';

		$user_id = isset($this->request->get['user_id']) ? (int)$this->request->get['user_id'] : 0;

		if ($data['api_can_view'] && $user_id) {
			$this->load->model('setting/api_key');

			$user_token = $this->session->data['user_token'];

			foreach ($this->model_setting_api_key->getKeys(['user_id' => $user_id]) as $key) {
				$data['api_keys'][] = [
					'api_key_id'   => (int)$key['api_key_id'],
					'name'         => (string)$key['name'],
					'comment'      => (string)($key['comment'] ?? ''),
					'prefix'       => (string)$key['prefix'],
					'status_value' => (int)$key['status'],
					'status'       => (int)$key['status'] ? $this->language->get('text_api_enabled') : $this->language->get('text_api_disabled'),
					'expires_at'   => $this->apiKeyDate((string)($key['expires_at'] ?? '')),
					'is_expired'   => !empty($key['expires_at']) && strtotime((string)$key['expires_at']) <= time(),
					'last_used_at' => $this->apiKeyDate((string)($key['last_used_at'] ?? '')),
					'requests_24h' => (int)$key['requests_24h'],
					'requests_total' => (int)$key['requests_total'],
					'rate_limit'   => (int)$key['rate_limit'],
					'ip_allowlist' => (string)($key['ip_allowlist'] ?? ''),
					'rotate'       => $this->url->link('user/user/api_key_rotate', 'user_token=' . $user_token . '&user_id=' . $user_id . '&api_key_id=' . (int)$key['api_key_id'], true),
					'revoke'       => $this->url->link('user/user/api_key_status', 'user_token=' . $user_token . '&user_id=' . $user_id . '&api_key_id=' . (int)$key['api_key_id'] . '&status=0', true),
					'enable'       => $this->url->link('user/user/api_key_status', 'user_token=' . $user_token . '&user_id=' . $user_id . '&api_key_id=' . (int)$key['api_key_id'] . '&status=1', true),
					'delete'       => $this->url->link('user/user/api_key_delete', 'user_token=' . $user_token . '&user_id=' . $user_id . '&api_key_id=' . (int)$key['api_key_id'], true),
					'edit'         => $this->url->link('setting/api_key/edit', 'user_token=' . $user_token . '&api_key_id=' . (int)$key['api_key_id'], true),
				];
			}

			$data['api_create_action'] = $this->url->link('user/user/api_key', 'user_token=' . $user_token . '&user_id=' . $user_id, true);

			// A freshly issued token is shown once, on the page of its owner.
			if (!empty($this->session->data['api_new_token']) && (int)($this->session->data['api_new_token_user'] ?? 0) === $user_id) {
				$data['api_new_token'] = (string)$this->session->data['api_new_token'];

				unset($this->session->data['api_new_token'], $this->session->data['api_new_token_user']);
			}
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('user/user_form', $data));
	}

	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'user/user')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if ((utf8_strlen($this->request->post['username']) < 3) || (utf8_strlen($this->request->post['username']) > 20)) {
			$this->error['username'] = $this->language->get('error_username');
		}

		$user_info = $this->model_user_user->getUserByUsername($this->request->post['username']);

		if (!isset($this->request->get['user_id'])) {
			if ($user_info) {
				$this->error['warning'] = $this->language->get('error_exists_username');
			}
		} else {
			if ($user_info && ($this->request->get['user_id'] != $user_info['user_id'])) {
				$this->error['warning'] = $this->language->get('error_exists_username');
			}
		}

		if ((utf8_strlen($this->request->post['firstname']) < 1) || (utf8_strlen($this->request->post['firstname']) > 32)) {
			$this->error['firstname'] = $this->language->get('error_firstname');
		}

		if ((utf8_strlen($this->request->post['lastname']) < 1) || (utf8_strlen($this->request->post['lastname']) > 32)) {
			$this->error['lastname'] = $this->language->get('error_lastname');
		}

		if ((utf8_strlen($this->request->post['email']) > 96) || !filter_var($this->request->post['email'], FILTER_VALIDATE_EMAIL)) {
			$this->error['email'] = $this->language->get('error_email');
		}

		$user_info = $this->model_user_user->getUserByEmail($this->request->post['email']);

		if (!isset($this->request->get['user_id'])) {
			if ($user_info) {
				$this->error['warning'] = $this->language->get('error_exists_email');
			}
		} else {
			if ($user_info && ($this->request->get['user_id'] != $user_info['user_id'])) {
				$this->error['warning'] = $this->language->get('error_exists_email');
			}
		}

		if ($this->request->post['password'] || (!isset($this->request->get['user_id']))) {
			if ((utf8_strlen(html_entity_decode($this->request->post['password'], ENT_QUOTES, 'UTF-8')) < 8) || (utf8_strlen(html_entity_decode($this->request->post['password'], ENT_QUOTES, 'UTF-8')) > 40)) {
				$this->error['password'] = $this->language->get('error_password');
			}

			if ($this->request->post['password'] != $this->request->post['confirm']) {
				$this->error['confirm'] = $this->language->get('error_confirm');
			}
		}

		$total_users = $this->model_user_user->getTotalUsers();

		if ($total_users <= 1 && isset($this->request->post['status']) && $this->request->post['status'] == 0) {
			$this->error['warning'] = $this->language->get('error_single_user');
		}

		return !$this->error;
	}

	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'user/user')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		foreach ($this->request->post['selected'] as $user_id) {
			if ($this->user->getId() == $user_id) {
				$this->error['warning'] = $this->language->get('error_account');
			}
		}

		return !$this->error;
	}
}