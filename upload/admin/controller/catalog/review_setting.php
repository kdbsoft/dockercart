<?php
class ControllerCatalogReviewSetting extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('catalog/review_setting');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('catalog/review_criteria');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->updateSetting('config', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('catalog/review_setting', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->getForm();
	}

	public function group() {
		$this->load->language('catalog/review_setting');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/review_criteria');

		$this->getGroupForm();
	}

	public function groupSave() {
		$this->load->language('catalog/review_setting');

		$json = array();

		if (!$this->user->hasPermission('modify', 'catalog/review')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($json['error']) && ($this->request->server['REQUEST_METHOD'] == 'POST')) {
			$this->load->model('catalog/review_criteria');

			$group_id = isset($this->request->get['criteria_group_id']) ? (int)$this->request->get['criteria_group_id'] : 0;

			if ($group_id) {
				$this->model_catalog_review_criteria->editGroup($group_id, $this->request->post);
			} else {
				$group_id = $this->model_catalog_review_criteria->addGroup($this->request->post);
			}

			if (isset($this->request->post['is_default']) && $this->request->post['is_default']) {
				$this->model_catalog_review_criteria->setDefaultGroup($group_id);
			}

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function groupDelete() {
		$this->load->language('catalog/review_setting');

		$json = array();

		if (!$this->user->hasPermission('modify', 'catalog/review')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($json['error']) && isset($this->request->get['criteria_group_id'])) {
			$this->load->model('catalog/review_criteria');

			$this->model_catalog_review_criteria->deleteGroup((int)$this->request->get['criteria_group_id']);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function groupDefault() {
		$this->load->language('catalog/review_setting');

		$json = array();

		if (!$this->user->hasPermission('modify', 'catalog/review')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($json['error']) && isset($this->request->get['criteria_group_id'])) {
			$this->load->model('catalog/review_criteria');

			$this->model_catalog_review_criteria->setDefaultGroup((int)$this->request->get['criteria_group_id']);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	protected function getForm() {
		$data['text_form'] = $this->language->get('heading_title');

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

		$url = '';

		$data['action'] = $this->url->link('catalog/review_setting', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['cancel'] = $this->url->link('catalog/review', 'user_token=' . $this->session->data['user_token'], true);

		$keys = array(
			'config_review_images_enabled',
			'config_review_max_images',
			'config_review_video_enabled',
			'config_review_image_max_size',
			'config_review_video_max_size',
			'config_review_image_dimension',
			'config_review_auto_approve',
			'config_review_verify_purchase',
			'config_review_show_distribution',
			'config_review_per_page',
			'config_review_rate_limit_count',
			'config_review_rate_limit_minutes',
			'config_review_honeypot',
		);

		foreach ($keys as $key) {
			if (isset($this->request->post[$key])) {
				$data[$key] = $this->request->post[$key];
			} else {
				$data[$key] = $this->config->get($key);
			}
		}

		$data['review_max_images'] = (int)$data['config_review_max_images'];

		// Criteria groups
		$data['groups'] = array();

		foreach ($this->model_catalog_review_criteria->getGroups() as $group) {
			$data['groups'][] = array(
				'criteria_group_id' => $group['criteria_group_id'],
				'name'              => $group['name'],
				'is_default'        => $group['is_default'],
				'criteria_count'    => $group['criteria_count'],
				'edit'              => $this->url->link('catalog/review_setting/group', 'user_token=' . $this->session->data['user_token'] . '&criteria_group_id=' . $group['criteria_group_id'], true),
				'add'               => $this->url->link('catalog/review_setting/group', 'user_token=' . $this->session->data['user_token'], true),
			);
		}

		$data['user_token'] = $this->session->data['user_token'];

		$data['add_group'] = $this->url->link('catalog/review_setting/group', 'user_token=' . $this->session->data['user_token'], true);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/review_setting', $data));
	}

	protected function getGroupForm() {
		$data['text_form'] = !isset($this->request->get['criteria_group_id']) ? $this->language->get('text_add_group') : $this->language->get('text_edit_group');

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

		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();

		if (isset($this->request->get['criteria_group_id'])) {
			$group_id = (int)$this->request->get['criteria_group_id'];
		} else {
			$group_id = 0;
		}

		$this->load->model('catalog/review_criteria');

		if (isset($this->request->get['criteria_group_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$group_info = $this->model_catalog_review_criteria->getGroup($group_id);
		} else {
			$group_info = array();
		}

		$data['criteria_group_id'] = $group_id;

		// Group name per language
		$data['group_names'] = array();

		foreach ($data['languages'] as $language) {
			$language_id = (int)$language['language_id'];

			if (isset($this->request->post['group_name'][$language_id])) {
				$data['group_names'][$language_id] = $this->request->post['group_name'][$language_id];
			} elseif (isset($group_info['names'][$language_id])) {
				$data['group_names'][$language_id] = $group_info['names'][$language_id];
			} else {
				$data['group_names'][$language_id] = '';
			}
		}

		// Criteria rows
		$data['criteria_rows'] = array();

		$posted_criteria = array();

		if (isset($this->request->post['criteria_name']) && is_array($this->request->post['criteria_name'])) {
			foreach ($this->request->post['criteria_name'] as $index => $names) {
				$posted_criteria[] = array(
					'criteria_id' => isset($this->request->post['criteria_id'][$index]) ? (int)$this->request->post['criteria_id'][$index] : 0,
					'type'        => isset($this->request->post['criteria_type'][$index]) ? $this->request->post['criteria_type'][$index] : 'text',
					'is_required' => isset($this->request->post['criteria_required'][$index]) ? 1 : 0,
					'names'       => $names,
					'help'        => isset($this->request->post['criteria_help'][$index]) ? $this->request->post['criteria_help'][$index] : array(),
				);
			}
		} elseif (isset($group_info['criteria']) && is_array($group_info['criteria'])) {
			foreach ($group_info['criteria'] as $item) {
				$posted_criteria[] = array(
					'criteria_id' => (int)$item['criteria_id'],
					'type'        => $item['type'],
					'is_required' => (int)$item['is_required'],
					'names'       => $item['names'],
					'help'        => isset($item['help']) ? $item['help'] : array(),
				);
			}
		}

		foreach ($posted_criteria as $row) {
			$names = array();

			foreach ($data['languages'] as $language) {
				$language_id = (int)$language['language_id'];

				$name = '';

				if (isset($row['names'][$language_id]['name'])) {
					$name = $row['names'][$language_id]['name'];
				} elseif (isset($row['names'][$language_id])) {
					$name = is_array($row['names'][$language_id]) ? '' : $row['names'][$language_id];
				}

				$help = '';

				if (isset($row['names'][$language_id]['help'])) {
					$help = $row['names'][$language_id]['help'];
				} elseif (isset($row['help'][$language_id])) {
					$help = is_array($row['help'][$language_id]) ? '' : $row['help'][$language_id];
				}

				$names[$language_id] = array(
					'name' => $name,
					'help' => $help,
				);
			}

			$data['criteria_rows'][] = array(
				'criteria_id' => $row['criteria_id'],
				'type'        => $row['type'],
				'is_required' => $row['is_required'],
				'names'       => $names,
			);
		}

		if (isset($this->request->post['is_default'])) {
			$data['is_default'] = $this->request->post['is_default'];
		} elseif (isset($group_info['is_default'])) {
			$data['is_default'] = $group_info['is_default'];
		} else {
			$data['is_default'] = 0;
		}

		$data['action'] = $this->url->link('catalog/review_setting/groupSave', 'user_token=' . $this->session->data['user_token'] . ($group_id ? '&criteria_group_id=' . $group_id : ''), true);
		$data['cancel'] = $this->url->link('catalog/review_setting', 'user_token=' . $this->session->data['user_token'], true);

		$data['user_token'] = $this->session->data['user_token'];

		$data['admin_language_id'] = (int)$this->config->get('config_language_id');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/review_setting_group', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'catalog/review')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}
