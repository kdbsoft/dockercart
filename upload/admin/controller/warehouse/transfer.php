<?php
/**
 * DockerCart Warehouse Transfer admin controller: transfers between warehouses.
 */

declare(strict_types=1);

class ControllerWarehouseTransfer extends Controller {
	private $error = [];

	public function index() {
		$this->load->language('warehouse/transfer');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('warehouse/transfer');
		$this->getList();
	}

	/**
	 * POST: create a transfer (modal form on the list screen).
	 */
	public function add() {
		$this->load->language('warehouse/transfer');
		$this->load->model('warehouse/transfer');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_warehouse_transfer->addTransfer($this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('warehouse/transfer', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->getList();
	}

	public function delete() {
		$this->load->language('warehouse/transfer');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('warehouse/transfer');

		if (isset($this->request->post['selected']) && $this->validateDelete()) {
			foreach ($this->request->post['selected'] as $transfer_id) {
				$this->model_warehouse_transfer->deleteTransfer((int)$transfer_id);
			}
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('warehouse/transfer', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->getList();
	}

	/**
	 * Search-as-you-type for transfer items ("product + variant"): simple
	 * products as single rows, configurable products per active variant.
	 */
	public function positionAutocomplete() {
		$json = [];

		$this->load->language('warehouse/transfer');

		if ($this->user->hasPermission('access', 'warehouse/transfer')) {
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

	/**
	 * AJAX: read-only transfer details for the list-screen modal.
	 */
	public function info() {
		$this->load->language('warehouse/transfer');
		$this->load->model('warehouse/transfer');

		if (!$this->user->hasPermission('access', 'warehouse/transfer')) {
			$this->response->setOutput('<div class="alert alert-danger" style="margin:0;"><i data-lucide="circle-alert" width="16" height="16"></i> ' . $this->language->get('error_permission') . '</div>');

			return;
		}

		$transfer_id = (int)($this->request->get['transfer_id'] ?? 0);
		$transfer_info = $transfer_id > 0 ? $this->model_warehouse_transfer->getTransfer($transfer_id) : [];

		if (!$transfer_info) {
			$this->response->setOutput('<div class="alert alert-warning" style="margin:0;"><i data-lucide="circle-alert" width="16" height="16"></i> ' . $this->language->get('text_no_results') . '</div>');

			return;
		}

		$this->load->model('warehouse/warehouse');

		$from = $this->model_warehouse_warehouse->getWarehouse((int)$transfer_info['from_warehouse_id']);
		$to = $this->model_warehouse_warehouse->getWarehouse((int)$transfer_info['to_warehouse_id']);

		$data['transfer_no'] = $transfer_info['transfer_no'];
		$data['status_key'] = $transfer_info['status'];
		$data['status_label'] = $this->language->get('text_status_' . $transfer_info['status']);
		$data['status_badge'] = $this->getStatusBadgeClass($transfer_info['status']);
		$data['from'] = $from['name'] ?? '—';
		$data['to'] = $to['name'] ?? '—';
		$data['note'] = $transfer_info['note'];
		$data['date_added'] = $transfer_info['date_added'];

		$creator = '';

		if ((int)$transfer_info['created_by'] > 0) {
			$this->load->model('user/user');

			$user_info = $this->model_user_user->getUser((int)$transfer_info['created_by']);

			$creator = $user_info ? $user_info['username'] : '';
		}

		$data['creator'] = $creator;

		$data['items'] = [];

		foreach ($this->model_warehouse_transfer->getTransferItems($transfer_id) as $item) {
			$variant_sku = $item['variant_sku'] ? ' / ' . $item['variant_sku'] : '';

			$data['items'][] = [
				'product_name' => $item['product_name'] ?: ('#' . $item['product_id']),
				'model' => trim($item['product_model'] . $variant_sku),
				'quantity' => $this->formatQuantity((string)$item['quantity']),
			];
		}

		$this->response->setOutput($this->load->view('warehouse/transfer_info', $data));
	}

	/**
	 * POST: quick status advance from the list row ("In transit" / "Complete" /
	 * "Cancel"). Delegates all transition guards to updateStatus().
	 */
	public function quickStatus() {
		$this->load->language('warehouse/transfer');

		$json = ['success' => false];

		if ($this->user->hasPermission('modify', 'warehouse/transfer')) {
			$transfer_id = (int)($this->request->post['transfer_id'] ?? 0);
			$status = (string)($this->request->post['status'] ?? '');

			if ($transfer_id > 0 && in_array($status, ['pending', 'in_transit', 'completed', 'cancelled'], true)) {
				$this->load->model('warehouse/transfer');

				$this->model_warehouse_transfer->updateStatus($transfer_id, $status);

				$json['success'] = true;
			} else {
				$json['error'] = $this->language->get('error_warning');
			}
		} else {
			$json['error'] = $this->language->get('error_permission');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	protected function getList() {
		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$filter_status = isset($this->request->get['filter_status']) ? (string)$this->request->get['filter_status'] : '';

		$data['breadcrumbs'] = [];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('warehouse/transfer', 'user_token=' . $this->session->data['user_token'], true),
		];

		$data['add'] = $this->url->link('warehouse/transfer/add', 'user_token=' . $this->session->data['user_token'], true);
		$data['delete'] = $this->url->link('warehouse/transfer/delete', 'user_token=' . $this->session->data['user_token'], true);
		$data['quick_status'] = $this->url->link('warehouse/transfer/quickStatus', 'user_token=' . $this->session->data['user_token'], true);
		$data['transfer_url'] = $this->url->link('warehouse/transfer/positionAutocomplete', 'user_token=' . $this->session->data['user_token'], true);
		$data['info_url'] = $this->url->link('warehouse/transfer/info', 'user_token=' . $this->session->data['user_token'], true);
		$data['user_token'] = $this->session->data['user_token'];
		$data['success'] = $this->session->data['success'] ?? '';
		unset($this->session->data['success']);
		$data['error_warning'] = $this->error['warning'] ?? '';
		$data['selected'] = isset($this->request->post['selected']) ? (array)$this->request->post['selected'] : [];

		$this->load->model('warehouse/warehouse');
		$data['warehouses'] = $this->model_warehouse_warehouse->getWarehouses(['sort' => 'priority', 'order' => 'DESC', 'limit' => 1000]);

		$status_keys = ['pending', 'in_transit', 'completed', 'cancelled'];

		// Per-admin saved filters (Shopify-style tabs).
		$active_filter = $this->getActiveUserFilter('warehouse_transfer');

		$this->load->model('user/user_filter');

		$user_id = (int)$this->user->getId();
		$saved_filters = $this->model_user_user_filter->getFilters($user_id, 'warehouse_transfer');

		$tab_counts = [
			'all' => $this->model_warehouse_transfer->getTotalTransfers([]),
		];

		foreach ($status_keys as $status_key) {
			$tab_counts[$status_key] = $this->model_warehouse_transfer->getTotalTransfers(['filter_status' => $status_key]);
		}

		foreach ($saved_filters as $saved) {
			$tab_counts['custom_' . $saved['filter_id']] = $this->model_warehouse_transfer->getTotalTransfers($this->buildTransferFilterData($saved['conditions']));
		}

		// Builtin status tabs on top of the "All" tab.
		$builtin_tabs = [];

		foreach ($status_keys as $status_key) {
			$builtin_tabs[] = [
				'id' => $status_key,
				'name' => $this->language->get('text_status_' . $status_key),
				'href' => $this->url->link('warehouse/transfer', 'user_token=' . $this->session->data['user_token'] . '&filter_status=' . $status_key, true),
				'count' => $tab_counts[$status_key],
				'is_active' => $filter_status === $status_key,
			];
		}

		$status_options = [];

		foreach ($status_keys as $status_key) {
			$status_options[] = ['value' => $status_key, 'label' => $this->language->get('text_status_' . $status_key)];
		}

		$data['user_filter'] = $this->renderUserFilter('warehouse_transfer', 'warehouse/transfer', [
			['key' => 'status', 'label' => $this->language->get('entry_status'), 'type' => 'select', 'options' => $status_options],
		], $tab_counts, '', $builtin_tabs);

		// KPI summary row (counts by status).
		$data['summary'] = [
			'pending' => $tab_counts['pending'],
			'in_transit' => $tab_counts['in_transit'],
			'completed' => $tab_counts['completed'],
			'cancelled' => $tab_counts['cancelled'],
		];

		$filter_data = [
			'filter_status' => $filter_status,
			'start' => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' => $this->config->get('config_limit_admin'),
		];

		if ($active_filter) {
			foreach ($this->buildTransferFilterData($active_filter['conditions']) as $key => $value) {
				$filter_data[$key] = $value;
			}
		}

		$total = $this->model_warehouse_transfer->getTotalTransfers($filter_data);
		$results = $this->model_warehouse_transfer->getTransfers($filter_data);

		foreach ($results as $result) {
			$data['transfers'][] = [
				'transfer_id' => $result['transfer_id'],
				'transfer_no' => $result['transfer_no'],
				'from' => $result['from_name'],
				'to' => $result['to_name'],
				'status_key' => $result['status'],
				'status' => $this->language->get('text_status_' . $result['status']),
				'status_badge' => $this->getStatusBadgeClass($result['status']),
				'items_count' => (int)$result['items_count'],
				'total_quantity' => $this->formatQuantity((string)$result['total_quantity']),
				'creator' => $result['creator'] ?? '',
				'date' => $result['date_added'],
			];
		}

		$url = $filter_data['filter_status'] !== '' ? '&filter_status=' . urlencode($filter_data['filter_status']) : '';

		$pagination = new Pagination();
		$pagination->total = $total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('warehouse/transfer', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();
		$data['results'] = $pagination->renderResults($this->language->get('text_pagination'));

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('warehouse/transfer_list', $data));
	}

	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'warehouse/transfer')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if ((int)($this->request->post['from_warehouse_id'] ?? 0) <= 0 || (int)($this->request->post['to_warehouse_id'] ?? 0) <= 0) {
			$this->error['warning'] = $this->language->get('error_warehouse_required');
		}

		if ((int)($this->request->post['from_warehouse_id'] ?? 0) === (int)($this->request->post['to_warehouse_id'] ?? 0)) {
			$this->error['warning'] = $this->language->get('error_same_warehouse');
		}

		return !$this->error;
	}

	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'warehouse/transfer')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	/**
	 * Translates saved-filter conditions into model filter_* params.
	 */
	private function buildTransferFilterData(array $conditions): array {
		$data = [];

		foreach ($conditions as $condition) {
			$field = (string)($condition['field'] ?? '');
			$value = $condition['value'] ?? '';

			if ($field === 'status' && (string)$value !== '') {
				$data['filter_status'] = (string)$value;
			}
		}

		return $data;
	}

	private function getStatusBadgeClass(string $status): string {
		switch ($status) {
			case 'pending':
				return 'page-header__badge page-header__badge--warning page-header__badge--unfilled';
			case 'in_transit':
				return 'page-header__badge page-header__badge--info';
			case 'completed':
				return 'page-header__badge page-header__badge--success';
			default:
				return 'page-header__badge page-header__badge--danger';
		}
	}

	/**
	 * Strips trailing zeros from a DECIMAL string ('5.0000' → '5', '2.5000' → '2.5').
	 */
	private function formatQuantity(string $decimal): string {
		$value = rtrim(rtrim($decimal, '0'), '.');

		return ($value === '' || $value === '-') ? '0' : $value;
	}
}