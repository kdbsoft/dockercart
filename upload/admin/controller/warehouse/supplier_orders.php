<?php
/**
 * DockerCart Warehouse Supplier Orders admin controller: dropship lines per
 * supplier with deadline highlighting, status updates and CSV export.
 */

declare(strict_types=1);

class ControllerWarehouseSupplierOrders extends Controller {
	public function index() {
		$this->load->language('warehouse/supplier_orders');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('warehouse/supplier_orders');
		$this->getList();
	}

	/**
	 * POST: update supplier status / tracking on a dropship line.
	 */
	public function updateLine() {
		$this->load->language('warehouse/supplier_orders');
		$this->load->model('warehouse/supplier_orders');

		$json = ['success' => false];

		if ($this->user->hasPermission('modify', 'warehouse/supplier_orders')) {
			$input = $this->request->post;
			$order_product_id = (int)($input['order_product_id'] ?? 0);
			$action = (string)($input['action'] ?? '');

			if ($action === 'ordered') {
				$this->model_warehouse_supplier_orders->markOrdered($order_product_id);
			} elseif ($action === 'shipped') {
				$this->model_warehouse_supplier_orders->markShipped($order_product_id, (string)($input['tracking'] ?? ''));
			}

			$json['success'] = true;
		} else {
			$json['error'] = $this->language->get('error_permission');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * GET: export CSV of the current filtered dropship lines.
	 */
	public function export() {
		$this->load->language('warehouse/supplier_orders');
		$this->load->model('warehouse/supplier_orders');

		if (!$this->user->hasPermission('access', 'warehouse/supplier_orders')) {
			return;
		}

		$filter_data = [];

		foreach (['filter_supplier_id', 'filter_status', 'filter_ordered'] as $key) {
			if (isset($this->request->get[$key]) && $this->request->get[$key] !== '') {
				$filter_data[$key] = $this->request->get[$key];
			}
		}

		$rows = $this->model_warehouse_supplier_orders->getOrders($filter_data + ['start' => 0, 'limit' => 100000]);

		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="supplier_orders.csv"');
		$out = fopen('php://output', 'w');
		fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM

		fputcsv($out, [
			'Order ID',
			'Supplier',
			'Product',
			'Model',
			'Variant SKU',
			'Quantity',
			'Supplier status',
			'Ordered date',
			'Deadline',
			'Tracking',
			'Customer',
		]);

		foreach ($rows as $row) {
			fputcsv($out, [
				$row['order_id'],
				$row['supplier_name'],
				$row['name'],
				$row['model'],
				$row['variant_sku'],
				$row['quantity'],
				$this->language->get('text_line_' . $row['supplier_status']),
				$row['supplier_ordered_date'],
				$row['deadline'],
				$row['supplier_tracking'],
				$row['customer_firstname'] . ' ' . $row['customer_lastname'],
			]);
		}

		fclose($out);
	}

	protected function getList() {
		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$filter_data = [];

		foreach (['filter_supplier_id', 'filter_status', 'filter_ordered'] as $key) {
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
			'href' => $this->url->link('warehouse/supplier_orders', 'user_token=' . $this->session->data['user_token'], true),
		];

		$data['export'] = $this->url->link('warehouse/supplier_orders/export', 'user_token=' . $this->session->data['user_token'], true);
		$data['user_token'] = $this->session->data['user_token'];

		$data['suppliers'] = $this->model_warehouse_supplier_orders->getDropshipWarehouses();

		$filter_data['start'] = ($page - 1) * $this->config->get('config_limit_admin');
		$filter_data['limit'] = $this->config->get('config_limit_admin');

		$total = $this->model_warehouse_supplier_orders->getTotalOrders($filter_data);
		$results = $this->model_warehouse_supplier_orders->getOrders($filter_data);

		$today = date('Y-m-d');

		foreach ($results as $result) {
			$overdue = false;

			if (!empty($result['deadline']) && $result['supplier_status'] !== 'shipped') {
				$overdue = $result['deadline'] < $today;
			}

			$data['orders'][] = [
				'order_product_id' => $result['order_product_id'],
				'order_id' => $result['order_id'],
				'supplier_name' => $result['supplier_name'],
				'customer' => $result['customer_firstname'] . ' ' . $result['customer_lastname'],
				'name' => $result['name'],
				'model' => $result['model'],
				'variant_sku' => $result['variant_sku'],
				'quantity' => $result['quantity'],
				'supplier_status' => $result['supplier_status'],
				'supplier_status_text' => $this->language->get('text_line_' . $result['supplier_status']),
				'ordered_date' => $result['supplier_ordered_date'],
				'deadline' => $result['deadline'],
				'tracking' => $result['supplier_tracking'],
				'overdue' => $overdue,
			];
		}

		$data['filter'] = $filter_data;
		$data['status_options'] = [
			'' => $this->language->get('text_all_status'),
			'pending' => $this->language->get('text_line_pending'),
			'ordered' => $this->language->get('text_line_ordered'),
			'shipped' => $this->language->get('text_line_shipped'),
		];
		$data['ordered_options'] = [
			'' => $this->language->get('text_all_ordered'),
			'pending' => $this->language->get('text_ordered_pending'),
			'ordered' => $this->language->get('text_ordered_done'),
		];

		$url = '';

		foreach (['filter_supplier_id', 'filter_status', 'filter_ordered'] as $key) {
			if (isset($filter_data[$key])) {
				$url .= '&' . $key . '=' . urlencode((string)$filter_data[$key]);
			}
		}

		$url .= '&export=1';

		$data['export_url'] = $this->url->link('warehouse/supplier_orders/export', 'user_token=' . $this->session->data['user_token'] . $url, true);

		$pagination = new Pagination();
		$pagination->total = $total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('warehouse/supplier_orders', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();
		$data['results'] = $pagination->renderResults($this->language->get('text_pagination'));

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('warehouse/supplier_orders_list', $data));
	}
}