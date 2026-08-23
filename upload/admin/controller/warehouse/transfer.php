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

	public function add() {
		$this->load->language('warehouse/transfer');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('warehouse/transfer');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_warehouse_transfer->addTransfer($this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('warehouse/transfer', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->getForm();
	}

	public function edit() {
		$this->load->language('warehouse/transfer');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('warehouse/transfer');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateStatus()) {
			// Editing a transfer only advances its status; items are immutable.
			$this->model_warehouse_transfer->updateStatus((int)$this->request->get['transfer_id'], (string)($this->request->post['status'] ?? 'pending'));
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('warehouse/transfer', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->getForm();
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

	protected function getList() {
		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		if (isset($this->request->get['filter_status'])) {
			$filter_status = $this->request->get['filter_status'];
		} else {
			$filter_status = '';
		}

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
		$data['user_token'] = $this->session->data['user_token'];
		$data['success'] = $this->session->data['success'] ?? '';
		unset($this->session->data['success']);
		$data['error_warning'] = $this->error['warning'] ?? '';
		$data['selected'] = isset($this->request->post['selected']) ? (array)$this->request->post['selected'] : [];

		$filter_data = [
			'filter_status' => $filter_status,
			'start' => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' => $this->config->get('config_limit_admin'),
		];

		$total = $this->model_warehouse_transfer->getTotalTransfers($filter_data);
		$results = $this->model_warehouse_transfer->getTransfers($filter_data);

		foreach ($results as $result) {
			$data['transfers'][] = [
				'transfer_id' => $result['transfer_id'],
				'transfer_no' => $result['transfer_no'],
				'from' => $result['from_name'],
				'to' => $result['to_name'],
				'status' => $this->language->get('text_status_' . $result['status']),
				'date' => $result['date_added'],
				'edit' => $this->url->link('warehouse/transfer/edit', 'user_token=' . $this->session->data['user_token'] . '&transfer_id=' . $result['transfer_id'], true),
			];
		}

		$data['status_options'] = [
			'' => $this->language->get('text_all_status'),
			'pending' => $this->language->get('text_status_pending'),
			'in_transit' => $this->language->get('text_status_in_transit'),
			'completed' => $this->language->get('text_status_completed'),
			'cancelled' => $this->language->get('text_status_cancelled'),
		];
		$data['filter_status'] = $filter_status;

		$url = $filter_status !== '' ? '&filter_status=' . $filter_status : '';

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

	protected function getForm() {
		$data['text_form'] = !isset($this->request->get['transfer_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');
		$data['error_warning'] = $this->error['warning'] ?? '';

		$data['breadcrumbs'] = [];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('warehouse/transfer', 'user_token=' . $this->session->data['user_token'], true),
		];

		$this->load->model('warehouse/warehouse');
		$data['warehouses'] = $this->model_warehouse_warehouse->getWarehouses(['sort' => 'priority', 'order' => 'DESC', 'limit' => 1000]);

		$is_edit = isset($this->request->get['transfer_id']);

		if ($is_edit) {
			$transfer_info = $this->model_warehouse_transfer->getTransfer((int)$this->request->get['transfer_id']);
			$transfer_items = $this->model_warehouse_transfer->getTransferItems((int)$this->request->get['transfer_id']);

			$items = [];

			foreach ($transfer_items as $item) {
				$items[] = [
					'product_id' => $item['product_id'],
					'variant_id' => $item['variant_id'],
					'quantity' => $item['quantity'],
					'label' => trim($item['product_model'] . ($item['variant_sku'] ? ' / ' . $item['variant_sku'] : '')),
				];
			}

			$data['transfer_id'] = $transfer_info['transfer_id'];
			$data['transfer_no'] = $transfer_info['transfer_no'];
			$data['status'] = $transfer_info['status'];
			$data['from_warehouse_id'] = $transfer_info['from_warehouse_id'];
			$data['to_warehouse_id'] = $transfer_info['to_warehouse_id'];
			$data['note'] = $transfer_info['note'];
			$data['items'] = $items;
			$data['is_immutable'] = in_array($transfer_info['status'], ['completed', 'cancelled'], true);
		} else {
			$data['transfer_id'] = 0;
			$data['transfer_no'] = '';
			$data['status'] = 'pending';
			$data['from_warehouse_id'] = '';
			$data['to_warehouse_id'] = '';
			$data['note'] = '';
			$data['items'] = [['product_id' => '', 'variant_id' => 0, 'quantity' => '', 'label' => '']];
			$data['is_immutable'] = false;
		}

		$data['action'] = $this->url->link('warehouse/transfer/' . ($is_edit ? 'edit' : 'add'), 'user_token=' . $this->session->data['user_token'] . ($is_edit ? '&transfer_id=' . $data['transfer_id'] : ''), true);
		$data['cancel'] = $this->url->link('warehouse/transfer', 'user_token=' . $this->session->data['user_token'], true);

		$data['status_options'] = [
			'pending' => $this->language->get('text_status_pending'),
			'in_transit' => $this->language->get('text_status_in_transit'),
			'completed' => $this->language->get('text_status_completed'),
			'cancelled' => $this->language->get('text_status_cancelled'),
		];

		$data['transfer_url'] = $this->url->link('catalog/product/autocomplete', 'user_token=' . $this->session->data['user_token'], true);
		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('warehouse/transfer_form', $data));
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

	protected function validateStatus() {
		return $this->validateForm();
	}

	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'warehouse/transfer')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}