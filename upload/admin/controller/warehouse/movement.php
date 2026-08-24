<?php
/**
 * DockerCart Warehouse Movement admin controller: manual inbound/outbound/
 * inventory movements and the filtered journal with a per-position stock map.
 */

declare(strict_types=1);

class ControllerWarehouseMovement extends Controller {
	private $error = [];

	public function index() {
		$this->load->language('warehouse/movement');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('warehouse/movement');
		$this->getList();
	}

	/**
	 * POST: apply a manual movement (modal form on the list screen).
	 */
	public function add() {
		$this->load->language('warehouse/movement');
		$this->load->model('warehouse/movement');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateAdd()) {
			$this->model_warehouse_movement->addMovement($this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('warehouse/movement', 'user_token=' . $this->session->data['user_token'] . $this->buildFilterUrl(), true));
		}

		$this->getList();
	}

	/**
	 * Query string with the active journal filters (+page, saved filter),
	 * used to keep the view context across the add-movement POST and redirect.
	 */
	private function buildFilterUrl(): string {
		$url = '';

		foreach (['filter_warehouse_id', 'filter_product_id', 'filter_type', 'filter_order_id', 'filter_date_from', 'filter_date_to', 'filter_id', 'page'] as $key) {
			if (isset($this->request->get[$key]) && (string)$this->request->get[$key] !== '') {
				$url .= '&' . $key . '=' . urlencode((string)$this->request->get[$key]);
			}
		}

		return $url;
	}

	/**
	 * Search-as-you-type for the journal toolbar (product pick → filter).
	 */
	public function autocomplete() {
		$json = [];

		$this->load->language('warehouse/movement');

		if ($this->user->hasPermission('access', 'warehouse/movement')) {
			$filter_search = trim((string)($this->request->get['filter_search'] ?? ''));

			if ($filter_search !== '') {
				$this->load->model('warehouse/stock');

				foreach ($this->model_warehouse_stock->autocompleteProducts($filter_search, 8) as $result) {
					$json[] = [
						'id' => $result['product_id'],
						'name' => $result['name'],
						'subtitle' => $result['model'],
						'href' => $this->url->link('warehouse/movement', 'user_token=' . $this->session->data['user_token'] . '&filter_product_id=' . (int)$result['product_id'], true),
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
	 * Search-as-you-type for the modal position picker: simple products as
	 * single rows, configurable products one row per active variant.
	 */
	public function positionAutocomplete() {
		$json = [];

		$this->load->language('warehouse/movement');

		if ($this->user->hasPermission('access', 'warehouse/movement')) {
			$filter_search = trim((string)($this->request->get['filter_search'] ?? ''));

			if ($filter_search !== '') {
				$this->load->model('warehouse/stock');

				foreach ($this->model_warehouse_stock->autocompletePositions($filter_search, 10) as $result) {
					$label = $result['name'] . ' (' . $result['model'];

					if (!empty($result['variant_id'])) {
						$label .= ' / ' . $result['sku'];

						if (!empty($result['option_names'])) {
							$label .= ' · ' . $result['option_names'];
						}
					}

					$label .= ')';

					$json[] = [
						'value' => (int)$result['product_id'],
						'variant_id' => (int)$result['variant_id'],
						'label' => $label,
					];
				}
			}
		} else {
			$json['error'] = $this->language->get('error_permission');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function stockMap() {
		$this->load->language('warehouse/movement');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['lines'] = [];
		$data['final_balance'] = '0';
		$data['warehouse_name'] = '';
		$data['position_product'] = '';
		$data['position_model'] = '';
		$data['position_variant_sku'] = '';
		$data['position_product_id'] = 0;
		$data['summary'] = $this->buildSummary(0.0, 0.0, 0);

		if (isset($this->request->get['product_id'])) {
			$this->load->model('warehouse/movement');

			$product_id = (int)$this->request->get['product_id'];
			$variant_id = (int)($this->request->get['variant_id'] ?? 0);
			$warehouse_id = (int)($this->request->get['warehouse_id'] ?? 0);

			$data['position_product_id'] = $product_id;

			$lines = $this->model_warehouse_movement->getStockMap([
				'product_id' => $product_id,
				'variant_id' => $variant_id,
				'warehouse_id' => $warehouse_id,
			]);

			$inbound = 0.0;
			$outbound = 0.0;

			foreach ($lines as $line) {
				$quantity = (float)$line['quantity'];

				if ($quantity > 0) {
					$inbound += $quantity;
				} elseif ($quantity < 0) {
					$outbound -= $quantity;
				}

				$data['lines'][] = [
					'date' => date($this->language->get('datetime_format'), strtotime($line['date_added'])),
					'warehouse' => $line['warehouse_name'] ?? '',
					'type_key' => $line['type'],
					'type' => $this->language->get('text_type_' . $line['type']),
					'quantity' => $this->formatQuantity((string)$line['quantity']),
					'reference' => $line['reference'],
					'comment' => $line['comment'],
					'balance' => $this->formatQuantity((string)$line['balance']),
				];
			}

			if ($data['lines']) {
				$last = end($data['lines']);
				$data['final_balance'] = $last['balance'];
				$data['warehouse_name'] = $warehouse_id ? (string)$last['warehouse'] : '';
			}

			$data['summary'] = $this->buildSummary($inbound, $outbound, count($data['lines']));

			// Position identity for the context panel.
			$this->load->model('warehouse/stock');

			$product = $this->model_warehouse_stock->getStockProduct($product_id);

			if ($product) {
				$data['position_product'] = (string)$product['name'];
			}

			$data['position_model'] = (string)($lines[0]['product_model'] ?? '');
			$data['position_variant_sku'] = ($variant_id && !empty($lines[0]['variant_sku'])) ? (string)$lines[0]['variant_sku'] : '';

			$this->document->setTitle($this->language->get('heading_title') . ' — ' . ($data['position_product'] ?: ('#' . $product_id)));
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

		$data['add'] = $this->url->link('warehouse/movement/add', 'user_token=' . $this->session->data['user_token'] . $this->buildFilterUrl(), true);
		$data['user_token'] = $this->session->data['user_token'];
		$data['stock_map_url'] = $this->url->link('warehouse/movement/stockMap', 'user_token=' . $this->session->data['user_token'], true);
		$data['order_info_url'] = $this->url->link('sale/order/info', 'user_token=' . $this->session->data['user_token'], true);
		$data['success'] = $this->session->data['success'] ?? '';
		unset($this->session->data['success']);
		$data['error_warning'] = $this->error['warning'] ?? '';
		$data['error_warehouse'] = $this->error['warehouse'] ?? '';
		$data['error_product'] = $this->error['product'] ?? '';
		$data['error_quantity'] = $this->error['quantity'] ?? '';

		$this->load->model('warehouse/warehouse');
		$warehouses = $this->model_warehouse_warehouse->getWarehouses(['sort' => 'priority', 'order' => 'DESC', 'limit' => 1000]);

		$this->load->model('warehouse/stock');

		$data['warehouses'] = $warehouses;

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

		// Per-admin saved filters (Shopify-style tabs).
		$active_filter = $this->getActiveUserFilter('warehouse_movement');

		$this->load->model('user/user_filter');

		$user_id = (int)$this->user->getId();
		$saved_filters = $this->model_user_user_filter->getFilters($user_id, 'warehouse_movement');

		$tab_counts = [
			'all' => $this->model_warehouse_movement->getTotalMovements([]),
		];

		foreach ($saved_filters as $saved) {
			$tab_counts['custom_' . $saved['filter_id']] = $this->model_warehouse_movement->getTotalMovements($this->buildMovementFilterData($saved['conditions']));
		}

		$warehouse_options = [
			['value' => '0', 'label' => $this->language->get('text_all')],
		];

		foreach ($warehouses as $warehouse) {
			$warehouse_options[] = ['value' => (string)$warehouse['warehouse_id'], 'label' => $warehouse['name']];
		}

		$type_options = [];

		foreach ($data['types'] as $key => $label) {
			$type_options[] = ['value' => $key, 'label' => $label];
		}

		$search = [
			'placeholder' => $this->language->get('text_search_placeholder'),
			'url' => $this->url->link('warehouse/movement/autocomplete', 'user_token=' . $this->session->data['user_token'], true),
		];

		// Active product filter (from a search pick): restore the query in the
		// search box and provide a one-click reset.
		if (isset($filter_data['filter_product_id'])) {
			$product = $this->model_warehouse_stock->getStockProduct((int)$filter_data['filter_product_id']);

			$clear_url = '';

			foreach ($filters as $key) {
				if ($key !== 'filter_product_id' && isset($filter_data[$key])) {
					$clear_url .= '&' . $key . '=' . urlencode((string)$filter_data[$key]);
				}
			}

			$search['value'] = $product ? (string)$product['name'] : '#' . $filter_data['filter_product_id'];
			$search['clear_url'] = $this->url->link('warehouse/movement', 'user_token=' . $this->session->data['user_token'] . $clear_url, true);
		}

		$data['user_filter'] = $this->renderUserFilter('warehouse_movement', 'warehouse/movement', [
			['key' => 'warehouse', 'label' => $this->language->get('entry_warehouse'), 'type' => 'select', 'options' => $warehouse_options],
			['key' => 'type', 'label' => $this->language->get('entry_type'), 'type' => 'select', 'options' => $type_options],
			['key' => 'order_id', 'label' => $this->language->get('entry_order_id'), 'type' => 'number'],
			['key' => 'date_from', 'label' => $this->language->get('entry_date_from'), 'type' => 'date'],
			['key' => 'date_to', 'label' => $this->language->get('entry_date_to'), 'type' => 'date'],
		], $tab_counts, '', [], $search);

		if ($active_filter) {
			foreach ($this->buildMovementFilterData($active_filter['conditions']) as $key => $value) {
				$filter_data[$key] = $value;
			}
		}

		$summary = $this->model_warehouse_movement->getMovementSummary($filter_data);

		$data['summary'] = $this->buildSummary(
			(float)$summary['inbound'],
			(float)$summary['outbound'],
			(int)$summary['total']
		);

		$filter_data['start'] = ($page - 1) * $this->config->get('config_limit_admin');
		$filter_data['limit'] = $this->config->get('config_limit_admin');

		$total = $this->model_warehouse_movement->getTotalMovements($filter_data);
		$results = $this->model_warehouse_movement->getMovements($filter_data);

		$data['movements'] = [];

		foreach ($results as $result) {
			$data['movements'][] = [
				'movement_id' => $result['movement_id'],
				'date' => date($this->language->get('datetime_format'), strtotime($result['date_added'])),
				'warehouse' => $result['warehouse_name'] ?? '',
				'warehouse_id' => $result['warehouse_id'],
				'product_id' => $result['product_id'],
				'variant_id' => $result['variant_id'],
				'product_name' => $result['product_name'] ?? '',
				'product_model' => $result['product_model'],
				'variant_sku' => $result['variant_sku'],
				'type_key' => $result['type'],
				'type' => $this->language->get('text_type_' . $result['type']),
				'quantity' => $result['quantity'],
				'reference' => $result['reference'],
				'order_id' => $result['order_id'],
				'comment' => $result['comment'],
				'username' => $result['username'] ?? '',
				'user_id' => $result['user_id'],
			];
		}

		$data['filter'] = array_intersect_key($filter_data, array_flip($filters));

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

	/**
	 * Builds the KPI summary payload from inbound/outbound unit totals
	 * (net is stored unsigned; the sign travels in net_sign).
	 */
	private function buildSummary(float $inbound, float $outbound, int $total): array {
		$inbound = round($inbound, 4);
		$outbound = round($outbound, 4);
		$net = round($inbound - $outbound, 4);

		return [
			'inbound' => $this->formatQuantity(number_format($inbound, 4, '.', '')),
			'outbound' => $this->formatQuantity(number_format($outbound, 4, '.', '')),
			'net' => $this->formatQuantity(number_format(abs($net), 4, '.', '')),
			'net_sign' => $net > 0 ? '+' : ($net < 0 ? '−' : ''),
			'net_positive' => $net > 0,
			'net_negative' => $net < 0,
			'total' => $total,
		];
	}

	/**
	 * Strips trailing zeros from a DECIMAL string ('5.0000' → '5', '2.5000' → '2.5').
	 */
	private function formatQuantity(string $decimal): string {
		$value = rtrim(rtrim($decimal, '0'), '.');

		return ($value === '' || $value === '-' || $value === '-0') ? '0' : $value;
	}

	/**
	 * Translates saved-filter conditions into model filter_* params.
	 */
	private function buildMovementFilterData(array $conditions): array {
		$data = [];

		foreach ($conditions as $condition) {
			$field = (string)($condition['field'] ?? '');
			$value = $condition['value'] ?? '';

			switch ($field) {
				case 'warehouse':
					if ((string)$value !== '' && (int)$value > 0) {
						$data['filter_warehouse_id'] = (string)(int)$value;
					}
					break;

				case 'type':
					if ((string)$value !== '') {
						$data['filter_type'] = (string)$value;
					}
					break;

				case 'order_id':
					if ((string)$value !== '') {
						$data['filter_order_id'] = (string)(int)$value;
					}
					break;

				case 'date_from':
					if ((string)$value !== '') {
						$data['filter_date_from'] = (string)$value;
					}
					break;

				case 'date_to':
					if ((string)$value !== '') {
						$data['filter_date_to'] = (string)$value;
					}
					break;
			}
		}

		return $data;
	}

	protected function validateAdd() {
		if (!$this->user->hasPermission('modify', 'warehouse/movement')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if ((int)($this->request->post['warehouse_id'] ?? 0) <= 0) {
			$this->error['warehouse'] = $this->language->get('error_warehouse');
		}

		$product_id = (int)($this->request->post['product_id'] ?? 0);

		if ($product_id <= 0) {
			$this->error['product'] = $this->language->get('error_product');
		} else {
			$this->load->model('warehouse/stock');

			if (!$this->model_warehouse_stock->getStockProduct($product_id)) {
				$this->error['product'] = $this->language->get('error_product');
			}
		}

		if (!isset($this->request->post['quantity']) || (float)$this->request->post['quantity'] <= 0) {
			$this->error['quantity'] = $this->language->get('error_quantity');
		}

		return !$this->error;
	}
}
