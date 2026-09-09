<?php
declare(strict_types=1);

class ControllerReportReport extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('report/report');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['user_token'] = $this->session->data['user_token'];

		if (isset($this->request->get['code'])) {
			$data['code'] = $this->request->get['code'];
		} else {
			$data['code'] = '';
		}

		$this->load->model('setting/extension');

		// Clean up orphaned installs (controller file removed)
		$installed_list = $this->model_setting_extension->getInstalled('report');

		foreach ($installed_list as $key => $value) {
			if (!is_file(DIR_APPLICATION . 'controller/extension/report/' . $value . '.php')) {
				$this->model_setting_extension->uninstall('report', $value);
				unset($installed_list[$key]);
			}
		}

		$installed_list = array_values($installed_list);

		$report_icons = array(
			'dockercart_analytics' => 'chart-no-axes-column',
			'supplier_profit'      => 'trending-up',
			'sale_order'           => 'shopping-cart',
			'sale_tax'             => 'percent',
			'sale_shipping'        => 'truck',
			'sale_coupon'          => 'ticket-percent',
			'sale_return'          => 'rotate-ccw',
			'product_purchased'    => 'package',
			'product_viewed'       => 'eye',
			'marketing'            => 'megaphone',
			'customer_order'       => 'users',
			'customer_reward'      => 'award',
			'customer_search'      => 'search',
			'customer_transaction' => 'credit-card',
			'customer_activity'    => 'activity'
		);

		// All reports are discovered from the controller directory; the
		// extension type page is gone — install/uninstall/status/sort_order
		// are managed on this page (see the editor in report.twig).
		$files = glob(DIR_APPLICATION . 'controller/extension/report/*.php');

		$all_reports = array();

		if ($files) {
			foreach ($files as $file) {
				$code = basename($file, '.php');

				$this->load->language('extension/report/' . $code, 'extension');

				$installed = in_array($code, $installed_list);
				$status = (bool)$this->config->get('report_' . $code . '_status');

				$all_reports[] = array(
					'code'       => $code,
					'text'       => $this->language->get('extension')->get('heading_title'),
					'icon'       => isset($report_icons[$code]) ? $report_icons[$code] : 'bar-chart-3',
					'installed'  => $installed,
					'status'     => $status,
					'sort_order' => (int)$this->config->get('report_' . $code . '_sort_order'),
					'href'       => $this->url->link('report/report', 'user_token=' . $this->session->data['user_token'] . '&code=' . $code, true),
					'install'    => $this->url->link('report/report/install', 'user_token=' . $this->session->data['user_token'] . '&code=' . $code, true),
					'uninstall'  => $this->url->link('report/report/uninstall', 'user_token=' . $this->session->data['user_token'] . '&code=' . $code, true),
				);
			}
		}

		// Pills: installed + enabled reports only
		$data['reports'] = array();

		foreach ($all_reports as $report) {
			if ($report['installed'] && $report['status'] && $this->user->hasPermission('access', 'extension/report/' . $report['code'])) {
				$data['reports'][] = array(
					'text'       => $report['text'],
					'code'       => $report['code'],
					'icon'       => $report['icon'],
					'sort_order' => $report['sort_order'],
					'href'       => $report['href']
				);
			}
		}

		$sort_order = array();

		foreach ($data['reports'] as $key => $value) {
			$sort_order[$key] = $value['sort_order'];
		}

		array_multisort($sort_order, SORT_ASC, $data['reports']);

		// Manage editor: every discovered report, ordered by sort_order
		$data['manage'] = $all_reports;

		usort($data['manage'], function ($a, $b) {
			return $a['sort_order'] <=> $b['sort_order'];
		});

		$data['can_edit'] = $this->user->hasPermission('modify', 'report/report');

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if (isset($this->session->data['error'])) {
			$data['error_warning'] = $this->session->data['error'];
			unset($this->session->data['error']);
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->request->get['code']) && $this->request->get['code'] !== '') {
			$data['report'] = $this->load->controller('extension/report/' . $this->request->get['code'] . '/report');
		} elseif (isset($data['reports'][0])) {
			$data['report'] = $this->load->controller('extension/report/' . $data['reports'][0]['code'] . '/report');
		} else {
			$data['report'] = '';
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('report/report', $data));
	}

	public function install() {
		$this->load->language('report/report');

		$code = isset($this->request->get['code']) ? (string)$this->request->get['code'] : '';

		if (!$this->user->hasPermission('modify', 'report/report')) {
			$this->session->data['error'] = $this->language->get('error_permission');
		} elseif (!$this->isValidReportCode($code)) {
			$this->session->data['error'] = $this->language->get('error_code');
		} else {
			$this->load->model('setting/extension');

			$this->model_setting_extension->install('report', $code);

			$this->load->model('user/user_group');
			$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/report/' . $code);
			$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/report/' . $code);

			$this->load->controller('extension/report/' . $code . '/install');

			// Default settings so the report appears enabled immediately
			if ($this->config->get('report_' . $code . '_status') === null) {
				$this->load->model('setting/setting');

				$this->model_setting_setting->editSetting('report_' . $code, array(
					'report_' . $code . '_status'     => 1,
					'report_' . $code . '_sort_order' => $this->getNextSortOrder(),
				));
			}

			$this->session->data['success'] = $this->language->get('text_success_install');
		}

		$this->redirectBack($code);
	}

	public function uninstall() {
		$this->load->language('report/report');

		$code = isset($this->request->get['code']) ? (string)$this->request->get['code'] : '';

		if (!$this->user->hasPermission('modify', 'report/report')) {
			$this->session->data['error'] = $this->language->get('error_permission');
		} elseif (!$this->isValidReportCode($code)) {
			$this->session->data['error'] = $this->language->get('error_code');
		} else {
			$this->load->model('setting/extension');

			$this->model_setting_extension->uninstall('report', $code);

			$this->load->controller('extension/report/' . $code . '/uninstall');

			$this->load->model('user/user_group');
			$this->model_user_user_group->removePermissions('extension/report/' . $code);

			$this->session->data['success'] = $this->language->get('text_success_uninstall');
		}

		$this->redirectBack($code);
	}

	public function saveLayout() {
		$this->load->language('report/report');

		$json = array();

		if (!$this->user->hasPermission('modify', 'report/report')) {
			$json['error'] = $this->language->get('error_permission');
		} elseif ($this->request->server['REQUEST_METHOD'] != 'POST' || empty($this->request->post['reports']) || !is_array($this->request->post['reports'])) {
			$json['error'] = $this->language->get('error_method');
		} else {
			$this->load->model('setting/setting');

			foreach ($this->request->post['reports'] as $code => $row) {
				if (!is_array($row) || !$this->isValidReportCode((string)$code)) {
					continue;
				}

				$status = !empty($row['status']);
				$sort_order = isset($row['sort_order']) ? (int)$row['sort_order'] : 0;

				$this->model_setting_setting->editSetting('report_' . $code, array(
					'report_' . $code . '_status'     => $status ? 1 : 0,
					'report_' . $code . '_sort_order' => $sort_order,
				));
			}

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function redirectBack(string $code) {
		$url = 'user_token=' . $this->session->data['user_token'];

		if ($code !== '') {
			$url .= '&code=' . $code;
		}

		$this->response->redirect($this->url->link('report/report', $url, true));
	}

	private function isValidReportCode(string $code): bool {
		if ($code === '' || strlen($code) > 64 || !preg_match('/^[a-zA-Z0-9_]+$/', $code)) {
			return false;
		}

		return is_file(DIR_APPLICATION . 'controller/extension/report/' . $code . '.php');
	}

	private function getNextSortOrder(): int {
		$query = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `key` LIKE 'report\\_%\\_sort_order'");

		$max = 0;

		foreach ($query->rows as $row) {
			$max = max($max, (int)$row['value']);
		}

		return $max + 1;
	}
}
