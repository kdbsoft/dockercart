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

		// Dashboard Extensions
		$dashboards = array();

		$this->load->model('setting/extension');

		// Get a list of installed modules
		$extensions = $this->model_setting_extension->getInstalled('dashboard');

		// Add all the modules which have multiple settings for each module
		foreach ($extensions as $code) {
			if ($this->config->get('dashboard_' . $code . '_status') && $this->user->hasPermission('access', 'extension/dashboard/' . $code)) {
				$output = $this->load->controller('extension/dashboard/' . $code . '/dashboard');

				if ($output) {
					$dashboards[] = array(
						'code'       => $code,
						'width'      => $this->config->get('dashboard_' . $code . '_width'),
						'sort_order' => $this->config->get('dashboard_' . $code . '_sort_order'),
						'output'     => $output
					);
				}
			}
		}

		$sort_order = array();

		foreach ($dashboards as $key => $value) {
			$sort_order[$key] = $value['sort_order'];
		}

		array_multisort($sort_order, SORT_ASC, $dashboards);

		$data['rows'] = $this->buildDashboardRows($dashboards);

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
