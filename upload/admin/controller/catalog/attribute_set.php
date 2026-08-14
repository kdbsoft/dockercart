<?php
class ControllerCatalogAttributeSet extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('catalog/attribute_set');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/attribute_set');

		$this->getList();
	}

	public function add() {
		$this->load->language('catalog/attribute_set');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/attribute_set');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_catalog_attribute_set->addAttributeSet($this->request->post);

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

			$this->response->redirect($this->url->link('catalog/attribute_set', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function edit() {
		$this->load->language('catalog/attribute_set');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/attribute_set');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_catalog_attribute_set->editAttributeSet($this->request->get['attribute_set_id'], $this->request->post);

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

			$this->response->redirect($this->url->link('catalog/attribute_set', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function copy() {
		$this->load->language('catalog/attribute_set');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/attribute_set');

		$attribute_set_ids = [];

		if (isset($this->request->post['selected'])) {
			$attribute_set_ids = $this->request->post['selected'];
		} elseif (isset($this->request->get['attribute_set_id'])) {
			$attribute_set_ids = [
				(int) $this->request->get['attribute_set_id'],
			];
		}

		if ($attribute_set_ids && $this->validateCopy()) {
			foreach ($attribute_set_ids as $attribute_set_id) {
				$this->model_catalog_attribute_set->copyAttributeSet(
					(int) $attribute_set_id,
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

			$this->response->redirect($this->url->link('catalog/attribute_set', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	public function delete() {
		$this->load->language('catalog/attribute_set');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/attribute_set');

		$attribute_set_ids = [];

		if (isset($this->request->post['selected'])) {
			$attribute_set_ids = $this->request->post['selected'];
		} elseif (isset($this->request->get['attribute_set_id'])) {
			$attribute_set_ids = [
				(int) $this->request->get['attribute_set_id'],
			];
		}

		if ($attribute_set_ids && $this->validateDelete()) {
			foreach ($attribute_set_ids as $attribute_set_id) {
				$this->model_catalog_attribute_set->deleteAttributeSet(
					(int) $attribute_set_id,
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

			$this->response->redirect($this->url->link('catalog/attribute_set', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	protected function getList() {
		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'astd.name';
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

		$data['add'] = $this->url->link('catalog/attribute_set/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['copy'] = $this->url->link('catalog/attribute_set/copy', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['delete'] = $this->url->link('catalog/attribute_set/delete', 'user_token=' . $this->session->data['user_token'] . $url, true);

		$data['attribute_sets'] = array();

		$filter_data = array(
			'sort'  => $sort,
			'order' => $order,
			'start' => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' => $this->config->get('config_limit_admin')
		);

		$attribute_set_total = $this->model_catalog_attribute_set->getTotalAttributeSets();

		$results = $this->model_catalog_attribute_set->getAttributeSets($filter_data);

		foreach ($results as $result) {
			$data['attribute_sets'][] = array(
				'attribute_set_id' => $result['attribute_set_id'],
				'name'             => $result['name'],
				'name_raw'         => $result['name'],
				'attribute_count'  => $result['attribute_count'],
				'sort_order'       => $result['sort_order'],
				'sort_order_raw'   => $result['sort_order'],
				'status'           => $result['status'],
				'status_raw'       => $result['status'],
				'edit'             => $this->url->link('catalog/attribute_set/edit', 'user_token=' . $this->session->data['user_token'] . '&attribute_set_id=' . $result['attribute_set_id'] . $url, true),
				'copy'             => $this->url->link('catalog/attribute_set/copy', 'user_token=' . $this->session->data['user_token'] . '&attribute_set_id=' . $result['attribute_set_id'] . $url, true),
				'delete'           => $this->url->link('catalog/attribute_set/delete', 'user_token=' . $this->session->data['user_token'] . '&attribute_set_id=' . $result['attribute_set_id'] . $url, true)
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

		$data['sort_name'] = $this->url->link('catalog/attribute_set', 'user_token=' . $this->session->data['user_token'] . '&sort=astd.name' . $url, true);
		$data['sort_sort_order'] = $this->url->link('catalog/attribute_set', 'user_token=' . $this->session->data['user_token'] . '&sort=ast.sort_order' . $url, true);

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		$pagination = new Pagination();
		$pagination->total = $attribute_set_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('catalog/attribute_set', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = $pagination->renderResults($this->language->get('text_pagination'));

		$data['sort'] = $sort;
		$data['order'] = $order;

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/attribute_set_list', $data));
	}

	protected function getForm() {
		$data['text_form'] = !isset($this->request->get['attribute_set_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');
		$data['text_form_subtitle'] = !isset($this->request->get['attribute_set_id'])
		    ? $this->language->get('text_add_attribute_set_subtitle')
		    : $this->language->get('text_edit_attribute_set_subtitle');

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

		if (!isset($this->request->get['attribute_set_id'])) {
			$data['action'] = $this->url->link('catalog/attribute_set/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		} else {
			$data['action'] = $this->url->link('catalog/attribute_set/edit', 'user_token=' . $this->session->data['user_token'] . '&attribute_set_id=' . $this->request->get['attribute_set_id'] . $url, true);
		}

		$data['cancel'] = $this->url->link('catalog/attribute_set', 'user_token=' . $this->session->data['user_token'] . $url, true);

		if (isset($this->request->get['attribute_set_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$attribute_set_info = $this->model_catalog_attribute_set->getAttributeSet($this->request->get['attribute_set_id']);
		}

		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();

		if (isset($this->request->post['attribute_set_description'])) {
			$data['attribute_set_description'] = $this->request->post['attribute_set_description'];
		} elseif (isset($this->request->get['attribute_set_id'])) {
			$data['attribute_set_description'] = $this->model_catalog_attribute_set->getAttributeSetDescriptions($this->request->get['attribute_set_id']);
		} else {
			$data['attribute_set_description'] = array();
		}

		$data['attribute_set_description'] = $this->decodeDescriptionFields($data['attribute_set_description'], array('name'));

		$data['entity_name'] = '';
		$lang_id = (int)$this->config->get('config_language_id');
		if (!empty($data['attribute_set_description'][$lang_id]['name'])) {
			$data['entity_name'] = $data['attribute_set_description'][$lang_id]['name'];
		}

		if (isset($this->request->post['sort_order'])) {
			$data['sort_order'] = $this->request->post['sort_order'];
		} elseif (!empty($attribute_set_info)) {
			$data['sort_order'] = $attribute_set_info['sort_order'];
		} else {
			$data['sort_order'] = '';
		}

		if (isset($this->request->post['status'])) {
			$data['status'] = $this->request->post['status'];
		} elseif (!empty($attribute_set_info)) {
			$data['status'] = $attribute_set_info['status'];
		} else {
			$data['status'] = true;
		}

		// Attributes in the set
		$data['attribute_set_attributes'] = array();

		if (isset($this->request->post['attribute_set_attribute'])) {
			$set_attributes = $this->request->post['attribute_set_attribute'];
		} elseif (isset($this->request->get['attribute_set_id'])) {
			$set_attributes = $this->model_catalog_attribute_set->getAttributeSetAttributes($this->request->get['attribute_set_id']);
		} else {
			$set_attributes = array();
		}

		foreach ($set_attributes as $set_attribute) {
			if (empty($set_attribute['attribute_id'])) {
				continue;
			}

			$name = '';
			$group = '';

			if (isset($set_attribute['name'])) {
				$name = $this->decodeHtmlEntitiesForDisplay($set_attribute['name']);
			} else {
				$this->load->model('catalog/attribute');
				$attribute_info = $this->model_catalog_attribute->getAttribute((int)$set_attribute['attribute_id']);

				if ($attribute_info) {
					$name = $this->decodeHtmlEntitiesForDisplay($attribute_info['name']);
				}
			}

			$data['attribute_set_attributes'][] = array(
				'attribute_id'     => (int)$set_attribute['attribute_id'],
				'name'             => $name,
				'attribute_group'  => isset($set_attribute['attribute_group']) ? $set_attribute['attribute_group'] : ''
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

		$this->response->setOutput($this->load->view('catalog/attribute_set_form', $data));
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
		if (!$this->user->hasPermission('modify', 'catalog/attribute_set')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		foreach ($this->request->post['attribute_set_description'] as $language_id => $value) {
			if ((utf8_strlen($value['name']) < 1) || (utf8_strlen($value['name']) > 64)) {
				$this->error['name'][$language_id] = $this->language->get('error_name');
			}
		}

		return !$this->error;
	}

	protected function validateCopy() {
		if (!$this->user->hasPermission('modify', 'catalog/attribute_set')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'catalog/attribute_set')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	public function updateField() {
		$json = array();

		if (!$this->user->hasPermission('modify', 'catalog/attribute_set')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->post['attribute_set_id']) || !isset($this->request->post['field']) || !isset($this->request->post['value'])) {
			$json['error'] = 'Invalid request';
		}

		if (!isset($json['error'])) {
			$attribute_set_id = (int)$this->request->post['attribute_set_id'];
			$field = $this->request->post['field'];
			$value = $this->request->post['value'];

			$this->load->model('catalog/attribute_set');

			if ($field === 'sort_order') {
				$val = (int)$value;

				if ($val < 0) {
					$json['error'] = $this->language->get('error_invalid_sort_order');
				} else {
					$this->model_catalog_attribute_set->updateAttributeSetField($attribute_set_id, array('sort_order' => $val));
					$json['success'] = true;
					$json['value_html'] = (string)$val;
				}
			} elseif ($field === 'status') {
				$val = $value == '1' || $value === 1 || $value === 'true' ? 1 : 0;
				$this->model_catalog_attribute_set->updateAttributeSetField($attribute_set_id, array('status' => $val));
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

		if (!$this->user->hasPermission('modify', 'catalog/attribute_set')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->get['attribute_set_id'])) {
			$json['error'] = 'Invalid request';
		}

		if (!isset($json['error'])) {
			$attribute_set_id = (int)$this->request->get['attribute_set_id'];

			$this->load->model('catalog/attribute_set');
			$this->load->model('localisation/language');

			$languages = $this->model_localisation_language->getLanguages();
			$descriptions = $this->model_catalog_attribute_set->getAttributeSetDescriptions($attribute_set_id);

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

		if (!$this->user->hasPermission('modify', 'catalog/attribute_set')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->post['attribute_set_id']) || !isset($this->request->post['names'])) {
			$json['error'] = 'Invalid request';
		}

		if (!isset($json['error'])) {
			$attribute_set_id = (int)$this->request->post['attribute_set_id'];
			$names = $this->request->post['names'];

			$this->load->model('catalog/attribute_set');
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
				$this->model_catalog_attribute_set->updateAttributeSetNames($attribute_set_id, $names);
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
			$this->load->model('catalog/attribute');

			$filter_data = array(
				'filter_name' => $this->request->get['filter_name'],
				'start'       => 0,
				'limit'       => 5
			);

			$results = $this->model_catalog_attribute->getAttributes($filter_data);

			foreach ($results as $result) {
				$json[] = array(
					'attribute_id'    => $result['attribute_id'],
					'name'            => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8')),
					'attribute_group' => $result['attribute_group']
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

	public function getAttributes() {
		$json = array();

		if (!$this->user->hasPermission('modify', 'catalog/product')) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode(array('error' => $this->language->get('text_error_permission'))));
			return;
		}

		if (!isset($this->request->get['attribute_set_id'])) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode(array('error' => 'Invalid request')));
			return;
		}

		$this->load->model('catalog/attribute_set');

		$attribute_set_id = (int)$this->request->get['attribute_set_id'];

		$results = $this->model_catalog_attribute_set->getAttributeSetAttributes($attribute_set_id);

		foreach ($results as $result) {
			$json[] = array(
				'attribute_id'    => (int)$result['attribute_id'],
				'name'            => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8')),
				'attribute_group' => $result['attribute_group']
			);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
