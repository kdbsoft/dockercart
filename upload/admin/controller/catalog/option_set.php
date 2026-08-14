<?php
class ControllerCatalogOptionSet extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('catalog/option_set');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/option_set');

		$this->getList();
	}

	public function add() {
		$this->load->language('catalog/option_set');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/option_set');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_catalog_option_set->addOptionSet($this->request->post);

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

			$this->response->redirect($this->url->link('catalog/option_set', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function edit() {
		$this->load->language('catalog/option_set');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/option_set');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_catalog_option_set->editOptionSet($this->request->get['option_set_id'], $this->request->post);

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

			$this->response->redirect($this->url->link('catalog/option_set', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function copy() {
		$this->load->language('catalog/option_set');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/option_set');

		$option_set_ids = [];

		if (isset($this->request->post['selected'])) {
			$option_set_ids = $this->request->post['selected'];
		} elseif (isset($this->request->get['option_set_id'])) {
			$option_set_ids = [
				(int) $this->request->get['option_set_id'],
			];
		}

		if ($option_set_ids && $this->validateCopy()) {
			foreach ($option_set_ids as $option_set_id) {
				$this->model_catalog_option_set->copyOptionSet(
					(int) $option_set_id,
				);
			}

			$this->session->data['success'] = $this->language->get(
				'text_success',
			);

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

			$this->response->redirect($this->url->link('catalog/option_set', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	public function delete() {
		$this->load->language('catalog/option_set');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/option_set');

		$option_set_ids = [];

		if (isset($this->request->post['selected'])) {
			$option_set_ids = $this->request->post['selected'];
		} elseif (isset($this->request->get['option_set_id'])) {
			$option_set_ids = [
				(int) $this->request->get['option_set_id'],
			];
		}

		if ($option_set_ids && $this->validateDelete()) {
			foreach ($option_set_ids as $option_set_id) {
				$this->model_catalog_option_set->deleteOptionSet(
					(int) $option_set_id,
				);
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

			$this->response->redirect($this->url->link('catalog/option_set', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	protected function getList() {
		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'ostd.name';
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

		$data['add'] = $this->url->link('catalog/option_set/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['copy'] = $this->url->link('catalog/option_set/copy', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['delete'] = $this->url->link('catalog/option_set/delete', 'user_token=' . $this->session->data['user_token'] . $url, true);

		$data['option_sets'] = array();

		$filter_data = array(
			'sort'  => $sort,
			'order' => $order,
			'start' => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' => $this->config->get('config_limit_admin')
		);

		$option_set_total = $this->model_catalog_option_set->getTotalOptionSets();

		$results = $this->model_catalog_option_set->getOptionSets($filter_data);

		foreach ($results as $result) {
			$data['option_sets'][] = array(
				'option_set_id'  => $result['option_set_id'],
				'name'           => $result['name'],
				'name_raw'       => $result['name'],
				'option_count'   => $result['option_count'],
				'sort_order'     => $result['sort_order'],
				'sort_order_raw' => $result['sort_order'],
				'status'         => $result['status'],
				'status_raw'     => $result['status'],
				'edit'           => $this->url->link('catalog/option_set/edit', 'user_token=' . $this->session->data['user_token'] . '&option_set_id=' . $result['option_set_id'] . $url, true),
				'copy'           => $this->url->link('catalog/option_set/copy', 'user_token=' . $this->session->data['user_token'] . '&option_set_id=' . $result['option_set_id'] . $url, true),
				'delete'         => $this->url->link('catalog/option_set/delete', 'user_token=' . $this->session->data['user_token'] . '&option_set_id=' . $result['option_set_id'] . $url, true)
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

		$data['sort_name'] = $this->url->link('catalog/option_set', 'user_token=' . $this->session->data['user_token'] . '&sort=ostd.name' . $url, true);
		$data['sort_sort_order'] = $this->url->link('catalog/option_set', 'user_token=' . $this->session->data['user_token'] . '&sort=ost.sort_order' . $url, true);

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		$pagination = new Pagination();
		$pagination->total = $option_set_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('catalog/option_set', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = $pagination->renderResults($this->language->get('text_pagination'));

		$data['sort'] = $sort;
		$data['order'] = $order;

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/option_set_list', $data));
	}

	protected function getForm() {
		$data['text_form'] = !isset($this->request->get['option_set_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');
		$data['text_form_subtitle'] = !isset($this->request->get['option_set_id'])
		    ? $this->language->get('text_add_option_set_subtitle')
		    : $this->language->get('text_edit_option_set_subtitle');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['name'])) {
			$data['error_name'] = $this->error['name'];
		} else {
			$data['error_name'] = array();
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

		if (!isset($this->request->get['option_set_id'])) {
			$data['action'] = $this->url->link('catalog/option_set/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		} else {
			$data['action'] = $this->url->link('catalog/option_set/edit', 'user_token=' . $this->session->data['user_token'] . '&option_set_id=' . $this->request->get['option_set_id'] . $url, true);
		}

		$data['cancel'] = $this->url->link('catalog/option_set', 'user_token=' . $this->session->data['user_token'] . $url, true);

		if (isset($this->request->get['option_set_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$option_set_info = $this->model_catalog_option_set->getOptionSet($this->request->get['option_set_id']);
		}

		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();

		if (isset($this->request->post['option_set_description'])) {
			$data['option_set_description'] = $this->request->post['option_set_description'];
		} elseif (isset($this->request->get['option_set_id'])) {
			$data['option_set_description'] = $this->model_catalog_option_set->getOptionSetDescriptions($this->request->get['option_set_id']);
		} else {
			$data['option_set_description'] = array();
		}

		$data['option_set_description'] = $this->decodeDescriptionFields($data['option_set_description'], array('name'));

		$data['entity_name'] = '';
		$lang_id = (int)$this->config->get('config_language_id');
		if (!empty($data['option_set_description'][$lang_id]['name'])) {
			$data['entity_name'] = $data['option_set_description'][$lang_id]['name'];
		}

		if (isset($this->request->post['sort_order'])) {
			$data['sort_order'] = $this->request->post['sort_order'];
		} elseif (!empty($option_set_info)) {
			$data['sort_order'] = $option_set_info['sort_order'];
		} else {
			$data['sort_order'] = '';
		}

		if (isset($this->request->post['status'])) {
			$data['status'] = $this->request->post['status'];
		} elseif (!empty($option_set_info)) {
			$data['status'] = $option_set_info['status'];
		} else {
			$data['status'] = true;
		}

		// Options in the set
		$data['option_set_options'] = array();

		if (isset($this->request->post['option_set_option'])) {
			$set_options = $this->request->post['option_set_option'];
		} elseif (isset($this->request->get['option_set_id'])) {
			$set_options = $this->model_catalog_option_set->getOptionSetOptions($this->request->get['option_set_id']);
		} else {
			$set_options = array();
		}

		foreach ($set_options as $set_option) {
			if (empty($set_option['option_id'])) {
				continue;
			}

			$name = '';
			$type = '';

			if (isset($set_option['name'])) {
				$name = $this->decodeHtmlEntitiesForDisplay($set_option['name']);
			} else {
				$this->load->model('catalog/option');
				$option_info = $this->model_catalog_option->getOption((int)$set_option['option_id']);

				if ($option_info) {
					$name = $this->decodeHtmlEntitiesForDisplay($option_info['name']);
				}
			}

			if (isset($set_option['type'])) {
				$type = $set_option['type'];
			} elseif (isset($option_info) && !empty($option_info['type'])) {
				$type = $option_info['type'];
			}

			$data['option_set_options'][] = array(
				'option_id' => (int)$set_option['option_id'],
				'name'      => $name,
				'type'      => $type
			);
		}

		$data['text_status_card'] = $this->language->get('text_status_card');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_active'] = $this->language->get('text_active');
		$data['text_inactive'] = $this->language->get('text_inactive');

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/option_set_form', $data));
	}

	private function decodeDescriptionFields($descriptions, $fields = array()) {
		if (!is_array($descriptions)) {
			return array();
		}

		foreach ($descriptions as $language_id => $description) {
			if (!is_array($description)) {
				continue;
			}

			foreach ($fields as $field) {
				if (isset($description[$field])) {
					$descriptions[$language_id][$field] = $this->decodeHtmlEntitiesForDisplay($description[$field]);
				}
			}
		}

		return $descriptions;
	}

	private function decodeHtmlEntitiesForDisplay($value) {
		if (!is_scalar($value)) {
			return '';
		}

		$decoded = (string)$value;

		for ($i = 0; $i < 2; $i++) {
			$next = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');

			if ($next === $decoded) {
				break;
			}

			$decoded = $next;
		}

		return $decoded;
	}

	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'catalog/option_set')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		foreach ($this->request->post['option_set_description'] as $language_id => $value) {
			if ((utf8_strlen($value['name']) < 1) || (utf8_strlen($value['name']) > 64)) {
				$this->error['name'][$language_id] = $this->language->get('error_name');
			}
		}

		return !$this->error;
	}

	protected function validateCopy() {
		if (!$this->user->hasPermission('modify', 'catalog/option_set')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'catalog/option_set')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	public function updateField() {
		$json = array();

		if (!$this->user->hasPermission('modify', 'catalog/option_set')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->post['option_set_id']) || !isset($this->request->post['field']) || !isset($this->request->post['value'])) {
			$json['error'] = 'Invalid request';
		}

		if (!isset($json['error'])) {
			$option_set_id = (int)$this->request->post['option_set_id'];
			$field = $this->request->post['field'];
			$value = $this->request->post['value'];

			$this->load->model('catalog/option_set');

			if ($field === 'sort_order') {
				$val = (int)$value;

				if ($val < 0) {
					$json['error'] = $this->language->get('error_invalid_sort_order');
				} else {
					$this->model_catalog_option_set->updateOptionSetField($option_set_id, array('sort_order' => $val));
					$json['success'] = true;
					$json['value_html'] = (string)$val;
				}
			} elseif ($field === 'status') {
				$val = $value == '1' || $value === 1 || $value === 'true' ? 1 : 0;
				$this->model_catalog_option_set->updateOptionSetField($option_set_id, array('status' => $val));
				$json['success'] = true;
				$json['value_html'] = $val
					? '<span class="label label-success">' . $this->language->get('text_enabled') . '</span>'
					: '<span class="label label-danger">' . $this->language->get('text_disabled') . '</span>';
			} else {
				$json['error'] = 'Invalid field';
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function getName() {
		$json = array();

		if (!$this->user->hasPermission('modify', 'catalog/option_set')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->get['option_set_id'])) {
			$json['error'] = 'Invalid request';
		}

		if (!isset($json['error'])) {
			$option_set_id = (int)$this->request->get['option_set_id'];

			$this->load->model('catalog/option_set');
			$this->load->model('localisation/language');

			$languages = $this->model_localisation_language->getLanguages();
			$descriptions = $this->model_catalog_option_set->getOptionSetDescriptions($option_set_id);

			$names = array();

			foreach ($languages as $language) {
				$lid = $language['language_id'];
				$names[$lid] = isset($descriptions[$lid]) ? $descriptions[$lid]['name'] : '';
			}

			$json['success'] = true;
			$json['languages'] = array_values($languages);
			$json['names'] = $names;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function updateNames() {
		$json = array();

		if (!$this->user->hasPermission('modify', 'catalog/option_set')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->post['option_set_id']) || !isset($this->request->post['names'])) {
			$json['error'] = 'Invalid request';
		}

		if (!isset($json['error'])) {
			$option_set_id = (int)$this->request->post['option_set_id'];
			$names = $this->request->post['names'];

			$this->load->model('catalog/option_set');
			$this->load->model('localisation/language');

			$languages = $this->model_localisation_language->getLanguages();

			$error_names = array();

			foreach ($languages as $language) {
				$lid = $language['language_id'];

				if (isset($names[$lid])) {
					$name = trim((string)$names[$lid]);

					if (utf8_strlen($name) < 1 || utf8_strlen($name) > 64) {
						$error_names[$lid] = $this->language->get('error_name');
					}
				}
			}

			if (!empty($error_names)) {
				$json['error'] = $this->language->get('error_name');
				$json['error_names'] = $error_names;
			} else {
				$this->model_catalog_option_set->updateOptionSetNames($option_set_id, $names);
				$json['success'] = true;
				$json['value_html'] = htmlspecialchars($names[$this->config->get('config_language_id')] ?? '', ENT_QUOTES, 'UTF-8');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function autocomplete() {
		$json = array();

		if (isset($this->request->get['filter_name'])) {
			$this->load->model('catalog/option');

			$filter_data = array(
				'filter_name' => $this->request->get['filter_name'],
				'start'       => 0,
				'limit'       => 5
			);

			$results = $this->model_catalog_option->getOptions($filter_data);

			foreach ($results as $result) {
				$json[] = array(
					'option_id'    => $result['option_id'],
					'name'         => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8')),
					'type'         => $result['type']
				);
			}
		}

		$sort_order = array();

		foreach ($json as $key => $value) {
			$sort_order[$key] = $value['name'];
		}

		array_multisort($sort_order, SORT_ASC, $json);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function getOptions() {
		$json = array();

		if (!$this->user->hasPermission('modify', 'catalog/product')) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode(array('error' => $this->language->get('error_permission'))));
			return;
		}

		if (!isset($this->request->get['option_set_id'])) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode(array('error' => 'Invalid request')));
			return;
		}

		$this->load->model('catalog/option_set');
		$this->load->model('catalog/option');

		$option_set_id = (int)$this->request->get['option_set_id'];

		$results = $this->model_catalog_option_set->getOptionSetOptions($option_set_id);

		foreach ($results as $result) {
			$option_value_data = array();

			if (in_array($result['type'], array('select', 'radio', 'checkbox', 'image', 'color'))) {
				$option_values = $this->model_catalog_option->getOptionValues($result['option_id']);

				foreach ($option_values as $option_value) {
					$option_value_data[] = array(
						'option_value_id' => $option_value['option_value_id'],
						'name'            => strip_tags(html_entity_decode($option_value['name'], ENT_QUOTES, 'UTF-8'))
					);
				}
			}

			$json[] = array(
				'option_id'    => (int)$result['option_id'],
				'name'         => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8')),
				'type'         => $result['type'],
				'option_value' => $option_value_data
			);
		}

		$sort_order = array();

		foreach ($json as $key => $value) {
			$sort_order[$key] = $value['name'];
		}

		array_multisort($sort_order, SORT_ASC, $json);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
