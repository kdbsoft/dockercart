<?php
class ControllerExtensionModuleDockercartGdpr extends Controller {
	private $error = array();

	public function index() {
		$data = $this->load->language('extension/module/dockercart_gdpr');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->document->addScript('view/javascript/jquery/magnific/jquery.magnific-popup.min.js');
		$this->document->addStyle('view/javascript/jquery/magnific/magnific-popup.css');

		$this->load->model('setting/module');
		$this->load->model('extension/module/dockercart_gdpr');
		$this->load->model('localisation/language');
		$this->load->model('setting/store');

		$data['stores'] = $this->model_setting_store->getStores();
		$data['languages'] = $this->model_localisation_language->getLanguages();

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('module_dockercart_gdpr', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/dockercart_gdpr', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/module/dockercart_gdpr', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
		$data['user_token'] = $this->session->data['user_token'];

		$fields = array(
			'module_dockercart_gdpr_status' => 0,
			'module_dockercart_gdpr_framework' => 'eu',
			'module_dockercart_gdpr_banner_position' => 'bottom',
			'module_dockercart_gdpr_consent_expiry' => 365,
			'module_dockercart_gdpr_privacy_policy_id' => 0,
			'module_dockercart_gdpr_cookie_policy_id' => 0,
			'module_dockercart_gdpr_do_not_sell' => 1,
			'module_dockercart_gdpr_force_reconsent' => 0,
			'module_dockercart_gdpr_banner_title' => '',
			'module_dockercart_gdpr_banner_text' => '',
			'module_dockercart_gdpr_accept_text' => '',
			'module_dockercart_gdpr_reject_text' => '',
			'module_dockercart_gdpr_do_not_sell_text' => '',
			'module_dockercart_gdpr_privacy_link_text' => '',
		);

		foreach ($fields as $key => $default) {
			if (isset($this->request->post[$key])) {
				$data[$key] = $this->request->post[$key];
			} else {
				$data[$key] = $this->config->get($key) !== null ? $this->config->get($key) : $default;
			}
		}

		$this->load->model('catalog/information');

		$data['informations'] = $this->model_catalog_information->getInformations();

		$data['cookie_groups_url'] = $this->url->link('extension/module/dockercart_gdpr/cookieGroupList', 'user_token=' . $this->session->data['user_token'], true);
		$data['consent_log_url'] = $this->url->link('extension/module/dockercart_gdpr/consentLog', 'user_token=' . $this->session->data['user_token'], true);
		$data['requests_url'] = $this->url->link('extension/module/dockercart_gdpr/requestList', 'user_token=' . $this->session->data['user_token'], true);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/dockercart_gdpr', $data));
	}

	public function install() {
		$this->load->model('extension/module/dockercart_gdpr');
		$this->load->model('user/user_group');
		$this->load->model('setting/event');

		$this->model_extension_module_dockercart_gdpr->install();

		$group_id = (int)$this->user->getGroupId();
		$this->model_user_user_group->addPermission($group_id, 'access', 'extension/module/dockercart_gdpr');
		$this->model_user_user_group->addPermission($group_id, 'modify', 'extension/module/dockercart_gdpr');

		$this->model_setting_event->deleteEventByCode('dockercart_gdpr_admin_menu');
		$this->model_setting_event->addEvent(
			'dockercart_gdpr_admin_menu',
			'admin/view/common/column_left/before',
			'extension/module/dockercart_gdpr/eventAdminMenu',
			1,
			0
		);

		$this->dockercart_scheduler->registerTask(
			'gdpr_cleanup',
			'GDPR Consent Cleanup',
			'php /var/www/html/bin/dockercart_gdpr_cleanup.php',
			'0 3 * * *',
			true
		);
	}

	public function uninstall() {
		$this->load->model('extension/module/dockercart_gdpr');
		$this->load->model('setting/event');

		$this->model_setting_event->deleteEventByCode('dockercart_gdpr_admin_menu');

		$this->dockercart_scheduler->unregisterTask('gdpr_cleanup');

		$this->model_extension_module_dockercart_gdpr->uninstall();
	}

	public function eventAdminMenu(&$route, &$data, &$output) {
		$this->load->language('extension/module/dockercart_gdpr');

		if (!$this->user->hasPermission('access', 'extension/module/dockercart_gdpr')) {
			return;
		}

		$menu = array(
			'name' => $this->language->get('text_gdpr'),
			'href' => $this->url->link('extension/module/dockercart_gdpr', 'user_token=' . $this->session->data['user_token'], true),
			'children' => array()
		);

		if (!isset($data['menus']) || !is_array($data['menus'])) {
			return;
		}

		foreach ($data['menus'] as &$item) {
			if (isset($item['id']) && $item['id'] === 'menu-marketing' && isset($item['children']) && is_array($item['children'])) {
				$item['children'][] = $menu;
				return;
			}
		}

		$data['menus'][] = array(
			'id' => 'menu-dockercart-gdpr',
			'icon' => 'fa-shield',
			'name' => $this->language->get('text_gdpr'),
			'href' => $this->url->link('extension/module/dockercart_gdpr', 'user_token=' . $this->session->data['user_token'], true),
			'children' => array()
		);
	}

	public function consentLog() {
		$this->load->language('extension/module/dockercart_gdpr');
		$this->load->model('extension/module/dockercart_gdpr');

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$limit = 20;

		$filter_data = array(
			'filter_customer' => isset($this->request->get['filter_customer']) ? (string)$this->request->get['filter_customer'] : '',
			'filter_consent_type' => isset($this->request->get['filter_consent_type']) ? (string)$this->request->get['filter_consent_type'] : '',
			'start' => ($page - 1) * $limit,
			'limit' => $limit
		);

		$total = $this->model_extension_module_dockercart_gdpr->getTotalConsentLog($filter_data);
		$results = $this->model_extension_module_dockercart_gdpr->getConsentLog($filter_data);

		$data['consents'] = array();

		foreach ($results as $result) {
			$data['consents'][] = array(
				'consent_id' => $result['consent_id'],
				'customer' => $result['customer_id'] > 0 ? ($result['firstname'] . ' ' . $result['lastname'] . ' (' . $result['email'] . ')') : $this->language->get('text_guest'),
				'consent_type' => $result['consent_type'],
				'consent_value' => (int)$result['consent_value'] ? $this->language->get('text_allowed') : $this->language->get('text_denied'),
				'ip' => $result['ip'],
				'date_added' => $result['date_added']
			);
		}

		$pagination = new Pagination();
		$pagination->total = $total;
		$pagination->page = $page;
		$pagination->limit = $limit;
		$pagination->url = $this->url->link('extension/module/dockercart_gdpr/consentLog', 'user_token=' . $this->session->data['user_token'] . '&page={page}', true);

		$data['pagination'] = $pagination->render();
		$data['results'] = sprintf(
			$this->language->get('text_pagination'),
			($total) ? (($page - 1) * $limit) + 1 : 0,
			((($page - 1) * $limit) > ($total - $limit)) ? $total : ((($page - 1) * $limit) + $limit),
			$total,
			ceil($total / $limit)
		);

		$this->response->setOutput($this->load->view('extension/module/dockercart_gdpr_consent_log', $data));
	}

	public function requestList() {
		$this->load->language('extension/module/dockercart_gdpr');
		$this->load->model('extension/module/dockercart_gdpr');

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$limit = 20;

		$filter_data = array(
			'filter_status' => isset($this->request->get['filter_status']) ? (string)$this->request->get['filter_status'] : '',
			'filter_type' => isset($this->request->get['filter_type']) ? (string)$this->request->get['filter_type'] : '',
			'start' => ($page - 1) * $limit,
			'limit' => $limit
		);

		$total = $this->model_extension_module_dockercart_gdpr->getTotalRequests($filter_data);
		$results = $this->model_extension_module_dockercart_gdpr->getRequests($filter_data);

		$data['requests'] = array();

		foreach ($results as $result) {
			$data['requests'][] = array(
				'request_id' => $result['request_id'],
				'customer' => $result['firstname'] . ' ' . $result['lastname'] . ' (' . $result['email'] . ')',
				'type' => $result['type'],
				'status' => $result['status'],
				'date_added' => $result['date_added'],
				'date_processed' => $result['date_processed'],
				'approve' => $this->url->link('extension/module/dockercart_gdpr/approveRequest', 'user_token=' . $this->session->data['user_token'] . '&request_id=' . $result['request_id'], true),
				'deny' => $this->url->link('extension/module/dockercart_gdpr/denyRequest', 'user_token=' . $this->session->data['user_token'] . '&request_id=' . $result['request_id'], true),
				'download' => $result['type'] === 'export' ? $this->url->link('extension/module/dockercart_gdpr/downloadExport', 'user_token=' . $this->session->data['user_token'] . '&request_id=' . $result['request_id'], true) : ''
			);
		}

		$pagination = new Pagination();
		$pagination->total = $total;
		$pagination->page = $page;
		$pagination->limit = $limit;
		$pagination->url = $this->url->link('extension/module/dockercart_gdpr/requestList', 'user_token=' . $this->session->data['user_token'] . '&page={page}', true);

		$data['pagination'] = $pagination->render();
		$data['results'] = sprintf(
			$this->language->get('text_pagination'),
			($total) ? (($page - 1) * $limit) + 1 : 0,
			((($page - 1) * $limit) > ($total - $limit)) ? $total : ((($page - 1) * $limit) + $limit),
			$total,
			ceil($total / $limit)
		);

		$this->response->setOutput($this->load->view('extension/module/dockercart_gdpr_request_list', $data));
	}

	public function approveRequest() {
		$this->load->language('extension/module/dockercart_gdpr');
		$this->load->model('extension/module/dockercart_gdpr');

		if (!$this->user->hasPermission('modify', 'extension/module/dockercart_gdpr')) {
			$this->session->data['warning'] = $this->language->get('error_permission');
			$this->response->redirect($this->url->link('extension/module/dockercart_gdpr', 'user_token=' . $this->session->data['user_token'], true));
			return;
		}

		$request_id = isset($this->request->get['request_id']) ? (int)$this->request->get['request_id'] : 0;

		if ($request_id) {
			$request_info = $this->model_extension_module_dockercart_gdpr->getRequest($request_id);

			if ($request_info && $request_info['status'] === 'pending') {
				$this->load->library('dockercart/gdpr');
				$gdpr = new DockercartGdpr($this->registry);

				if ($request_info['type'] === 'export') {
					$this->model_extension_module_dockercart_gdpr->setRequestStatus($request_id, 'approved');
				} elseif ($request_info['type'] === 'delete') {
					$gdpr->anonymizeCustomer((int)$request_info['customer_id']);
					$this->model_extension_module_dockercart_gdpr->setRequestStatus($request_id, 'completed');
				}

				$this->session->data['success'] = $this->language->get('text_request_approved');
			}
		}

		$this->response->redirect($this->url->link('extension/module/dockercart_gdpr', 'user_token=' . $this->session->data['user_token'], true));
	}

	public function denyRequest() {
		$this->load->language('extension/module/dockercart_gdpr');
		$this->load->model('extension/module/dockercart_gdpr');

		if (!$this->user->hasPermission('modify', 'extension/module/dockercart_gdpr')) {
			$this->session->data['warning'] = $this->language->get('error_permission');
			$this->response->redirect($this->url->link('extension/module/dockercart_gdpr', 'user_token=' . $this->session->data['user_token'], true));
			return;
		}

		$request_id = isset($this->request->get['request_id']) ? (int)$this->request->get['request_id'] : 0;

		if ($request_id) {
			$this->model_extension_module_dockercart_gdpr->setRequestStatus($request_id, 'denied');
			$this->session->data['success'] = $this->language->get('text_request_denied');
		}

		$this->response->redirect($this->url->link('extension/module/dockercart_gdpr', 'user_token=' . $this->session->data['user_token'], true));
	}

	public function downloadExport() {
		$this->load->model('extension/module/dockercart_gdpr');

		$request_id = isset($this->request->get['request_id']) ? (int)$this->request->get['request_id'] : 0;
		$request_info = $this->model_extension_module_dockercart_gdpr->getRequest($request_id);

		if (!$request_info || $request_info['type'] !== 'export') {
			return;
		}

		$this->load->library('dockercart/gdpr');
		$gdpr = new DockercartGdpr($this->registry);

		$data = $gdpr->exportCustomerData((int)$request_info['customer_id']);

		$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

		$this->response->addHeader('Content-Type: application/json; charset=utf-8');
		$this->response->addHeader('Content-Disposition: attachment; filename="customer_data_' . (int)$request_info['customer_id'] . '.json"');
		$this->response->setOutput($json);
	}

	public function cookieGroupList() {
		$this->load->language('extension/module/dockercart_gdpr');
		$this->load->model('extension/module/dockercart_gdpr');

		$data['cookie_groups'] = $this->model_extension_module_dockercart_gdpr->getCookieGroups(array());

		foreach ($data['cookie_groups'] as &$group) {
			$group['edit'] = $this->url->link('extension/module/dockercart_gdpr/cookieGroupForm', 'user_token=' . $this->session->data['user_token'] . '&cookie_group_id=' . $group['cookie_group_id'], true);
			$group['delete'] = $this->url->link('extension/module/dockercart_gdpr/deleteCookieGroup', 'user_token=' . $this->session->data['user_token'] . '&cookie_group_id=' . $group['cookie_group_id'], true);
			$group['cookies'] = $this->url->link('extension/module/dockercart_gdpr/cookieList', 'user_token=' . $this->session->data['user_token'] . '&cookie_group_id=' . $group['cookie_group_id'], true);
		}

		$data['add'] = $this->url->link('extension/module/dockercart_gdpr/cookieGroupForm', 'user_token=' . $this->session->data['user_token'], true);

		$this->response->setOutput($this->load->view('extension/module/dockercart_gdpr_cookie_groups', $data));
	}

	public function cookieGroupForm() {
		$this->load->language('extension/module/dockercart_gdpr');
		$this->load->model('extension/module/dockercart_gdpr');
		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();

		$cookie_group_id = isset($this->request->get['cookie_group_id']) ? (int)$this->request->get['cookie_group_id'] : 0;

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			if ($cookie_group_id) {
				$this->model_extension_module_dockercart_gdpr->editCookieGroup($cookie_group_id, $this->request->post);
			} else {
				$cookie_group_id = $this->model_extension_module_dockercart_gdpr->addCookieGroup($this->request->post);
			}

			$json = array('success' => true, 'cookie_group_id' => $cookie_group_id);
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
			return;
		}

		if ($cookie_group_id) {
			$group_info = $this->model_extension_module_dockercart_gdpr->getCookieGroup($cookie_group_id);
		} else {
			$group_info = array();
		}

		$data['cookie_group_id'] = $cookie_group_id;
		$data['sort_order'] = isset($group_info['sort_order']) ? $group_info['sort_order'] : 0;
		$data['is_required'] = isset($group_info['is_required']) ? $group_info['is_required'] : 0;
		$data['status'] = isset($group_info['status']) ? $group_info['status'] : 1;
		$data['action'] = $this->url->link('extension/module/dockercart_gdpr/cookieGroupForm', 'user_token=' . $this->session->data['user_token'] . '&cookie_group_id=' . $cookie_group_id, true);

		$descriptions = $this->model_extension_module_dockercart_gdpr->getCookieGroupDescriptions($cookie_group_id);
		$data['group_description'] = array();

		foreach ($data['languages'] as $language) {
			$language_id = (int)$language['language_id'];
			$data['group_description'][$language_id] = isset($descriptions[$language_id]) ? $descriptions[$language_id] : array('name' => '', 'description' => '');
		}

		$this->response->setOutput($this->load->view('extension/module/dockercart_gdpr_cookie_group_form', $data));
	}

	public function deleteCookieGroup() {
		$this->load->language('extension/module/dockercart_gdpr');
		$this->load->model('extension/module/dockercart_gdpr');

		if (isset($this->request->get['cookie_group_id'])) {
			$this->model_extension_module_dockercart_gdpr->deleteCookieGroup((int)$this->request->get['cookie_group_id']);
			$this->session->data['success'] = $this->language->get('text_success_delete_group');
		}

		$this->response->redirect($this->url->link('extension/module/dockercart_gdpr', 'user_token=' . $this->session->data['user_token'], true));
	}

	public function cookieList() {
		$this->load->language('extension/module/dockercart_gdpr');
		$this->load->model('extension/module/dockercart_gdpr');

		$cookie_group_id = isset($this->request->get['cookie_group_id']) ? (int)$this->request->get['cookie_group_id'] : 0;

		$data['cookies'] = $this->model_extension_module_dockercart_gdpr->getCookies($cookie_group_id, array());

		foreach ($data['cookies'] as &$cookie) {
			$cookie['edit'] = $this->url->link('extension/module/dockercart_gdpr/cookieForm', 'user_token=' . $this->session->data['user_token'] . '&cookie_id=' . $cookie['cookie_id'], true);
			$cookie['delete'] = $this->url->link('extension/module/dockercart_gdpr/deleteCookie', 'user_token=' . $this->session->data['user_token'] . '&cookie_id=' . $cookie['cookie_id'], true);
		}

		$data['add'] = $this->url->link('extension/module/dockercart_gdpr/cookieForm', 'user_token=' . $this->session->data['user_token'] . '&cookie_group_id=' . $cookie_group_id, true);
		$data['cookie_group_id'] = $cookie_group_id;

		$this->response->setOutput($this->load->view('extension/module/dockercart_gdpr_cookies', $data));
	}

	public function cookieForm() {
		$this->load->language('extension/module/dockercart_gdpr');
		$this->load->model('extension/module/dockercart_gdpr');
		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();
		$data['cookie_groups'] = $this->model_extension_module_dockercart_gdpr->getCookieGroups(array('filter_status' => 1));

		$cookie_id = isset($this->request->get['cookie_id']) ? (int)$this->request->get['cookie_id'] : 0;

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			if ($cookie_id) {
				$this->model_extension_module_dockercart_gdpr->editCookie($cookie_id, $this->request->post);
			} else {
				$this->model_extension_module_dockercart_gdpr->addCookie($this->request->post);
			}

			$json = array('success' => true);
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
			return;
		}

		if ($cookie_id) {
			$cookie_info = $this->model_extension_module_dockercart_gdpr->getCookie($cookie_id);
		} else {
			$cookie_info = array();
		}

		$data['cookie_id'] = $cookie_id;
		$data['cookie_group_id'] = isset($this->request->get['cookie_group_id']) ? (int)$this->request->get['cookie_group_id'] : (isset($cookie_info['cookie_group_id']) ? $cookie_info['cookie_group_id'] : 0);
		$data['name'] = isset($cookie_info['name']) ? $cookie_info['name'] : '';
		$data['provider'] = isset($cookie_info['provider']) ? $cookie_info['provider'] : '';
		$data['domain'] = isset($cookie_info['domain']) ? $cookie_info['domain'] : '';
		$data['duration'] = isset($cookie_info['duration']) ? $cookie_info['duration'] : '';
		$data['sort_order'] = isset($cookie_info['sort_order']) ? $cookie_info['sort_order'] : 0;
		$data['status'] = isset($cookie_info['status']) ? $cookie_info['status'] : 1;

		$descriptions = $this->model_extension_module_dockercart_gdpr->getCookieDescriptions($cookie_id);
		$data['cookie_description'] = array();

		foreach ($data['languages'] as $language) {
			$language_id = (int)$language['language_id'];
			$data['cookie_description'][$language_id] = isset($descriptions[$language_id]) ? $descriptions[$language_id] : array('description' => '');
		}

		$this->response->setOutput($this->load->view('extension/module/dockercart_gdpr_cookie_form', $data));
	}

	public function deleteCookie() {
		$this->load->language('extension/module/dockercart_gdpr');
		$this->load->model('extension/module/dockercart_gdpr');

		if (isset($this->request->get['cookie_id'])) {
			$this->model_extension_module_dockercart_gdpr->deleteCookie((int)$this->request->get['cookie_id']);
			$this->session->data['success'] = $this->language->get('text_success_delete_cookie');
		}

		$this->response->redirect($this->url->link('extension/module/dockercart_gdpr', 'user_token=' . $this->session->data['user_token'], true));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/dockercart_gdpr')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}
