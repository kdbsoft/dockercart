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
	 * Search-as-you-type for the toolbar: dropship orders and products.
	 */
	public function autocomplete() {
		$json = [];

		$this->load->language('warehouse/supplier_orders');

		if ($this->user->hasPermission('access', 'warehouse/supplier_orders')) {
			$filter_search = trim((string)($this->request->get['filter_search'] ?? ''));

			if ($filter_search !== '') {
				$this->load->model('warehouse/supplier_orders');

				foreach ($this->model_warehouse_supplier_orders->autocompleteOrders($filter_search, 5) as $result) {
					$json[] = [
						'id' => '#' . $result['order_id'],
						'name' => trim((string)$result['customer']) !== '' ? $result['customer'] : '—',
						'subtitle' => $result['product'],
						'href' => $this->url->link('warehouse/supplier_orders', 'user_token=' . $this->session->data['user_token'] . '&filter_order_id=' . (int)$result['order_id'], true),
					];
				}

				$this->load->model('warehouse/stock');

				foreach ($this->model_warehouse_stock->autocompleteProducts($filter_search, 5) as $result) {
					$json[] = [
						'id' => '#' . $result['product_id'],
						'name' => $result['name'],
						'subtitle' => $result['model'],
						'href' => $this->url->link('warehouse/supplier_orders', 'user_token=' . $this->session->data['user_token'] . '&filter_product_id=' . (int)$result['product_id'], true),
					];
				}
			}
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

		foreach (['filter_supplier_id', 'filter_status', 'filter_ordered', 'filter_product_id', 'filter_order_id'] as $key) {
			if (isset($this->request->get[$key]) && $this->request->get[$key] !== '') {
				$filter_data[$key] = $this->request->get[$key];
			}
		}

		if (!empty($this->request->get['filter_overdue'])) {
			$filter_data['filter_overdue'] = 1;
		}

		if (!empty($this->request->get['filter_id'])) {
			$saved = $this->getActiveUserFilter('warehouse_supplier_orders');

			if ($saved) {
				foreach ($this->buildSupplierFilterData($saved['conditions']) as $key => $value) {
					$filter_data[$key] = $value;
				}
			}
		}

		$rows = $this->model_warehouse_supplier_orders->getOrders($filter_data + ['start' => 0, 'limit' => 100000]);

		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="supplier_orders.csv"');
		$out = fopen('php://output', 'w');
		fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM

		fputcsv($out, [
			$this->language->get('column_order'),
			$this->language->get('column_supplier'),
			$this->language->get('column_product'),
			$this->language->get('column_model'),
			$this->language->get('column_variant'),
			$this->language->get('column_quantity'),
			$this->language->get('column_status'),
			$this->language->get('column_ordered'),
			$this->language->get('column_deadline'),
			$this->language->get('column_tracking'),
			$this->language->get('column_customer'),
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
				trim($row['customer_firstname'] . ' ' . $row['customer_lastname']),
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

		$filter_keys = ['filter_supplier_id', 'filter_status', 'filter_ordered', 'filter_product_id', 'filter_order_id'];

		$filter_data = [];

		foreach ($filter_keys as $key) {
			if (isset($this->request->get[$key]) && (string)$this->request->get[$key] !== '') {
				$filter_data[$key] = (string)$this->request->get[$key];
			}
		}

		$overdue_active = isset($this->request->get['filter_overdue']) && $this->request->get['filter_overdue'] === '1';

		$data['breadcrumbs'] = [];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('warehouse/supplier_orders', 'user_token=' . $this->session->data['user_token'], true),
		];

		$data['user_token'] = $this->session->data['user_token'];

		// Per-admin saved filters (Shopify-style tabs).
		$active_filter = $this->getActiveUserFilter('warehouse_supplier_orders');

		$this->load->model('user/user_filter');

		$user_id = (int)$this->user->getId();
		$saved_filters = $this->model_user_user_filter->getFilters($user_id, 'warehouse_supplier_orders');

		$status_keys = ['pending', 'ordered', 'shipped'];

		$tab_counts = [
			'all' => $this->model_warehouse_supplier_orders->getTotalOrders([]),
		];

		foreach ($status_keys as $status_key) {
			$tab_counts[$status_key] = $this->model_warehouse_supplier_orders->getTotalOrders(['filter_status' => $status_key]);
		}

		$tab_counts['overdue'] = $this->model_warehouse_supplier_orders->getTotalOrders(['filter_overdue' => 1]);

		foreach ($saved_filters as $saved) {
			$tab_counts['custom_' . $saved['filter_id']] = $this->model_warehouse_supplier_orders->getTotalOrders($this->buildSupplierFilterData($saved['conditions']));
		}

		// Builtin status tabs on top of the "All" tab.
		$builtin_tabs = [];

		foreach ($status_keys as $status_key) {
			$builtin_tabs[] = [
				'id' => $status_key,
				'name' => $this->language->get('text_line_' . $status_key),
				'href' => $this->url->link('warehouse/supplier_orders', 'user_token=' . $this->session->data['user_token'] . '&filter_status=' . $status_key, true),
				'count' => $tab_counts[$status_key],
				'is_active' => ($filter_data['filter_status'] ?? '') === $status_key,
			];
		}

		$builtin_tabs[] = [
			'id' => 'overdue',
			'name' => $this->language->get('text_overdue'),
			'href' => $this->url->link('warehouse/supplier_orders', 'user_token=' . $this->session->data['user_token'] . '&filter_overdue=1', true),
			'count' => $tab_counts['overdue'],
			'is_active' => $overdue_active,
		];

		// Filter builder fields for the add-filter modal.
		$supplier_options = [];

		foreach ($this->model_warehouse_supplier_orders->getDropshipWarehouses() as $supplier) {
			$supplier_options[] = [
				'value' => (string)$supplier['warehouse_id'],
				'label' => $supplier['supplier_name'] ? $supplier['supplier_name'] : $supplier['name'],
			];
		}

		$status_options = [];

		foreach ($status_keys as $status_key) {
			$status_options[] = ['value' => $status_key, 'label' => $this->language->get('text_line_' . $status_key)];
		}

		$ordered_options = [
			['value' => 'pending', 'label' => $this->language->get('text_ordered_pending')],
			['value' => 'ordered', 'label' => $this->language->get('text_ordered_done')],
		];

		$search = [
			'placeholder' => $this->language->get('text_search_placeholder'),
			'url' => $this->url->link('warehouse/supplier_orders/autocomplete', 'user_token=' . $this->session->data['user_token'], true),
		];

		// Active product/order filter (from a search pick): restore the query in
		// the search box and provide a one-click reset.
		$picked_key = '';

		if (isset($filter_data['filter_product_id'])) {
			$picked_key = 'filter_product_id';
		} elseif (isset($filter_data['filter_order_id'])) {
			$picked_key = 'filter_order_id';
		}

		if ($picked_key !== '') {
			if ($picked_key === 'filter_product_id') {
				$this->load->model('warehouse/stock');

				$product = $this->model_warehouse_stock->getStockProduct((int)$filter_data['filter_product_id']);

				$search['value'] = $product ? (string)$product['name'] : '#' . $filter_data['filter_product_id'];
			} else {
				$search['value'] = '#' . $filter_data['filter_order_id'];
			}

			$clear_url = '';

			foreach ($filter_keys as $key) {
				if ($key !== $picked_key && isset($filter_data[$key])) {
					$clear_url .= '&' . $key . '=' . urlencode($filter_data[$key]);
				}
			}

			if ($overdue_active) {
				$clear_url .= '&filter_overdue=1';
			}

			$search['clear_url'] = $this->url->link('warehouse/supplier_orders', 'user_token=' . $this->session->data['user_token'] . $clear_url, true);
		}

		$data['user_filter'] = $this->renderUserFilter('warehouse_supplier_orders', 'warehouse/supplier_orders', [
			['key' => 'supplier', 'label' => $this->language->get('entry_supplier'), 'type' => 'select', 'options' => $supplier_options],
			['key' => 'status', 'label' => $this->language->get('entry_status'), 'type' => 'select', 'options' => $status_options],
			['key' => 'ordered', 'label' => $this->language->get('entry_ordered'), 'type' => 'select', 'options' => $ordered_options],
		], $tab_counts, '', $builtin_tabs, $search);

		// Apply saved filter conditions on top of the GET params.
		if ($active_filter) {
			foreach ($this->buildSupplierFilterData($active_filter['conditions']) as $key => $value) {
				$filter_data[$key] = $value;
			}
		}

		if ($overdue_active) {
			$filter_data['filter_overdue'] = 1;
		}

		// KPI summary row (global counts by status).
		$data['summary'] = $this->model_warehouse_supplier_orders->getSummaryCounts();

		$filter_data['start'] = ($page - 1) * (int)$this->config->get('config_limit_admin');
		$filter_data['limit'] = (int)$this->config->get('config_limit_admin');

		$total = $this->model_warehouse_supplier_orders->getTotalOrders($filter_data);
		$results = $this->model_warehouse_supplier_orders->getOrders($filter_data);

		$today = date('Y-m-d');

		foreach ($results as $result) {
			$deadline_days = null;

			if (!empty($result['deadline'])) {
				$deadline_days = (int)floor((strtotime($result['deadline']) - strtotime($today)) / 86400);
			}

			$overdue = false;

			if ($result['supplier_status'] !== 'shipped' && $deadline_days !== null && $deadline_days < 0) {
				$overdue = true;
			}

			$data['orders'][] = [
				'order_product_id' => $result['order_product_id'],
				'order_id' => $result['order_id'],
				'order_href' => $this->url->link('sale/order_detail', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . (int)$result['order_id'], true),
				'product_href' => $this->url->link('catalog/product/edit', 'user_token=' . $this->session->data['user_token'] . '&product_id=' . (int)$result['product_id'], true),
				'supplier_name' => $result['supplier_name'] ? $result['supplier_name'] : $result['warehouse_name'],
				'customer' => trim($result['customer_firstname'] . ' ' . $result['customer_lastname']),
				'name' => $result['name'],
				'model' => $result['model'],
				'variant_sku' => $result['variant_sku'],
				'quantity' => $result['quantity'],
				'supplier_status' => $result['supplier_status'],
				'supplier_status_text' => $this->language->get('text_line_' . $result['supplier_status']),
				'status_badge' => $this->getStatusBadgeClass((string)$result['supplier_status']),
				'ordered_date' => $result['supplier_ordered_date'],
				'deadline' => $result['deadline'],
				'deadline_days' => $deadline_days,
				'tracking' => $result['supplier_tracking'],
				'overdue' => $overdue,
			];
		}

		$url = '';

		foreach ($filter_keys as $key) {
			if (isset($filter_data[$key])) {
				$url .= '&' . $key . '=' . urlencode((string)$filter_data[$key]);
			}
		}

		if ($overdue_active) {
			$url .= '&filter_overdue=1';
		}

		if (isset($this->request->get['filter_id'])) {
			$url .= '&filter_id=' . (int)$this->request->get['filter_id'];
		}

		$data['export_url'] = $this->url->link('warehouse/supplier_orders/export', 'user_token=' . $this->session->data['user_token'] . $url, true);

		$pagination = new Pagination();
		$pagination->total = $total;
		$pagination->page = $page;
		$pagination->limit = (int)$this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('warehouse/supplier_orders', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();
		$data['results'] = $pagination->renderResults($this->language->get('text_pagination'));

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('warehouse/supplier_orders_list', $data));
	}

	/**
	 * Translates saved-filter conditions into model filter_* params.
	 */
	private function buildSupplierFilterData(array $conditions): array {
		$data = [];

		foreach ($conditions as $condition) {
			$field = (string)($condition['field'] ?? '');
			$value = $condition['value'] ?? '';

			if ((string)$value === '') {
				continue;
			}

			if ($field === 'supplier') {
				$data['filter_supplier_id'] = (int)$value;
			} elseif ($field === 'status') {
				$data['filter_status'] = (string)$value;
			} elseif ($field === 'ordered') {
				$data['filter_ordered'] = (string)$value;
			}
		}

		return $data;
	}

	private function getStatusBadgeClass(string $status): string {
		switch ($status) {
			case 'shipped':
				return 'page-header__badge page-header__badge--success';
			case 'ordered':
				return 'page-header__badge page-header__badge--info';
			default:
				return 'page-header__badge page-header__badge--pending';
		}
	}
}
