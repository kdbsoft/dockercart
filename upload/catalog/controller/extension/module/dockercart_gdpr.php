<?php
class ControllerExtensionModuleDockercartGdpr extends Controller {
	public function index($setting) {
		if (!isset($setting['module_dockercart_gdpr_status']) || !(int)$setting['module_dockercart_gdpr_status']) {
			return '';
		}

		$this->load->language('extension/module/dockercart_gdpr');

		$data['framework'] = $setting['module_dockercart_gdpr_framework'] ?? 'eu';
		$data['position'] = $setting['module_dockercart_gdpr_banner_position'] ?? 'bottom';
		$data['consent_expiry'] = (int)($setting['module_dockercart_gdpr_consent_expiry'] ?? 365);
		$data['do_not_sell_enabled'] = !empty($setting['module_dockercart_gdpr_do_not_sell']);

		$language_id = (int)$this->config->get('config_language_id');

		$data['banner_title'] = ($setting['module_dockercart_gdpr_banner_title'] ?? '') ?: $this->language->get('text_banner_title');
		$data['banner_text'] = ($setting['module_dockercart_gdpr_banner_text'] ?? '') ?: $this->language->get('text_banner_text');
		$data['accept_text'] = ($setting['module_dockercart_gdpr_accept_text'] ?? '') ?: $this->language->get('text_accept_all');
		$data['reject_text'] = ($setting['module_dockercart_gdpr_reject_text'] ?? '') ?: $this->language->get('text_reject_all');
		$data['do_not_sell_text'] = ($setting['module_dockercart_gdpr_do_not_sell_text'] ?? '') ?: $this->language->get('text_do_not_sell');
		$data['privacy_link_text'] = ($setting['module_dockercart_gdpr_privacy_link_text'] ?? '') ?: $this->language->get('text_privacy_policy');

		$privacy_policy_id = (int)($setting['module_dockercart_gdpr_privacy_policy_id'] ?? 0);
		$cookie_policy_id = (int)($setting['module_dockercart_gdpr_cookie_policy_id'] ?? 0);

		$data['privacy_url'] = $privacy_policy_id ? $this->url->link('information/information', 'information_id=' . $privacy_policy_id) : '';
		$data['cookie_url'] = $cookie_policy_id ? $this->url->link('information/information', 'information_id=' . $cookie_policy_id) : '';

		$data['accept_url'] = $this->url->link('extension/module/dockercart_gdpr/accept');
		$data['reject_url'] = $this->url->link('extension/module/dockercart_gdpr/reject');
		$data['save_url'] = $this->url->link('extension/module/dockercart_gdpr/savePreferences');
		$data['do_not_sell_url'] = $this->url->link('extension/module/dockercart_gdpr/doNotSell');

		$this->load->model('extension/module/dockercart_gdpr');
		$data['cookie_groups'] = $this->model_extension_module_dockercart_gdpr->getCookieGroups($language_id);

		$data['module_id'] = 'dc-gdpr-' . mt_rand(1000, 999999);
		$data['dockercart_version'] = defined('DOCKERCART_VERSION') ? DOCKERCART_VERSION : '';

		return $this->load->view('extension/module/dockercart_gdpr', $data);
	}

	public function accept() {
		$this->logConsent('all', true);
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode(array('success' => true)));
	}

	public function reject() {
		$this->logConsent('all', false);
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode(array('success' => true)));
	}

	public function savePreferences() {
		$json = array('success' => false);

		if ($this->request->server['REQUEST_METHOD'] === 'POST') {
			$preferences = isset($this->request->post['preferences']) ? (array)$this->request->post['preferences'] : array();

			$this->load->model('extension/module/dockercart_gdpr');
			$this->load->model('extension/module/dockercart_gdpr');

			$language_id = (int)$this->config->get('config_language_id');
			$groups = $this->model_extension_module_dockercart_gdpr->getCookieGroups($language_id);
			$customer_id = $this->customer->isLogged() ? (int)$this->customer->getId() : 0;

			foreach ($groups as $group) {
				$group_id = (int)$group['cookie_group_id'];
				$allowed = in_array('group_' . $group_id, $preferences);
				$this->logConsent('cookie_group_' . $group_id, $allowed, $customer_id);
			}

			$json['success'] = true;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function doNotSell() {
		$json = array('success' => false);

		if ($this->request->server['REQUEST_METHOD'] === 'POST') {
			$opt_out = !empty($this->request->post['opt_out']);
			$customer_id = $this->customer->isLogged() ? (int)$this->customer->getId() : 0;

			if ($customer_id) {
				$this->load->model('extension/module/dockercart_gdpr');

				if ($opt_out) {
					$this->model_extension_module_dockercart_gdpr->doNotSellOptOut($customer_id);
				} else {
					$this->model_extension_module_dockercart_gdpr->doNotSellOptIn($customer_id);
				}

				$json['success'] = true;
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function account() {
		if (!$this->customer->isLogged()) {
			$this->session->data['redirect'] = $this->url->link('extension/module/dockercart_gdpr/account');
			$this->response->redirect($this->url->link('account/login'));
			return;
		}

		$this->load->language('extension/module/dockercart_gdpr');
		$this->load->model('extension/module/dockercart_gdpr');

		$this->document->setTitle($this->language->get('text_account_title'));

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_account'),
			'href' => $this->url->link('account/account')
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_account_title'),
			'href' => $this->url->link('extension/module/dockercart_gdpr/account')
		);

		$customer_id = (int)$this->customer->getId();
		$language_id = (int)$this->config->get('config_language_id');

		$data['cookie_groups'] = $this->model_extension_module_dockercart_gdpr->getCookieGroups($language_id);

		$this->load->library('dockercart/gdpr');
		$gdpr = new DockercartGdpr($this->registry);

		$data['consents'] = array();
		foreach ($data['cookie_groups'] as $group) {
			$data['consents']['group_' . $group['cookie_group_id']] = $gdpr->hasConsented($customer_id, 'cookie_group_' . $group['cookie_group_id']);
		}

		$data['has_pending_export'] = $gdpr->hasPendingRequest($customer_id, 'export');
		$data['has_pending_delete'] = $gdpr->hasPendingRequest($customer_id, 'delete');

		$data['framework'] = $this->config->get('module_dockercart_gdpr_framework') ?: 'eu';
		$data['do_not_sell_enabled'] = !empty($this->config->get('module_dockercart_gdpr_do_not_sell'));
		$data['do_not_sell_opted_out'] = $gdpr->hasConsented($customer_id, 'do_not_sell');

		$data['save_url'] = $this->url->link('extension/module/dockercart_gdpr/savePreferences');
		$data['export_url'] = $this->url->link('extension/module/dockercart_gdpr/requestExport');
		$data['delete_url'] = $this->url->link('extension/module/dockercart_gdpr/requestDelete');
		$data['do_not_sell_url'] = $this->url->link('extension/module/dockercart_gdpr/doNotSell');
		$data['continue'] = $this->url->link('account/account');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('extension/module/dockercart_gdpr_account', $data));
	}

	public function requestExport() {
		$json = array('success' => false);

		if (!$this->customer->isLogged()) {
			$json['error'] = 'not_logged';
		} else {
			$customer_id = (int)$this->customer->getId();

			$this->load->library('dockercart/gdpr');
			$gdpr = new DockercartGdpr($this->registry);

			if (!$gdpr->hasPendingRequest($customer_id, 'export')) {
				$gdpr->createRequest($customer_id, 'export', (int)$this->config->get('config_store_id'), (int)$this->config->get('config_language_id'));
				$json['success'] = true;
			} else {
				$json['error'] = 'pending_exists';
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function requestDelete() {
		$json = array('success' => false);

		if (!$this->customer->isLogged()) {
			$json['error'] = 'not_logged';
		} else {
			$customer_id = (int)$this->customer->getId();

			$this->load->library('dockercart/gdpr');
			$gdpr = new DockercartGdpr($this->registry);

			if (!$gdpr->hasPendingRequest($customer_id, 'delete')) {
				$gdpr->createRequest($customer_id, 'delete', (int)$this->config->get('config_store_id'), (int)$this->config->get('config_language_id'));
				$json['success'] = true;
			} else {
				$json['error'] = 'pending_exists';
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function logConsent($type, $value, $customer_id = 0) {
		if (!$customer_id && $this->customer->isLogged()) {
			$customer_id = (int)$this->customer->getId();
		}

		$this->load->library('dockercart/gdpr');
		$gdpr = new DockercartGdpr($this->registry);
		$gdpr->setConsent($customer_id, $type, $value, $this->session->getId());
	}
}
