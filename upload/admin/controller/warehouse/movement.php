<?php
/**
 * DockerCart Warehouse Movement admin controller: manual inbound/outbound/
 * inventory movements and the filtered journal with a per-position stock map.
 */

declare(strict_types=1);

class ControllerWarehouseMovement extends Controller {
	public function index() {
		$this->load->language('warehouse/movement');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('warehouse/movement');
		$this->getList();
	}

	/**
	 * POST: apply a manual movement (form at top of the list screen).
	 */
	public function add() {
		$this->load->language('warehouse/movement');
		$this->load->model('warehouse/movement');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateAdd()) {
			$this->model_warehouse_movement->addMovement($this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('warehouse/movement', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->getList();
	}

	public function stockMap() {
		$this->load->language('warehouse/movement');

		$data['lines'] = [];

		if (isset($this->request->get['product_id'])) {
			$this->load->model('warehouse/movement');

			$lines = $this->model_warehouse_movement->getStockMap([
				'product_id' => (int)$this->request->get['product_id'],
				'variant_id' => (int)($this->request->get['variant_id'] ?? 0),
				'warehouse_id' => (int)($this->request->get['warehouse_id'] ?? 0),
			]);

			foreach ($lines as $line) {
				$data['lines'][] = [
					'date' => $line['date_added'],
					'warehouse' => $line['warehouse_name'] ?? '',
					'type' => $this->language->get('text_type_' . $line['type']),
					'quantity' => $line['quantity'],
					'reference' => $line['reference'],
					'comment' => $line['comment'],
					'balance' => $line['balance'],
				];
			}
		}

		$data['cancel'] = $this->url->link('warehouse/movement', 'user_token=' . $this->session->data['user_token'], true);
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('warehouse/movement_map', $data));
	}

	protected function getList() {
		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$filters = ['filter_warehouse_id', 'filter_product_id', 'filter_type', 'filter_order_id', 'filter_date_from', 'filter_date_to'];

		$filter_data = [];

		foreach ($filters as $key) {
			if (isset($this->request->get[$key]) && $this->request->get[$key] !== '') {
				$filter_data[$key] = $this->request->get[$key];
			}
		}

		$data['breadcrumbs'] = [];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('warehouse/movement', 'user_token=' . $this->session->data['user_token'], true),
		];

		$data['add'] = $this->url->link('warehouse/movement/add', 'user_token=' . $this->session->data['user_token'], true);
		$data['user_token'] = $this->session->data['user_token'];
		$data['stock_map_url'] = $this->url->link('warehouse/movement/stockMap', 'user_token=' . $this->session->data['user_token'], true);
		$data['success'] = $this->session->data['success'] ?? '';
		unset($this->session->data['success']);
		$data['error_warning'] = $this->error['warning'] ?? '';
		$data['error_warehouse'] = $this->error['warehouse'] ?? '';
		$data['error_product'] = $this->error['product'] ?? '';
		$data['error_quantity'] = $this->error['quantity'] ?? '';

		$this->load->model('warehouse/warehouse');
		$data['warehouses'] = $this->model_warehouse_warehouse->getWarehouses(['sort' => 'priority', 'order' => 'DESC', 'limit' => 1000]);

		$filter_data['start'] = ($page - 1) * $this->config->get('config_limit_admin');
		$filter_data['limit'] = $this->config->get('config_limit_admin');

		$total = $this->model_warehouse_movement->getTotalMovements($filter_data);
		$results = $this->model_warehouse_movement->getMovements($filter_data);

		foreach ($results as $result) {
			$data['movements'][] = [
				'movement_id' => $result['movement_id'],
				'date' => $result['date_added'],
				'warehouse' => $result['warehouse_name'] ?? '',
				'warehouse_id' => $result['warehouse_id'],
				'product_id' => $result['product_id'],
				'variant_id' => $result['variant_id'],
				'product_model' => $result['product_model'],
				'variant_sku' => $result['variant_sku'],
				'type' => $this->language->get('text_type_' . $result['type']),
				'quantity' => $result['quantity'],
				'reference' => $result['reference'],
				'order_id' => $result['order_id'],
				'comment' => $result['comment'],
				'user_id' => $result['user_id'],
			];
		}

		$data['types'] = [
			'inbound' => $this->language->get('text_type_inbound'),
			'outbound' => $this->language->get('text_type_outbound'),
			'adjustment' => $this->language->get('text_type_adjustment'),
			'transfer_in' => $this->language->get('text_type_transfer_in'),
			'transfer_out' => $this->language->get('text_type_transfer_out'),
			'order_subtract' => $this->language->get('text_type_order_subtract'),
			'order_restock' => $this->language->get('text_type_order_restock'),
			'return' => $this->language->get('text_type_return'),
		];

		$data['filter'] = $filter_data;

		$url = '';

		foreach ($filters as $key) {
			if (isset($filter_data[$key])) {
				$url .= '&' . $key . '=' . urlencode((string)$filter_data[$key]);
			}
		}

		$pagination = new Pagination();
		$pagination->total = $total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('warehouse/movement', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();
		$data['results'] = $pagination->renderResults($this->language->get('text_pagination'));

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('warehouse/movement_list', $data));
	}

	protected function validateAdd() {
		if (!$this->user->hasPermission('modify', 'warehouse/movement')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if ((int)($this->request->post['warehouse_id'] ?? 0) <= 0) {
			$this->error['warehouse'] = $this->language->get('error_warehouse');
		}

		if ((int)($this->request->post['product_id'] ?? 0) <= 0) {
			$this->error['product'] = $this->language->get('error_product');
		}

		if (!isset($this->request->post['quantity']) || (float)$this->request->post['quantity'] <= 0) {
			$this->error['quantity'] = $this->language->get('error_quantity');
		}

		return !$this->error;
	}
}