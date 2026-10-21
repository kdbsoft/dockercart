<?php
class ControllerUserUserPermission extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('user/user_group');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('user/user_group');

		$this->getList();
	}

	public function add() {
		$this->load->language('user/user_group');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('user/user_group');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_user_user_group->addUserGroup($this->request->post);

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

			$this->response->redirect($this->url->link('user/user_permission', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function edit() {
		$this->load->language('user/user_group');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('user/user_group');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			if ((int)$this->request->get['user_group_id'] === \Cart\User::ADMINISTRATOR_GROUP_ID) {
				// Administrator is an implicit superuser - its stored document is
				// unused, so keep it as-is instead of letting the (empty) form wipe it.
				unset($this->request->post['permission']);
			}

			$this->model_user_user_group->editUserGroup($this->request->get['user_group_id'], $this->request->post);

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

			$this->response->redirect($this->url->link('user/user_permission', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function delete() {
		$this->load->language('user/user_group');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('user/user_group');

		if (isset($this->request->post['selected']) && $this->validateDelete()) {
			foreach ($this->request->post['selected'] as $user_group_id) {
				$this->model_user_user_group->deleteUserGroup($user_group_id);
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

			$this->response->redirect($this->url->link('user/user_permission', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	/**
	 * Creates the standard role groups (DockercartRoles::templates()) that are
	 * missing. Idempotent: existing template groups are detected by
	 * oc_user_group.template_code and left untouched.
	 */
	public function createRoles() {
		$this->load->language('user/user_group');

		$this->load->model('user/user_group');

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if ($this->request->server['REQUEST_METHOD'] != 'POST' || !$this->user->hasPermission('modify', 'user/user_permission')) {
			$this->session->data['error_warning'] = $this->language->get('error_permission');

			$this->response->redirect($this->url->link('user/user_permission', 'user_token=' . $this->session->data['user_token'] . $url, true));

			return;
		}

		$templates = array();

		foreach (DockercartRoles::templates() as $code => $template) {
			$templates[$code] = array(
				'name'       => $this->language->get($template['name']),
				'permission' => DockercartRoles::permission($template)
			);
		}

		$result = $this->model_user_user_group->installTemplates($templates);

		if ($result['created']) {
			$this->session->data['success'] = sprintf($this->language->get('text_roles_created'), count($result['created']), implode(', ', $result['created']));
		} else {
			$this->session->data['success'] = sprintf($this->language->get('text_roles_existing'), count($result['existing']));
		}

		$this->response->redirect($this->url->link('user/user_permission', 'user_token=' . $this->session->data['user_token'] . $url, true));
	}

	protected function getList() {
		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'name';
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

		$data['add'] = $this->url->link('user/user_permission/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['delete'] = $this->url->link('user/user_permission/delete', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['create_roles'] = $this->url->link('user/user_permission/createRoles', 'user_token=' . $this->session->data['user_token'] . $url, true);

		$data['user_groups'] = array();

		$filter_data = array(
			'sort'  => $sort,
			'order' => $order,
			'start' => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' => $this->config->get('config_limit_admin')
		);

		$user_group_total = $this->model_user_user_group->getTotalUserGroups();

		$role_names = $this->getRoleNames();

		$results = $this->model_user_user_group->getUserGroups($filter_data);

		foreach ($results as $result) {
			$template_code = isset($result['template_code']) ? (string)$result['template_code'] : '';

			$data['user_groups'][] = array(
				'user_group_id' => $result['user_group_id'],
				'name'          => $result['name'],
				'template_name' => isset($role_names[$template_code]) ? $role_names[$template_code] : '',
				'edit'          => $this->url->link('user/user_permission/edit', 'user_token=' . $this->session->data['user_token'] . '&user_group_id=' . $result['user_group_id'] . $url, true)
			);
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} elseif (isset($this->session->data['error_warning'])) {
			$data['error_warning'] = $this->session->data['error_warning'];

			unset($this->session->data['error_warning']);
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

		$data['sort_name'] = $this->url->link('user/user_permission', 'user_token=' . $this->session->data['user_token'] . '&sort=name' . $url, true);

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		$pagination = new Pagination();
		$pagination->total = $user_group_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('user/user_permission', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = $pagination->renderResults($this->language->get('text_pagination'));

		$data['sort'] = $sort;
		$data['order'] = $order;

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('user/user_group_list', $data));
	}

	protected function getForm() {
		$data['text_form'] = !isset($this->request->get['user_group_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['name'])) {
			$data['error_name'] = $this->error['name'];
		} else {
			$data['error_name'] = '';
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

		if (!isset($this->request->get['user_group_id'])) {
			$data['action'] = $this->url->link('user/user_permission/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		} else {
			$data['action'] = $this->url->link('user/user_permission/edit', 'user_token=' . $this->session->data['user_token'] . '&user_group_id=' . $this->request->get['user_group_id'] . $url, true);
		}

		$data['cancel'] = $this->url->link('user/user_permission', 'user_token=' . $this->session->data['user_token'] . $url, true);

		if (isset($this->request->get['user_group_id']) && $this->request->server['REQUEST_METHOD'] != 'POST') {
			$user_group_info = $this->model_user_user_group->getUserGroup($this->request->get['user_group_id']);
		}

		if (isset($this->request->post['name'])) {
			$data['name'] = $this->request->post['name'];
		} elseif (!empty($user_group_info)) {
			$data['name'] = $user_group_info['name'];
		} else {
			$data['name'] = '';
		}

		if (isset($this->request->post['template_code'])) {
			$data['template_code'] = $this->request->post['template_code'];
		} elseif (!empty($user_group_info['template_code'])) {
			$data['template_code'] = $user_group_info['template_code'];
		} else {
			$data['template_code'] = '';
		}

		$user_group_id = isset($this->request->get['user_group_id']) ? (int)$this->request->get['user_group_id'] : 0;

		// The Administrator group is an implicit superuser (see Cart\User), its
		// stored permission document is never consulted - so the form shows a
		// notice instead of the picker and the POSTed permissions are ignored.
		$data['is_superuser_group'] = ($user_group_id === \Cart\User::ADMINISTRATOR_GROUP_ID);

		if (isset($this->request->post['permission']['access'])) {
			$access = (array)$this->request->post['permission']['access'];
		} elseif (isset($user_group_info['permission']['access'])) {
			$access = (array)$user_group_info['permission']['access'];
		} else {
			$access = array();
		}

		if (isset($this->request->post['permission']['modify'])) {
			$modify = (array)$this->request->post['permission']['modify'];
		} elseif (isset($user_group_info['permission']['modify'])) {
			$modify = (array)$user_group_info['permission']['modify'];
		} else {
			$modify = array();
		}

		$data['permission_sections'] = $this->getPermissionSections($access, $modify);
		$data['capabilities'] = $this->getCapabilities($modify);
		$data['role_templates'] = $this->getRoleTemplates();

		$data['role_templates_json'] = json_encode($data['role_templates'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('user/user_group_form', $data));
	}

	/**
	 * Route picker data, grouped by admin menu section.
	 *
	 * Non-navigable routes (startup/*, event/*, mail/*) are hidden: they are
	 * never permission checked, so showing them only made the picker noisy.
	 *
	 * @param string[] $access granted access routes
	 * @param string[] $modify granted modify routes
	 * @return array<int, array{code: string, label: string, routes: array}>*
	 */
	protected function getPermissionSections(array $access, array $modify): array {
		$ignore = array(
			'common/dashboard',
			'common/startup',
			'common/login',
			'common/logout',
			'common/forgotten',
			'common/reset',
			'common/footer',
			'common/header',
			'error/not_found',
			'error/permission'
		);

		$routes = array();

		$files = array();

		// Make path into an array
		$path = array(DIR_APPLICATION . 'controller/*');

		// While the path array is still populated keep looping through
		while (count($path) != 0) {
			$next = array_shift($path);

			foreach (glob($next) as $file) {
				// If directory add to path array
				if (is_dir($file)) {
					$path[] = $file . '/*';
				}

				// Add the file to the files to be deleted array
				if (is_file($file)) {
					$files[] = $file;
				}
			}
		}

		// Sort the file array
		sort($files);

		foreach ($files as $file) {
			$controller = substr($file, strlen(DIR_APPLICATION . 'controller/'));

			$permission = substr($controller, 0, strrpos($controller, '.'));

			if (in_array($permission, $ignore) || DockercartRoles::isHidden($permission)) {
				continue;
			}

			$parts = explode('/', $permission);
			$parts = array_map(function($p) {
				return implode(' ', array_map('ucfirst', explode('_', $p)));
			}, $parts);

			$title = '';

			$this->load->language($permission, 'perm_' . str_replace('/', '_', $permission));

			if (isset($this->language->data['perm_' . str_replace('/', '_', $permission)]->data['heading_title'])) {
				$title = $this->language->data['perm_' . str_replace('/', '_', $permission)]->data['heading_title'];
			}

			$routes[$permission] = array(
				'route'  => $permission,
				'name'   => implode('/', $parts),
				'title'  => $title,
				'access' => in_array($permission, $access),
				'modify' => in_array($permission, $modify)
			);
		}

		$sections = DockercartRoles::sections();
		$labels = array();

		foreach ($sections as $code => $language_key) {
			$labels[$code] = $this->language->get($language_key);
		}

		$labels['other'] = $this->language->get('text_section_other');

		$data = array();

		foreach (DockercartRoles::groupBySection(array_keys($routes)) as $code => $section_routes) {
			$items = array();

			foreach ($section_routes as $route) {
				$items[] = $routes[$route];
			}

			$data[] = array(
				'code'   => $code,
				'label'  => isset($labels[$code]) ? $labels[$code] : $code,
				'routes' => $items
			);
		}

		return $data;
	}

	/**
	 * Capability pseudo routes (action level permissions) with their state.
	 *
	 * @param string[] $modify granted modify routes
	 * @return array<int, array{route: string, label: string, checked: bool}>*
	 */
	protected function getCapabilities(array $modify): array {
		$capabilities = array();

		foreach (DockercartRoles::capabilities() as $route => $language_key) {
			$capabilities[] = array(
				'route'   => $route,
				'label'   => $this->language->get($language_key),
				'checked' => in_array($route, $modify)
			);
		}

		return $capabilities;
	}

	/**
	 * Ready-made role templates for the "apply template" selector.
	 *
	 * @return array<int, array{code: string, name: string, access: string[], modify: string[]}>*
	 */
	protected function getRoleTemplates(): array {
		$templates = array();

		foreach (DockercartRoles::templates() as $code => $template) {
			$permission = DockercartRoles::permission($template);

			$templates[] = array(
				'code'   => $code,
				'name'   => $this->language->get($template['name']),
				'access' => $permission['access'],
				'modify' => $permission['modify']
			);
		}

		return $templates;
	}

	/**
	 * template_code => localized role name, for the group list.
	 *
	 * @return array<string, string>
	 */
	protected function getRoleNames(): array {
		$names = array();

		foreach (DockercartRoles::templates() as $code => $template) {
			$names[$code] = $this->language->get($template['name']);
		}

		return $names;
	}

	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'user/user_permission')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if ((utf8_strlen($this->request->post['name']) < 3) || (utf8_strlen($this->request->post['name']) > 64)) {
			$this->error['name'] = $this->language->get('error_name');
		}

		return !$this->error;
	}

	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'user/user_permission')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$this->load->model('user/user');

		foreach ($this->request->post['selected'] as $user_group_id) {
			if ((int)$user_group_id === \Cart\User::ADMINISTRATOR_GROUP_ID) {
				$this->error['warning'] = $this->language->get('error_delete_administrator');

				continue;
			}

			$user_total = $this->model_user_user->getTotalUsersByGroupId($user_group_id);

			if ($user_total) {
				$this->error['warning'] = sprintf($this->language->get('error_user'), $user_total);
			}
		}

		return !$this->error;
	}
}