<?php
declare(strict_types=1);

class ControllerExtensionCaptchaDockercart extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/captcha/dockercart');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$settings = $this->request->post;

			if (!isset($settings['captcha_dockercart_operations'])) {
				$settings['captcha_dockercart_operations'] = array();
			}

			$this->model_setting_setting->editSetting('captcha_dockercart', $settings);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=captcha', true));
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
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=captcha', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/captcha/dockercart', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/captcha/dockercart', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=captcha', true);

		if (isset($this->request->post['captcha_dockercart_status'])) {
			$data['captcha_dockercart_status'] = $this->request->post['captcha_dockercart_status'];
		} else {
			$data['captcha_dockercart_status'] = $this->config->get('captcha_dockercart_status');
		}

		$operations_config = $this->config->get('captcha_dockercart_operations');
		if (isset($this->request->post['captcha_dockercart_operations'])) {
			$data['captcha_dockercart_operations'] = $this->request->post['captcha_dockercart_operations'];
		} elseif (is_array($operations_config)) {
			$data['captcha_dockercart_operations'] = $operations_config;
		} else {
			$data['captcha_dockercart_operations'] = array('addition', 'subtraction', 'multiplication');
		}

		if (isset($this->request->post['captcha_dockercart_max_number'])) {
			$data['captcha_dockercart_max_number'] = $this->request->post['captcha_dockercart_max_number'];
		} else {
			$data['captcha_dockercart_max_number'] = $this->config->get('captcha_dockercart_max_number') ?: 10;
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/captcha/dockercart', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/captcha/dockercart')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	public function install() {
		$defaults = array(
			'captcha_dockercart_status' => 1,
			'captcha_dockercart_operations' => array('addition', 'subtraction', 'multiplication'),
			'captcha_dockercart_max_number' => 10,
		);

		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('captcha_dockercart', $defaults);
	}

	public function uninstall() {
		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('captcha_dockercart');
	}
}
