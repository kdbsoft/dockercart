<?php
class ControllerCatalogAttributeGroup extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('catalog/attribute_group');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/attribute_group');

		$this->getList();
	}

	public function add() {
		$this->load->language('catalog/attribute_group');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/attribute_group');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_catalog_attribute_group->addAttributeGroup($this->request->post);

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

			$this->response->redirect($this->url->link('catalog/attribute_group', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function edit() {
		$this->load->language('catalog/attribute_group');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/attribute_group');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_catalog_attribute_group->editAttributeGroup($this->request->get['attribute_group_id'], $this->request->post);

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

			$this->response->redirect($this->url->link('catalog/attribute_group', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function copy() {
		$this->load->language('catalog/attribute_group');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/attribute_group');

		$attribute_group_ids = [];

		if (isset($this->request->post['selected'])) {
			$attribute_group_ids = $this->request->post['selected'];
		} elseif (isset($this->request->get['attribute_group_id'])) {
			$attribute_group_ids = [
				(int) $this->request->get['attribute_group_id'],
			];
		}

		if ($attribute_group_ids && $this->validateCopy()) {
			foreach ($attribute_group_ids as $attribute_group_id) {
				$this->model_catalog_attribute_group->copyAttributeGroup(
					(int) $attribute_group_id,
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

			$this->response->redirect($this->url->link('catalog/attribute_group', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	public function delete() {
		$this->load->language('catalog/attribute_group');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/attribute_group');

		$attribute_group_ids = [];

		if (isset($this->request->post['selected'])) {
			$attribute_group_ids = $this->request->post['selected'];
		} elseif (isset($this->request->get['attribute_group_id'])) {
			$attribute_group_ids = [
				(int) $this->request->get['attribute_group_id'],
			];
		}

		if ($attribute_group_ids && $this->validateDelete()) {
			foreach ($attribute_group_ids as $attribute_group_id) {
				$this->model_catalog_attribute_group->deleteAttributeGroup(
					(int) $attribute_group_id,
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

			$this->response->redirect($this->url->link('catalog/attribute_group', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	protected function getList() {
		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'agd.name';
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

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('catalog/attribute_group', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		$data['add'] = $this->url->link('catalog/attribute_group/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['copy'] = $this->url->link('catalog/attribute_group/copy', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['delete'] = $this->url->link('catalog/attribute_group/delete', 'user_token=' . $this->session->data['user_token'] . $url, true);

		$data['attribute_groups'] = array();

		$filter_data = array(
			'sort'  => $sort,
			'order' => $order,
			'start' => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' => $this->config->get('config_limit_admin')
		);

		$attribute_group_total = $this->model_catalog_attribute_group->getTotalAttributeGroups();

		$results = $this->model_catalog_attribute_group->getAttributeGroups($filter_data);

		foreach ($results as $result) {
			$data['attribute_groups'][] = array(
				'attribute_group_id' => $result['attribute_group_id'],
				'name'               => $result['name'],
				'name_raw'           => $result['name'],
				'sort_order'         => $result['sort_order'],
				'sort_order_raw'     => $result['sort_order'],
				'edit'               => $this->url->link('catalog/attribute_group/edit', 'user_token=' . $this->session->data['user_token'] . '&attribute_group_id=' . $result['attribute_group_id'] . $url, true),
				'copy'               => $this->url->link('catalog/attribute_group/copy', 'user_token=' . $this->session->data['user_token'] . '&attribute_group_id=' . $result['attribute_group_id'] . $url, true),
				'delete'             => $this->url->link('catalog/attribute_group/delete', 'user_token=' . $this->session->data['user_token'] . '&attribute_group_id=' . $result['attribute_group_id'] . $url, true)
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

		$data['sort_name'] = $this->url->link('catalog/attribute_group', 'user_token=' . $this->session->data['user_token'] . '&sort=agd.name' . $url, true);
		$data['sort_sort_order'] = $this->url->link('catalog/attribute_group', 'user_token=' . $this->session->data['user_token'] . '&sort=ag.sort_order' . $url, true);

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		$pagination = new Pagination();
		$pagination->total = $attribute_group_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('catalog/attribute_group', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($attribute_group_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($attribute_group_total - $this->config->get('config_limit_admin'))) ? $attribute_group_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $attribute_group_total, ceil($attribute_group_total / $this->config->get('config_limit_admin')));

		$data['sort'] = $sort;
		$data['order'] = $order;

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/attribute_group_list', $data));
	}

	protected function getForm() {
		$data['text_form'] = !isset($this->request->get['attribute_group_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');
		$data['text_form_subtitle'] = !isset($this->request->get['attribute_group_id'])
		    ? $this->language->get('text_add_attribute_group_subtitle')
		    : $this->language->get('text_edit_attribute_group_subtitle');

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

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('catalog/attribute_group', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		if (!isset($this->request->get['attribute_group_id'])) {
			$data['action'] = $this->url->link('catalog/attribute_group/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		} else {
			$data['action'] = $this->url->link('catalog/attribute_group/edit', 'user_token=' . $this->session->data['user_token'] . '&attribute_group_id=' . $this->request->get['attribute_group_id'] . $url, true);
		}

		$data['cancel'] = $this->url->link('catalog/attribute_group', 'user_token=' . $this->session->data['user_token'] . $url, true);

		if (isset($this->request->get['attribute_group_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$attribute_group_info = $this->model_catalog_attribute_group->getAttributeGroup($this->request->get['attribute_group_id']);
		}

		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();

		if (isset($this->request->post['attribute_group_description'])) {
			$data['attribute_group_description'] = $this->request->post['attribute_group_description'];
		} elseif (isset($this->request->get['attribute_group_id'])) {
			$data['attribute_group_description'] = $this->model_catalog_attribute_group->getAttributeGroupDescriptions($this->request->get['attribute_group_id']);
		} else {
			$data['attribute_group_description'] = array();
		}

		$data['attribute_group_description'] = $this->decodeDescriptionFields($data['attribute_group_description'], array('name'));

		if (isset($this->request->post['sort_order'])) {
			$data['sort_order'] = $this->request->post['sort_order'];
		} elseif (!empty($attribute_group_info)) {
			$data['sort_order'] = $attribute_group_info['sort_order'];
		} else {
			$data['sort_order'] = '';
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/attribute_group_form', $data));
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
		if (!$this->user->hasPermission('modify', 'catalog/attribute_group')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		foreach ($this->request->post['attribute_group_description'] as $language_id => $value) {
			if ((utf8_strlen($value['name']) < 1) || (utf8_strlen($value['name']) > 64)) {
				$this->error['name'][$language_id] = $this->language->get('error_name');
			}
		}

		return !$this->error;
	}

	protected function validateCopy() {
		if (!$this->user->hasPermission('modify', 'catalog/attribute_group')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'catalog/attribute_group')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$attribute_group_ids = [];

		if (isset($this->request->post['selected'])) {
			$attribute_group_ids = $this->request->post['selected'];
		} elseif (isset($this->request->get['attribute_group_id'])) {
			$attribute_group_ids = [(int) $this->request->get['attribute_group_id']];
		}

		$this->load->model('catalog/attribute');

		foreach ($attribute_group_ids as $attribute_group_id) {
			$attribute_total = $this->model_catalog_attribute->getTotalAttributesByAttributeGroupId($attribute_group_id);

			if ($attribute_total) {
				$this->error['warning'] = sprintf($this->language->get('error_attribute'), $attribute_total);
			}
		}

		return !$this->error;
	}

	public function updateField() {
		$json = array();

		if (!$this->user->hasPermission('modify', 'catalog/attribute_group')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->post['attribute_group_id']) || !isset($this->request->post['field']) || !isset($this->request->post['value'])) {
			$json['error'] = 'Invalid request';
		}

		if (!isset($json['error'])) {
			$attribute_group_id = (int)$this->request->post['attribute_group_id'];
			$field = $this->request->post['field'];
			$value = $this->request->post['value'];

			$this->load->model('catalog/attribute_group');

			if ($field === 'sort_order') {
				$val = (int)$value;

				if ($val < 0) {
					$json['error'] = $this->language->get('error_invalid_sort_order');
				} else {
					$this->model_catalog_attribute_group->updateAttributeGroupField($attribute_group_id, array('sort_order' => $val));
					$json['success'] = true;
					$json['value_html'] = (string)$val;
				}
			} else {
				$json['error'] = 'Invalid field';
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function getName() {
		$json = array();

		if (!$this->user->hasPermission('modify', 'catalog/attribute_group')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->get['attribute_group_id'])) {
			$json['error'] = 'Invalid request';
		}

		if (!isset($json['error'])) {
			$attribute_group_id = (int)$this->request->get['attribute_group_id'];

			$this->load->model('catalog/attribute_group');
			$this->load->model('localisation/language');

			$languages = $this->model_localisation_language->getLanguages();
			$descriptions = $this->model_catalog_attribute_group->getAttributeGroupDescriptions($attribute_group_id);

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

		if (!$this->user->hasPermission('modify', 'catalog/attribute_group')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->post['attribute_group_id']) || !isset($this->request->post['names'])) {
			$json['error'] = 'Invalid request';
		}

		if (!isset($json['error'])) {
			$attribute_group_id = (int)$this->request->post['attribute_group_id'];
			$names = $this->request->post['names'];

			$this->load->model('catalog/attribute_group');
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
				$this->model_catalog_attribute_group->updateAttributeGroupNames($attribute_group_id, $names);
				$json['success'] = true;
				$json['value_html'] = htmlspecialchars($names[$this->config->get('config_language_id')] ?? '', ENT_QUOTES, 'UTF-8');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
