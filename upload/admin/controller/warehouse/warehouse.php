<?php
/**
 * DockerCart Warehouse admin controller: CRUD for warehouses incl. schedule,
 * holidays, self-pickup and dropship supplier blocks.
 */

declare(strict_types=1);

class ControllerWarehouseWarehouse extends Controller {
	private $error = [];

	public function index() {
		$this->load->language('warehouse/warehouse');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('warehouse/warehouse');
		$this->getList();
	}

	public function add() {
		$this->load->language('warehouse/warehouse');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('warehouse/warehouse');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_warehouse_warehouse->addWarehouse($this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('warehouse/warehouse', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->getForm();
	}

	public function edit() {
		$this->load->language('warehouse/warehouse');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('warehouse/warehouse');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_warehouse_warehouse->editWarehouse((int)$this->request->get['warehouse_id'], $this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('warehouse/warehouse', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->getForm();
	}

	public function delete() {
		$this->load->language('warehouse/warehouse');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('warehouse/warehouse');

		if (isset($this->request->post['selected']) && $this->validateDelete()) {
			foreach ($this->request->post['selected'] as $warehouse_id) {
				try {
					$this->model_warehouse_warehouse->deleteWarehouse((int)$warehouse_id);
				} catch (\RuntimeException $e) {
					continue;
				}
			}
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('warehouse/warehouse', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->getList();
	}

	/**
	 * AJAX: fetch shared holidays to copy into a warehouse form.
	 */
	public function sharedHolidays() {
		$this->load->language('warehouse/warehouse');
		$this->load->model('warehouse/warehouse');

		$json = ['success' => false];

		if ($this->user->hasPermission('access', 'warehouse/warehouse')) {
			$json['holidays'] = $this->model_warehouse_warehouse->getSharedHolidays();
			$json['success'] = true;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	protected function getList() {
		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'is_default';
		}

		if (isset($this->request->get['order'])) {
			$order = $this->request->get['order'];
		} else {
			$order = 'DESC';
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$data['success'] = $this->session->data['success'] ?? '';
		unset($this->session->data['success']);
		$data['error_warning'] = $this->error['warning'] ?? '';
		$data['selected'] = isset($this->request->post['selected']) ? (array)$this->request->post['selected'] : [];

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

		$data['breadcrumbs'] = [];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('warehouse/warehouse', 'user_token=' . $this->session->data['user_token'], true),
		];

		$data['add'] = $this->url->link('warehouse/warehouse/add', 'user_token=' . $this->session->data['user_token'], true);
		$data['delete'] = $this->url->link('warehouse/warehouse/delete', 'user_token=' . $this->session->data['user_token'], true);

		$data['warehouses'] = [];

		$filter_data = [
			'sort' => $sort,
			'order' => $order,
			'start' => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' => $this->config->get('config_limit_admin'),
		];

		$warehouse_total = $this->model_warehouse_warehouse->getTotalWarehouses();

		$results = $this->model_warehouse_warehouse->getWarehouses($filter_data);

		foreach ($results as $result) {
			$data['warehouses'][] = [
				'warehouse_id' => $result['warehouse_id'],
				'name' => $result['name'],
				'type' => $this->language->get('text_type_' . $result['type']),
				'priority' => $result['priority'],
				'is_default' => $result['is_default'],
				'status' => $result['status'],
				'allow_pickup' => $result['allow_pickup'],
				'edit' => $this->url->link('warehouse/warehouse/edit', 'user_token=' . $this->session->data['user_token'] . '&warehouse_id=' . $result['warehouse_id'], true),
				'delete' => $this->url->link('warehouse/warehouse/delete', 'user_token=' . $this->session->data['user_token'] . '&warehouse_id=' . $result['warehouse_id'], true),
			];
		}

		$sort_html = ['name', 'type', 'priority', 'is_default', 'status'];

		foreach ($sort_html as $key) {
			$data['sort_' . $key] = $this->url->link('warehouse/warehouse', 'user_token=' . $this->session->data['user_token'] . '&sort=' . $key . '&order=' . ($order === 'ASC' ? 'DESC' : 'ASC'), true);
		}

		$data['order'] = $order;

		$pagination = new Pagination();
		$pagination->total = $warehouse_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('warehouse/warehouse', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();
		$data['results'] = $pagination->renderResults($this->language->get('text_pagination'));

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('warehouse/warehouse_list', $data));
	}

	protected function getForm() {
		$data['text_form'] = !isset($this->request->get['warehouse_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');
		$data['error_warning'] = $this->error['warning'] ?? '';

		$url = '';

		if (isset($this->request->get['warehouse_id'])) {
			$url .= '&warehouse_id=' . $this->request->get['warehouse_id'];
		}

		$data['breadcrumbs'] = [];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('warehouse/warehouse', 'user_token=' . $this->session->data['user_token'], true),
		];

		if (isset($this->request->get['warehouse_id'])) {
			$data['action'] = $this->url->link('warehouse/warehouse/edit', 'user_token=' . $this->session->data['user_token'] . $url, true);
		} else {
			$data['action'] = $this->url->link('warehouse/warehouse/add', 'user_token=' . $this->session->data['user_token'], true);
		}

		$data['cancel'] = $this->url->link('warehouse/warehouse', 'user_token=' . $this->session->data['user_token'], true);

		if (isset($this->request->get['warehouse_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$warehouse_info = $this->model_warehouse_warehouse->getWarehouse((int)$this->request->get['warehouse_id']);
		} else {
			$warehouse_info = [];
		}

		$fields = ['type', 'is_default', 'priority', 'status', 'sort_order', 'address_1', 'address_2', 'city', 'postcode', 'country_id', 'zone_id', 'latitude', 'longitude', 'phone', 'email', 'map_url', 'prepare_days', 'low_stock', 'allow_pickup', 'pickup_cost', 'pickup_note', 'supplier_name', 'supplier_phone', 'supplier_email', 'supplier_lead_time', 'supplier_note'];

		foreach ($fields as $field) {
			if (isset($this->request->post[$field])) {
				$data[$field] = $this->request->post[$field];
			} elseif (isset($warehouse_info[$field])) {
				$data[$field] = $warehouse_info[$field];
			} else {
				$data[$field] = '';
			}
		}

		// Multilingual names.
		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();

		if (isset($this->request->post['warehouse_description'])) {
			$data['warehouse_description'] = $this->request->post['warehouse_description'];
		} elseif ($warehouse_info) {
			$data['warehouse_description'] = $this->model_warehouse_warehouse->getDescriptions((int)$this->request->get['warehouse_id']);
		} else {
			$data['warehouse_description'] = [];
		}

		$data['error_name'] = $this->error['name'] ?? [];

		if (isset($this->request->post['schedule'])) {
			$data['schedule'] = $this->request->post['schedule'];
		} elseif (isset($warehouse_info['schedule'])) {
			$data['schedule'] = $warehouse_info['schedule'];
		} else {
			$default_schedule = [];

			for ($d = 1; $d <= 7; $d++) {
				$default_schedule[$d] = ['is_open' => ($d !== 7), 'windows' => [['time_from' => '09:00', 'time_to' => '18:00']]];
			}

			$data['schedule'] = $default_schedule;
		}

		if (isset($this->request->post['holiday'])) {
			$data['holiday'] = $this->request->post['holiday'];
		} elseif (isset($warehouse_info['holiday'])) {
			$data['holiday'] = $warehouse_info['holiday'];
		} else {
			$data['holiday'] = [];
		}

		$data['days'] = [
			1 => $this->language->get('text_monday'),
			2 => $this->language->get('text_tuesday'),
			3 => $this->language->get('text_wednesday'),
			4 => $this->language->get('text_thursday'),
			5 => $this->language->get('text_friday'),
			6 => $this->language->get('text_saturday'),
			7 => $this->language->get('text_sunday'),
		];

		// Countries / zones for the address block.
		$this->load->model('localisation/country');
		$data['countries'] = $this->model_localisation_country->getCountries();

		$this->load->model('localisation/zone');
		$data['zones'] = [];

		if ((int)$data['country_id'] > 0) {
			$data['zones'] = $this->model_localisation_zone->getZonesByCountryId((int)$data['country_id']);
		}

		$data['shared_holidays'] = $this->model_warehouse_warehouse->getSharedHolidays();
		$data['shared_holidays_url'] = $this->url->link('warehouse/warehouse/sharedHolidays', 'user_token=' . $this->session->data['user_token'], true);
		$data['user_token'] = $this->session->data['user_token'];
		$data['text_none'] = $this->language->get('text_none');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('warehouse/warehouse_form', $data));
	}

	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'warehouse/warehouse')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		// Multilingual names: every installed language must have one.
		$this->load->model('localisation/language');

		$descriptions = isset($this->request->post['warehouse_description']) && is_array($this->request->post['warehouse_description']) ? $this->request->post['warehouse_description'] : [];

		foreach ($this->model_localisation_language->getLanguages() as $language) {
			$language_id = (int)$language['language_id'];
			$name = trim((string)($descriptions[$language_id]['name'] ?? ''));

			if (utf8_strlen($name) < 1 || utf8_strlen($name) > 255) {
				$this->error['name'][$language_id] = $this->language->get('error_name');
			}
		}

		// Per-warehouse unique custom code is not enforced at DB level (no
		// NOT-NULL unique on an empty string); validated at controller level.
		if (isset($this->request->post['is_default']) && $this->request->post['is_default']) {
			$query = $this->db->query("SELECT `warehouse_id` FROM `" . DB_PREFIX . "warehouse` WHERE `is_default` = '1' AND `warehouse_id` <> '" . (int)($this->request->get['warehouse_id'] ?? 0) . "'");

			if ($query->num_rows) {
				$this->error['warning'] = $this->language->get('error_default_exists');
			}
		}

		return !$this->error;
	}

	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'warehouse/warehouse')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}