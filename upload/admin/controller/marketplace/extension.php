<?php
class ControllerMarketplaceExtension extends Controller {
	private $error = array();

	// Icon map per extension type
	private $type_icons = array(
		'module'    => 'fa-puzzle-piece',
		'payment'   => 'fa-credit-card',
		'shipping'  => 'fa-truck',
		'total'     => 'fa-calculator',
		'dashboard' => 'fa-tachometer',
		'analytics' => 'fa-line-chart',
		'report'    => 'fa-bar-chart',
		'feed'      => 'fa-rss',
		'theme'     => 'fa-paint-brush',
		'captcha'   => 'fa-shield',
		'advertise' => 'fa-bullhorn',
		'fraud'     => 'fa-ban',
		'menu'      => 'fa-bars',
		'currency'  => 'fa-money',
	);

	public function index() {
		$this->load->language('marketplace/extension');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['user_token'] = $this->session->data['user_token'];
		$data['filter_type'] = isset($this->request->get['type']) ? (string)$this->request->get['type'] : '';

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

		$this->load->model('setting/extension');
		$this->load->model('setting/module');
		$this->load->model('setting/store');
		$this->load->model('setting/setting');

		$data['groups']       = array();
		$data['type_filters'] = array();

		$type_files = glob(DIR_APPLICATION . 'controller/extension/extension/*.php', GLOB_BRACE);

		if ($type_files) {
			foreach ($type_files as $type_file) {
				$type = basename($type_file, '.php');

				if ($type === 'promotion') {
					continue;
				}

				if (!$this->user->hasPermission('access', 'extension/extension/' . $type)) {
					continue;
				}

				$this->load->language('extension/extension/' . $type, 'type_lang');
				$type_label = $this->language->get('type_lang')->get('heading_title');
				$type_icon  = isset($this->type_icons[$type]) ? $this->type_icons[$type] : 'fa-plug';

				$installed_list = $this->model_setting_extension->getInstalled($type);

				// Clean up orphaned installs
				foreach ($installed_list as $key => $value) {
					if (!is_file(DIR_APPLICATION . 'controller/extension/' . $type . '/' . $value . '.php')
						&& !is_file(DIR_APPLICATION . 'controller/' . $type . '/' . $value . '.php')
					) {
						$this->model_setting_extension->uninstall($type, $value);
						unset($installed_list[$key]);
					}
				}

				$ext_files = glob(DIR_APPLICATION . 'controller/extension/' . $type . '/*.php');

				if (!$ext_files) {
					continue;
				}

				$extensions = array();

				foreach ($ext_files as $ext_file) {
					$code = basename($ext_file, '.php');

					$this->load->language('extension/' . $type . '/' . $code, 'ext_lang');
					$name = $this->language->get('ext_lang')->get('heading_title');

					$installed = in_array($code, $installed_list);

					$status_raw  = $this->resolveStatus($type, $code, $installed);
					$status_text = $status_raw
						? $this->language->get('text_enabled')
						: $this->language->get('text_disabled');

					// Resolve primary edit URL
					if (in_array($type, array('analytics', 'theme'))) {
						$edit_url = $this->url->link('extension/' . $type . '/' . $code, 'user_token=' . $this->session->data['user_token'] . '&store_id=0', true);
					} else {
						$edit_url = $this->url->link('extension/' . $type . '/' . $code, 'user_token=' . $this->session->data['user_token'], true);
					}

					// Module instance count
					$instance_count = 0;
					if ($type === 'module' && $installed) {
						$instance_count = count($this->model_setting_module->getModulesByCode($code));
					}

					// Optional sort_order
					$sort_order = '';
					if (in_array($type, array('payment', 'shipping', 'total', 'report', 'dashboard', 'menu'))) {
						$sort_order = (string)$this->config->get($type . '_' . $code . '_sort_order');
					}

					// Payment external link
					$ext_link = '';
					if ($type === 'payment') {
						$text_link = $this->language->get('ext_lang')->get('text_' . $code);
						if ($text_link !== 'text_' . $code) {
							$ext_link = $text_link;
						}
					}

					$extensions[] = array(
						'code'           => $code,
						'name'           => $name,
						'installed'      => $installed,
						'status_raw'     => $status_raw,
						'status'         => $status_text,
						'edit'           => $edit_url,
						'install_url'    => $this->url->link('marketplace/extension/install', 'user_token=' . $this->session->data['user_token'] . '&type=' . $type . '&extension=' . $code, true),
						'uninstall_url'  => $this->url->link('marketplace/extension/uninstall', 'user_token=' . $this->session->data['user_token'] . '&type=' . $type . '&extension=' . $code, true),
						'instance_count' => $instance_count,
						'sort_order'     => $sort_order,
						'ext_link'       => $ext_link,
					);
				}

				// Sort by name
				usort($extensions, function($a, $b) {
					return strcmp($a['name'], $b['name']);
				});

				$data['groups'][] = array(
					'type'       => $type,
					'label'      => $type_label,
					'icon'       => $type_icon,
					'extensions' => $extensions,
				);

				$data['type_filters'][] = array(
					'type'  => $type,
					'label' => $type_label,
					'icon'  => $type_icon,
					'count' => count($extensions),
				);
			}
		}

		$data['text_enabled']       = $this->language->get('text_enabled');
		$data['text_disabled']      = $this->language->get('text_disabled');
		$data['text_all']           = $this->language->get('text_all');
		$data['text_installed']     = $this->language->get('text_installed');
		$data['text_not_installed'] = $this->language->get('text_not_installed');
		$data['text_search']        = $this->language->get('text_search');
		$data['text_no_results']    = $this->language->get('text_no_results');
		$data['text_instances']     = $this->language->get('text_instances');
		$data['text_confirm']       = $this->language->get('text_confirm');

		$data['button_install']   = $this->language->get('button_install');
		$data['button_uninstall'] = $this->language->get('button_uninstall');
		$data['button_edit']      = $this->language->get('button_edit');
		$data['button_add']       = $this->language->get('button_add');

		$data['header']      = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer']      = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('marketplace/extension', $data));
	}

	public function install() {
		$this->load->language('marketplace/extension');

		$json = array();

		$type      = isset($this->request->get['type'])      ? (string)$this->request->get['type']      : '';
		$extension = isset($this->request->get['extension']) ? (string)$this->request->get['extension'] : '';

		$valid_types = $this->getValidTypes();

		if (!in_array($type, $valid_types)) {
			$json['error'] = $this->language->get('error_permission');
		} elseif (!$this->user->hasPermission('modify', 'extension/extension/' . $type)) {
			$json['error'] = $this->language->get('error_permission');
		} elseif (empty($extension) || strlen($extension) < 1 || strlen($extension) > 64
			|| !preg_match('/^[a-zA-Z0-9_]+$/', $extension)
		) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$this->load->model('setting/extension');

			$this->model_setting_extension->install($type, $extension);

			$this->load->model('user/user_group');
			$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/' . $type . '/' . $extension);
			$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/' . $type . '/' . $extension);

			// Call the extension's own install method if it exists
			$this->load->controller('extension/' . $type . '/' . $extension . '/install');

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function uninstall() {
		$this->load->language('marketplace/extension');

		$json = array();

		$type      = isset($this->request->get['type'])      ? (string)$this->request->get['type']      : '';
		$extension = isset($this->request->get['extension']) ? (string)$this->request->get['extension'] : '';

		$valid_types = $this->getValidTypes();

		if (!in_array($type, $valid_types)) {
			$json['error'] = $this->language->get('error_permission');
		} elseif (!$this->user->hasPermission('modify', 'extension/extension/' . $type)) {
			$json['error'] = $this->language->get('error_permission');
		} elseif (empty($extension) || strlen($extension) < 1 || strlen($extension) > 64
			|| !preg_match('/^[a-zA-Z0-9_]+$/', $extension)
		) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$this->load->model('setting/extension');

			$this->model_setting_extension->uninstall($type, $extension);

			// Module-specific: remove all module instances
			if ($type === 'module') {
				$this->load->model('setting/module');
				$this->model_setting_module->deleteModulesByCode($extension);
			}

			// Call the extension's own uninstall method if it exists
			$this->load->controller('extension/' . $type . '/' . $extension . '/uninstall');

			$this->load->model('user/user_group');
			$this->model_user_user_group->removePermissions('extension/' . $type . '/' . $extension);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function refreshMenu() {
		$output = $this->load->controller('common/column_left');
		$this->response->setOutput($output);
	}

	private function getValidTypes() {
		$files = glob(DIR_APPLICATION . 'controller/extension/extension/*.php');
		if (!$files) {
			return array();
		}
		$types = array();
		foreach ($files as $file) {
			$t = basename($file, '.php');
			if ($t !== 'promotion') {
				$types[] = $t;
			}
		}
		return $types;
	}

	private function resolveStatus($type, $code, $installed) {
		if (!$installed) {
			return false;
		}
		if ($type === 'feed') {
			$status = $this->config->get('feed_' . $code . '_status');
			if ($status === null) {
				$status = $this->config->get('module_' . $code . '_status');
			}
			if ($status === null) {
				$status = $this->config->get($code . '_status');
			}
			return (bool)$status;
		}
		return (bool)$this->config->get($type . '_' . $code . '_status');
	}
}
