<?php
class ControllerCommonDashboard extends Controller {
	public function index() {
		$this->load->language('common/dashboard');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['user_token'] = $this->session->data['user_token'];

		// Onboarding checklist
		$this->load->model('catalog/category');
		$this->load->model('catalog/product');
		$this->load->model('catalog/manufacturer');

		$data['onboarding_enabled'] = (bool)$this->config->get('config_onboarding_enabled');

		// The store is considered "configured" once the admin email differs from
		// the value seeded at install time. The install default is recorded by the
		// entrypoint bootstrap in `config_onboarding_email_default`; fall back to the
		// known default for databases bootstrapped before that setting existed.
		$default_email = $this->config->get('config_onboarding_email_default');
		if ($default_email === null || $default_email === '') {
			$default_email = 'admin@dockercart.net';
		}

		$data['onboarding_steps'] = array(
			array(
				'title' => $this->language->get('text_step_settings'),
				'desc'  => $this->language->get('text_step_settings_desc'),
				'href'  => $this->url->link('setting/setting', 'user_token=' . $this->session->data['user_token'], true),
				'done'  => ((string)$this->config->get('config_email') !== (string)$default_email),
				'icon'  => 'settings'
			),
			array(
				'title' => $this->language->get('text_step_category'),
				'desc'  => $this->language->get('text_step_category_desc'),
				'href'  => $this->url->link('catalog/category', 'user_token=' . $this->session->data['user_token'], true),
				'done'  => $this->model_catalog_category->getTotalCategories() > 0,
				'icon'  => 'folder-plus'
			),
			array(
				'title' => $this->language->get('text_step_product'),
				'desc'  => $this->language->get('text_step_product_desc'),
				'href'  => $this->url->link('catalog/product', 'user_token=' . $this->session->data['user_token'], true),
				'done'  => $this->model_catalog_product->getTotalProducts() > 0,
				'icon'  => 'package-plus'
			),
			array(
				'title' => $this->language->get('text_step_manufacturer'),
				'desc'  => $this->language->get('text_step_manufacturer_desc'),
				'href'  => $this->url->link('catalog/manufacturer', 'user_token=' . $this->session->data['user_token'], true),
				'done'  => $this->model_catalog_manufacturer->getTotalManufacturers() > 0,
				'icon'  => 'factory'
			)
		);

		$data['onboarding_dismiss'] = $this->url->link('common/dashboard/dismiss', 'user_token=' . $this->session->data['user_token'], true);

		// Check install directory exists
		if (is_dir(DIR_CATALOG . '../install')) {
			$data['error_install'] = $this->language->get('error_install');
		} else {
			$data['error_install'] = '';
		}

		// Dashboard widgets (auto-discovered, no extension type).
		$widget_codes = $this->discoverWidgetCodes();

		$widgets = array();

		foreach ($widget_codes as $code) {
			$this->load->language('extension/dashboard/' . $code, 'widget_lang');
			$title = $this->language->get('widget_lang')->get('heading_title');

			$status = $this->config->get('dashboard_' . $code . '_status');
			$status = ($status === null) ? 0 : (int)$status;

			$width = (int)$this->config->get('dashboard_' . $code . '_width');

			if ($width < 3 || $width > 12) {
				$width = 6;
			}

			$sort_order = $this->config->get('dashboard_' . $code . '_sort_order');
			$sort_order = ($sort_order === null || $sort_order === '') ? 0 : (int)$sort_order;

			$output = '';

			if ($status) {
				$output = $this->load->controller('extension/dashboard/' . $code . '/dashboard');
			}

			$widgets[] = array(
				'code'       => $code,
				'title'      => ($title !== '' && $title !== 'heading_title') ? $title : $code,
				'status'     => $status ? 1 : 0,
				'width'      => $width,
				'sort_order' => $sort_order,
				'output'     => $output
			);
		}

		usort($widgets, function($a, $b) {
			return $a['sort_order'] <=> $b['sort_order'];
		});

		$dashboards = array();

		foreach ($widgets as $widget) {
			if ($widget['status'] && $widget['output']) {
				$dashboards[] = array(
					'code'       => $widget['code'],
					'width'      => $widget['width'],
					'sort_order' => $widget['sort_order'],
					'output'     => $widget['output']
				);
			}
		}

		$data['rows'] = $this->buildDashboardRows($dashboards);

		$data['widgets'] = $widgets;
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		// Note: common/dashboard itself carries no grantable permission (it is excluded
		// from the permission matrix), so edit mode is gated by the global store
		// settings permission instead.
		$data['can_edit'] = $this->user->hasPermission('modify', 'setting/setting');

		if (DIR_STORAGE == DIR_SYSTEM . 'storage/') {
			$data['security'] = $this->load->controller('common/security');
		} else {
			$data['security'] = '';
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('common/dashboard', $data));
	}

	public function saveLayout() {
		$this->load->language('common/dashboard');

		$json = array();

		if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
			$json['error'] = $this->language->get('error_invalid_request');
		} elseif (!$this->user->hasPermission('modify', 'setting/setting')) {
			$json['error'] = $this->language->get('error_permission');
		} elseif (!isset($this->request->post['layout']) || !is_array($this->request->post['layout'])) {
			$json['error'] = $this->language->get('error_invalid_layout');
		} else {
			$allowed = $this->discoverWidgetCodes();

			$this->load->model('setting/setting');

			foreach ($this->request->post['layout'] as $code => $item) {
				if (!in_array($code, $allowed, true) || !is_array($item)) {
					continue;
				}

				$status = isset($item['status']) ? (int)$item['status'] : 0;
				$width = isset($item['width']) ? (int)$item['width'] : 6;
				$sort_order = isset($item['sort_order']) ? (int)$item['sort_order'] : 0;

				$status = $status ? 1 : 0;
				$width = max(3, min(12, $width));

				// updateSetting upserts only the given keys, preserving dashboard_<code>_stack.
				$this->model_setting_setting->updateSetting('dashboard_' . $code, array(
					'dashboard_' . $code . '_status'     => $status,
					'dashboard_' . $code . '_width'      => $width,
					'dashboard_' . $code . '_sort_order' => $sort_order
				));
			}

			$json['success'] = $this->language->get('text_layout_saved');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Auto-discovered widget codes (no extension type).
	 *
	 * @return array
	 */
	protected function discoverWidgetCodes() {
		$codes = array();

		foreach (glob(DIR_APPLICATION . 'controller/extension/dashboard/*.php') as $file) {
			$codes[] = basename($file, '.php');
		}

		sort($codes);

		return $codes;
	}

	public function dismiss() {
		$this->load->model('setting/setting');

		$this->model_setting_setting->editSettingValue('config', 'config_onboarding_enabled', '0');

		$this->response->redirect($this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true));
	}

	public function currency() {
		if ($this->config->get('config_currency_auto')) {
			$config_currency_engine = $this->config->get('config_currency_engine');
			if ($config_currency_engine) {
				$this->load->controller('extension/currency/'.$config_currency_engine.'/currency');
			}
		}
	}

	/**
	 * Group sorted dashboard widgets into rows of columns. Each column holds one
	 * or more widgets - a widget can opt into being stacked vertically inside the
	 * column of another widget via the "dashboard_<code>_stack" setting (value =
	 * target widget code). Column width is driven by the host widget's width and
	 * rows close once their accumulated width reaches 12. The "md_full" flag makes
	 * narrow columns go full-width on medium/small screens when the row contains a
	 * widget wider than 3 columns.
	 *
	 * @param array $dashboards
	 * @return array
	 */
	protected function buildDashboardRows($dashboards) {
		$stack_targets = array();

		foreach ($dashboards as $dashboard) {
			$stack_target = $this->config->get('dashboard_' . $dashboard['code'] . '_stack');

			if ($stack_target !== null && $stack_target !== '') {
				$stack_targets[$dashboard['code']] = $stack_target;
			}
		}

		$data['rows'] = array();
		$row_columns = array();
		$row_width = 0;
		$stacked = array();

		$flush_row = function () use (&$data, &$row_columns, &$row_width) {
			if (!$row_columns) {
				return;
			}

			$md_full = false;

			foreach ($row_columns as $column) {
				foreach ($column['widgets'] as $widget) {
					if ($widget['width'] > 3) {
						$md_full = true;
						break 2;
					}
				}
			}

			$data['rows'][] = array(
				'columns' => $row_columns,
				'md_full' => $md_full
			);

			$row_columns = array();
			$row_width = 0;
		};

		foreach ($dashboards as $dashboard) {
			$code = $dashboard['code'];

			if (isset($stacked[$code])) {
				continue;
			}

			// Widgets that stack into another column are appended when that column is built.
			if (isset($stack_targets[$code])) {
				$stacked[$code] = true;
				continue;
			}

			$widgets = array();

			foreach ($dashboards as $candidate) {
				$target = isset($stack_targets[$candidate['code']]) ? $stack_targets[$candidate['code']] : null;

				if ($candidate['code'] === $code) {
					$widgets[] = $candidate;
				} elseif ($target === $code) {
					// Stack this widget inside the host widget's column, top to bottom
					// by sort order.
					$widgets[] = $candidate;
					$stacked[$candidate['code']] = true;
				}
			}

			usort($widgets, function ($a, $b) {
				return $a['sort_order'] <=> $b['sort_order'];
			});

			$row_columns[] = array(
				'width'   => $dashboard['width'],
				'widgets' => $widgets
			);

			$row_width += $dashboard['width'];

			if ($row_width >= 12) {
				$flush_row();
			}
		}

		$flush_row();

		return $data['rows'];
	}
}
