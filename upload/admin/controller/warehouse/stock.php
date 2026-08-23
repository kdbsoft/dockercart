<?php
/**
 * DockerCart Warehouse Stock admin controller: the product x warehouse matrix
 * with AJAX cell editing and a "recalculate totals" drift report.
 */

declare(strict_types=1);

class ControllerWarehouseStock extends Controller {
	public function index() {
		$this->load->language('warehouse/stock');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('warehouse/stock');
		$this->getList();
	}

	/**
	 * AJAX: update one matrix cell (quantity / unlimited / lead_time).
	 */
	public function updateCell() {
		$this->load->language('warehouse/stock');
		$this->load->model('warehouse/stock');

		$json = ['success' => false];

		if ($this->user->hasPermission('modify', 'warehouse/stock')) {
			$input = $this->request->post;
			$stock_id = (int)($input['stock_id'] ?? 0);
			$mode = (string)($input['mode'] ?? 'quantity');

			// Read the current row so only the edited field changes.
			$current = $this->db->query("SELECT quantity, unlimited, lead_time FROM `" . DB_PREFIX . "warehouse_stock` WHERE `stock_id` = '" . (int)$stock_id . "'");

			if ($current->num_rows) {
				$quantity = (float)$current->row['quantity'];
				$unlimited = (bool)$current->row['unlimited'];
				$lead_time = (int)$current->row['lead_time'];

				if ($mode === 'unlimited') {
					$unlimited = (bool)(int)($input['value'] ?? 0);
				} elseif ($mode === 'lead_time') {
					$lead_time = (int)($input['value'] ?? 0);
				} else {
					$quantity = (float)($input['value'] ?? 0);
				}

				$this->model_warehouse_stock->setCell($stock_id, $quantity, $unlimited, $lead_time);
			}

			$json['success'] = true;
		} else {
			$json['error'] = $this->language->get('error_permission');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * AJAX: add a missing product row to a warehouse (from the product picker).
	 */
	public function addProduct() {
		$this->load->language('warehouse/stock');
		$this->load->model('warehouse/stock');

		$json = ['success' => false];

		if ($this->user->hasPermission('modify', 'warehouse/stock')) {
			$input = $this->request->post;

			$stock_id = $this->model_warehouse_stock->ensureRow(
				(int)($input['warehouse_id'] ?? 0),
				(int)($input['product_id'] ?? 0),
				(int)($input['variant_id'] ?? 0)
			);

			$json['stock_id'] = $stock_id;
			$json['success'] = true;
		} else {
			$json['error'] = $this->language->get('error_permission');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * AJAX: recompute all denormalised caches and report drift.
	 */
	public function recalculate() {
		$this->load->language('warehouse/stock');
		$this->load->model('warehouse/stock');

		$json = ['success' => false];

		if ($this->user->hasPermission('modify', 'warehouse/stock')) {
			$result = $this->model_warehouse_stock->recalculate();
			$json = [
				'success' => true,
				'total' => $result['total'],
				'drifted' => $result['drifted'],
				'totals_message' => sprintf($this->language->get('text_recalculated'), $result['total'], $result['drifted']),
			];
		} else {
			$json['error'] = $this->language->get('error_permission');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * AJAX: search-as-you-type for the toolbar (filters the matrix on pick).
	 */
	public function autocomplete() {
		$this->load->language('warehouse/stock');
		$this->load->model('warehouse/stock');

		$json = [];

		if ($this->user->hasPermission('access', 'warehouse/stock')) {
			$filter_search = trim((string)($this->request->get['filter_search'] ?? ''));

			if ($filter_search !== '') {
				foreach ($this->model_warehouse_stock->autocompleteProducts($filter_search, 8) as $result) {
					$json[] = [
						'id' => $result['product_id'],
						'name' => $result['name'],
						'subtitle' => $result['model'],
						'href' => $this->url->link('warehouse/stock', 'user_token=' . $this->session->data['user_token'] . '&filter_product_id=' . (int)$result['product_id'], true),
					];
				}
			}
		} else {
			$json['error'] = $this->language->get('error_permission');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	protected function getList() {
		$page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;

		// URL filters (also produced by saved-filter tab conditions).
		$filter_keys = ['filter_warehouse_id', 'filter_product_id', 'filter_name', 'filter_model', 'filter_sku', 'filter_quantity_min', 'filter_quantity_max', 'filter_unlimited'];
		$filters = [];

		foreach ($filter_keys as $key) {
			$filters[$key] = isset($this->request->get[$key]) ? (string)$this->request->get[$key] : '';
		}

		$data['breadcrumbs'] = [];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('warehouse/stock', 'user_token=' . $this->session->data['user_token'], true),
		];

		$data['recalculate'] = $this->url->link('warehouse/stock/recalculate', 'user_token=' . $this->session->data['user_token'], true);
		$data['user_token'] = $this->session->data['user_token'];
		$data['product_edit_url'] = $this->url->link('catalog/product/edit', 'user_token=' . $this->session->data['user_token'], true);
		$data['warehouse_edit_url'] = $this->url->link('warehouse/warehouse/edit', 'user_token=' . $this->session->data['user_token'], true);

		$this->load->model('warehouse/warehouse');
		$warehouses = $this->model_warehouse_warehouse->getWarehouses(['sort' => 'priority', 'order' => 'DESC', 'limit' => 1000]);

		// Per-admin saved filters (Shopify-style tabs).
		$active_filter = $this->getActiveUserFilter('warehouse_stock');

		$this->load->model('user/user_filter');

		$user_id = (int)$this->user->getId();
		$saved_filters = $this->model_user_user_filter->getFilters($user_id, 'warehouse_stock');

		$tab_counts = [
			'all' => $this->model_warehouse_stock->getTotalStock([]),
		];

		foreach ($saved_filters as $saved) {
			$tab_counts['custom_' . $saved['filter_id']] = $this->model_warehouse_stock->getTotalStock($this->buildStockFilterData($saved['conditions']));
		}

		$warehouse_options = [
			['value' => '0', 'label' => $this->language->get('text_all')],
		];

		foreach ($warehouses as $warehouse) {
			$warehouse_options[] = ['value' => (string)$warehouse['warehouse_id'], 'label' => $warehouse['name']];
		}

		$unlimited_options = [
			['value' => '1', 'label' => $this->language->get('text_yes')],
			['value' => '0', 'label' => $this->language->get('text_no')],
		];

		$search = [
			'placeholder' => $this->language->get('text_search_placeholder'),
			'url' => $this->url->link('warehouse/stock/autocomplete', 'user_token=' . $this->session->data['user_token'], true),
		];

		// Active product filter (from a search pick): restore the query in the
		// search box and provide a one-click reset.
		if ($filters['filter_product_id'] !== '') {
			$product = $this->model_warehouse_stock->getStockProduct((int)$filters['filter_product_id']);

			$clear_url = '';

			foreach ($filter_keys as $key) {
				if ($key !== 'filter_product_id' && $filters[$key] !== '') {
					$clear_url .= '&' . $key . '=' . urlencode(html_entity_decode($filters[$key], ENT_QUOTES, 'UTF-8'));
				}
			}

			$search['value'] = $product ? (string)$product['name'] : '#' . $filters['filter_product_id'];
			$search['clear_url'] = $this->url->link('warehouse/stock', 'user_token=' . $this->session->data['user_token'] . $clear_url, true);
		}

		$data['user_filter'] = $this->renderUserFilter('warehouse_stock', 'warehouse/stock', [
			['key' => 'warehouse', 'label' => $this->language->get('entry_warehouse'), 'type' => 'select', 'options' => $warehouse_options],
			['key' => 'name', 'label' => $this->language->get('entry_product'), 'type' => 'text'],
			['key' => 'model', 'label' => $this->language->get('entry_model'), 'type' => 'text'],
			['key' => 'sku', 'label' => $this->language->get('entry_sku'), 'type' => 'text'],
			['key' => 'quantity', 'label' => $this->language->get('entry_quantity'), 'type' => 'number'],
			['key' => 'unlimited', 'label' => $this->language->get('entry_unlimited'), 'type' => 'select', 'options' => $unlimited_options],
		], $tab_counts, '', [], $search);

		$filter_data = array_merge($filters, [
			'start' => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' => $this->config->get('config_limit_admin'),
		]);

		if ($active_filter) {
			foreach ($this->buildStockFilterData($active_filter['conditions']) as $key => $value) {
				$filter_data[$key] = $value;
			}
		}

		$stock_total = $this->model_warehouse_stock->getTotalStock($filter_data);
		$results = $this->model_warehouse_stock->getStockMatrix($filter_data);

		foreach ($results as $result) {
			$data['rows'][] = [
				'stock_id' => $result['stock_id'],
				'warehouse_id' => $result['warehouse_id'],
				'warehouse_name' => $result['warehouse_name'],
				'product_id' => $result['product_id'],
				'product_name' => $result['product_name'],
				'product_model' => $result['product_model'],
				'variant_id' => $result['variant_id'],
				'variant_sku' => $result['variant_sku'],
				'quantity' => $result['quantity'],
				'unlimited' => $result['unlimited'],
				'lead_time' => $result['lead_time'],
			];
		}

		$url = '';

		foreach ($filter_keys as $key) {
			if ($filters[$key] !== '') {
				$url .= '&' . $key . '=' . urlencode(html_entity_decode($filters[$key], ENT_QUOTES, 'UTF-8'));
			}
		}

		$pagination = new Pagination();
		$pagination->total = $stock_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('warehouse/stock', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();
		$data['results'] = $pagination->renderResults($this->language->get('text_pagination'));

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('warehouse/stock_list', $data));
	}

	/**
	 * Maps saved-filter conditions onto the filter_* keys used by the model.
	 *
	 * Mirrors buildProductFilterData() in catalog/product and buildFilterData()
	 * in sale/order: number fields may carry value_min/value_max ranges or a
	 * single value with an eq/gt/gte/lt/lte operator.
	 */
	private function buildStockFilterData(array $conditions): array {
		$data = [];

		foreach ($conditions as $condition) {
			$field = (string)($condition['field'] ?? '');
			$operator = (string)($condition['operator'] ?? 'eq');
			$value = $condition['value'] ?? '';

			switch ($field) {
				case 'warehouse':
					if ((string)$value !== '') {
						$data['filter_warehouse_id'] = (string)(int)$value;
					}
					break;

				case 'name':
					$data['filter_name'] = (string)$value;
					break;

				case 'model':
					$data['filter_model'] = (string)$value;
					break;

				case 'sku':
					$data['filter_sku'] = (string)$value;
					break;

				case 'quantity':
					if (isset($condition['value_min']) || isset($condition['value_max'])) {
						if (isset($condition['value_min']) && $condition['value_min'] !== '') {
							$data['filter_quantity_min'] = (string)$condition['value_min'];
						}

						if (isset($condition['value_max']) && $condition['value_max'] !== '') {
							$data['filter_quantity_max'] = (string)$condition['value_max'];
						}
					} elseif ((string)$value !== '') {
						if ($operator === 'gt' || $operator === 'gte') {
							$data['filter_quantity_min'] = (string)$value;
						} elseif ($operator === 'lt' || $operator === 'lte') {
							$data['filter_quantity_max'] = (string)$value;
						} else {
							$data['filter_quantity_min'] = (string)$value;
							$data['filter_quantity_max'] = (string)$value;
						}
					}
					break;

				case 'unlimited':
					if ((string)$value !== '') {
						$data['filter_unlimited'] = (string)(int)$value;
					}
					break;
			}
		}

		return $data;
	}
}
