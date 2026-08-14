<?php
class ControllerSaleReturn extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('sale/return');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('sale/return');

		$this->getList();
	}

	public function add() {
		$this->load->language('sale/return');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('sale/return');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_sale_return->addReturn($this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['filter_return_id'])) {
				$url .= '&filter_return_id=' . $this->request->get['filter_return_id'];
			}

			if (isset($this->request->get['filter_order_id'])) {
				$url .= '&filter_order_id=' . $this->request->get['filter_order_id'];
			}

			if (isset($this->request->get['filter_customer'])) {
				$url .= '&filter_customer=' . urlencode(html_entity_decode($this->request->get['filter_customer'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_product'])) {
				$url .= '&filter_product=' . urlencode(html_entity_decode($this->request->get['filter_product'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_model'])) {
				$url .= '&filter_model=' . urlencode(html_entity_decode($this->request->get['filter_model'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_return_status_id'])) {
				$url .= '&filter_return_status_id=' . $this->request->get['filter_return_status_id'];
			}

			if (isset($this->request->get['filter_date_added'])) {
				$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
			}

			if (isset($this->request->get['filter_date_modified'])) {
				$url .= '&filter_date_modified=' . $this->request->get['filter_date_modified'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('sale/return', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function edit() {
		$this->load->language('sale/return');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('sale/return');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_sale_return->editReturn($this->request->get['return_id'], $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['filter_return_id'])) {
				$url .= '&filter_return_id=' . $this->request->get['filter_return_id'];
			}

			if (isset($this->request->get['filter_order_id'])) {
				$url .= '&filter_order_id=' . $this->request->get['filter_order_id'];
			}

			if (isset($this->request->get['filter_customer'])) {
				$url .= '&filter_customer=' . urlencode(html_entity_decode($this->request->get['filter_customer'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_product'])) {
				$url .= '&filter_product=' . urlencode(html_entity_decode($this->request->get['filter_product'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_model'])) {
				$url .= '&filter_model=' . urlencode(html_entity_decode($this->request->get['filter_model'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_return_status_id'])) {
				$url .= '&filter_return_status_id=' . $this->request->get['filter_return_status_id'];
			}

			if (isset($this->request->get['filter_date_added'])) {
				$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
			}

			if (isset($this->request->get['filter_date_modified'])) {
				$url .= '&filter_date_modified=' . $this->request->get['filter_date_modified'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('sale/return', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function delete() {
		$this->load->language('sale/return');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('sale/return');

		if (isset($this->request->post['selected']) && $this->validateDelete()) {
			foreach ($this->request->post['selected'] as $return_id) {
				$this->model_sale_return->deleteReturn($return_id);
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['filter_return_id'])) {
				$url .= '&filter_return_id=' . $this->request->get['filter_return_id'];
			}

			if (isset($this->request->get['filter_order_id'])) {
				$url .= '&filter_order_id=' . $this->request->get['filter_order_id'];
			}

			if (isset($this->request->get['filter_customer'])) {
				$url .= '&filter_customer=' . urlencode(html_entity_decode($this->request->get['filter_customer'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_product'])) {
				$url .= '&filter_product=' . urlencode(html_entity_decode($this->request->get['filter_product'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_model'])) {
				$url .= '&filter_model=' . urlencode(html_entity_decode($this->request->get['filter_model'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_return_status_id'])) {
				$url .= '&filter_return_status_id=' . $this->request->get['filter_return_status_id'];
			}

			if (isset($this->request->get['filter_date_added'])) {
				$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
			}

			if (isset($this->request->get['filter_date_modified'])) {
				$url .= '&filter_date_modified=' . $this->request->get['filter_date_modified'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('sale/return', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	public function exportCsv(): void {
		$this->load->language('sale/return');

		if (!$this->user->hasPermission('access', 'sale/return')) {
			$this->response->redirect($this->url->link('sale/return', 'user_token=' . $this->session->data['user_token'], true));
			return;
		}

		$this->load->model('sale/return');

		$ids = array();

		if (isset($this->request->get['return_id'])) {
			if (is_array($this->request->get['return_id'])) {
				$ids = $this->request->get['return_id'];
			} else {
				$ids = explode(',', (string)$this->request->get['return_id']);
			}

			$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
		}

		if (!$ids) {
			$this->response->redirect($this->url->link('sale/return', 'user_token=' . $this->session->data['user_token'], true));
			return;
		}

		$id_list = implode(',', $ids);

		$qty_query = $this->db->query("SELECT return_id, SUM(quantity) AS total_quantity FROM `" . DB_PREFIX . "return_product` WHERE return_id IN (" . $id_list . ") GROUP BY return_id");

		$quantities = array();

		foreach ($qty_query->rows as $row) {
			$quantities[(int)$row['return_id']] = (int)$row['total_quantity'];
		}

		$reason_query = $this->db->query("SELECT rr.return_reason_id, rr.name FROM `" . DB_PREFIX . "return_reason` rr WHERE rr.language_id = '" . (int)$this->config->get('config_language_id') . "'");

		$reasons = array();

		foreach ($reason_query->rows as $row) {
			$reasons[(int)$row['return_reason_id']] = $row['name'];
		}

		$action_query = $this->db->query("SELECT ra.return_action_id, ra.name FROM `" . DB_PREFIX . "return_action` ra WHERE ra.language_id = '" . (int)$this->config->get('config_language_id') . "'");

		$actions = array();

		foreach ($action_query->rows as $row) {
			$actions[(int)$row['return_action_id']] = $row['name'];
		}

		$status_query = $this->db->query("SELECT rs.return_status_id, rs.name FROM `" . DB_PREFIX . "return_status` rs WHERE rs.language_id = '" . (int)$this->config->get('config_language_id') . "'");

		$statuses = array();

		foreach ($status_query->rows as $row) {
			$statuses[(int)$row['return_status_id']] = $row['name'];
		}

		$headers = array(
			$this->language->get('column_return_id'),
			$this->language->get('column_order_id'),
			$this->language->get('column_customer'),
			$this->language->get('entry_email'),
			$this->language->get('entry_telephone'),
			$this->language->get('column_product'),
			$this->language->get('column_model'),
			$this->language->get('column_quantity'),
			$this->language->get('text_return_type'),
			$this->language->get('column_amount'),
			$this->language->get('entry_return_reason'),
			$this->language->get('entry_return_action'),
			$this->language->get('column_status'),
			$this->language->get('column_date_added'),
			$this->language->get('column_date_modified'),
			$this->language->get('column_comment')
		);

		$rows = array();

		foreach ($ids as $return_id) {
			$return_info = $this->model_sale_return->getReturn($return_id);

			if (!$return_info) {
				continue;
			}

			$type = $return_info['type'] === 'partial' ? 'text_return_type_partial' : ($return_info['type'] === 'exchange' ? 'text_return_type_exchange' : 'text_return_type_full');

			$rows[] = array(
				$return_id,
				$return_info['order_id'],
				$return_info['firstname'] . ' ' . $return_info['lastname'],
				$return_info['email'],
				$return_info['telephone'],
				$return_info['product'],
				$return_info['model'],
				isset($quantities[(int)$return_id]) ? $quantities[(int)$return_id] : 0,
				$this->language->get($type),
				(float)$return_info['amount'],
				isset($reasons[(int)$return_info['return_reason_id']]) ? $reasons[(int)$return_info['return_reason_id']] : '',
				isset($actions[(int)$return_info['return_action_id']]) ? $actions[(int)$return_info['return_action_id']] : '',
				$return_info['return_status'],
				date($this->language->get('date_format_short'), strtotime($return_info['date_added'])),
				date($this->language->get('date_format_short'), strtotime($return_info['date_modified'])),
				$return_info['comment']
			);
		}

		if (!$rows) {
			$this->response->redirect($this->url->link('sale/return', 'user_token=' . $this->session->data['user_token'], true));
			return;
		}

		$output = "\xEF\xBB\xBF" . $this->csvLine($headers);

		foreach ($rows as $row) {
			$output .= $this->csvLine($row);
		}

		$filename = 'returns-' . date('Ymd-His') . '.csv';

		$this->response->addHeader('Content-Type: text/csv; charset=UTF-8');
		$this->response->addHeader('Content-Disposition: attachment; filename="' . $filename . '"');
		$this->response->setOutput($output);
	}

	private function csvLine(array $fields): string {
		$quoted = array();

		foreach ($fields as $field) {
			$quoted[] = '"' . str_replace('"', '""', (string)$field) . '"';
		}

		return implode(',', $quoted) . "\r\n";
	}

	protected function getList() {
		if (isset($this->request->get['filter_return_id'])) {
			$filter_return_id = $this->request->get['filter_return_id'];
		} else {
			$filter_return_id = '';
		}

		if (isset($this->request->get['filter_order_id'])) {
			$filter_order_id = $this->request->get['filter_order_id'];
		} else {
			$filter_order_id = '';
		}

		if (isset($this->request->get['filter_customer'])) {
			$filter_customer = $this->request->get['filter_customer'];
		} else {
			$filter_customer = '';
		}

		if (isset($this->request->get['filter_product'])) {
			$filter_product = $this->request->get['filter_product'];
		} else {
			$filter_product = '';
		}

		if (isset($this->request->get['filter_model'])) {
			$filter_model = $this->request->get['filter_model'];
		} else {
			$filter_model = '';
		}

		if (isset($this->request->get['filter_return_status_id'])) {
			$filter_return_status_id = $this->request->get['filter_return_status_id'];
		} else {
			$filter_return_status_id = '';
		}

		if (isset($this->request->get['filter_date_added'])) {
			$filter_date_added = $this->request->get['filter_date_added'];
		} else {
			$filter_date_added = '';
		}

		if (isset($this->request->get['filter_date_modified'])) {
			$filter_date_modified = $this->request->get['filter_date_modified'];
		} else {
			$filter_date_modified = '';
		}

		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'r.return_id';
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

		$url = '';

		if (isset($this->request->get['filter_return_id'])) {
			$url .= '&filter_return_id=' . $this->request->get['filter_return_id'];
		}

		if (isset($this->request->get['filter_order_id'])) {
			$url .= '&filter_order_id=' . $this->request->get['filter_order_id'];
		}

		if (isset($this->request->get['filter_customer'])) {
			$url .= '&filter_customer=' . urlencode(html_entity_decode($this->request->get['filter_customer'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_product'])) {
			$url .= '&filter_product=' . urlencode(html_entity_decode($this->request->get['filter_product'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_model'])) {
			$url .= '&filter_model=' . urlencode(html_entity_decode($this->request->get['filter_model'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_return_status_id'])) {
			$url .= '&filter_return_status_id=' . $this->request->get['filter_return_status_id'];
		}

		if (isset($this->request->get['filter_date_added'])) {
			$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
		}

		if (isset($this->request->get['filter_date_modified'])) {
			$url .= '&filter_date_modified=' . $this->request->get['filter_date_modified'];
		}

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['add'] = $this->url->link('sale/return/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['delete'] = $this->url->link('sale/return/delete', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['export_url'] = str_replace('&amp;', '&', $this->url->link('sale/return/exportCsv', 'user_token=' . $this->session->data['user_token'], true));
		$data['text_list_subtitle'] = $this->language->get('text_list_subtitle');

		// Per-admin saved filters (Shopify-style tabs)
		$active_filter = $this->getActiveUserFilter('return');

		$this->load->model('user/user_filter');

		$user_id = (int)$this->user->getId();
		$saved_filters = $this->model_user_user_filter->getFilters($user_id, 'return');

		$tab_counts = array(
			'all' => $this->model_sale_return->getTotalReturns(array())
		);

		foreach ($saved_filters as $saved) {
			$tab_counts['custom_' . $saved['filter_id']] = $this->model_sale_return->getTotalReturns($this->buildReturnFilterData($saved['conditions']));
		}

		$this->load->model('localisation/return_status');

		$return_status_options = array();

		foreach ($this->model_localisation_return_status->getReturnStatuses() as $status) {
			$return_status_options[] = array(
				'value' => $status['return_status_id'],
				'label' => $status['name']
			);
		}

		$data['user_filter'] = $this->renderUserFilter('return', 'sale/return', array(
			array('key' => 'return_id', 'label' => $this->language->get('entry_return_id'), 'type' => 'number'),
			array('key' => 'order_id', 'label' => $this->language->get('entry_order_id'), 'type' => 'number'),
			array('key' => 'customer', 'label' => $this->language->get('entry_customer'), 'type' => 'text'),
			array('key' => 'product', 'label' => $this->language->get('entry_product'), 'type' => 'text'),
			array('key' => 'model', 'label' => $this->language->get('entry_model'), 'type' => 'text'),
			array('key' => 'return_status_id', 'label' => $this->language->get('entry_return_status'), 'type' => 'multi', 'options' => $return_status_options),
			array('key' => 'date_added', 'label' => $this->language->get('entry_date_added'), 'type' => 'date'),
			array('key' => 'date_modified', 'label' => $this->language->get('entry_date_modified'), 'type' => 'date')
		), $tab_counts);

		$data['active_filter'] = $active_filter;

		$data['returns'] = array();

		$filter_data = array(
			'filter_return_id'        => $filter_return_id,
			'filter_order_id'         => $filter_order_id,
			'filter_customer'         => $filter_customer,
			'filter_product'          => $filter_product,
			'filter_model'            => $filter_model,
			'filter_return_status_id' => $filter_return_status_id,
			'filter_date_added'       => $filter_date_added,
			'filter_date_modified'    => $filter_date_modified,
			'sort'                    => $sort,
			'order'                   => $order,
			'start'                   => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit'                   => $this->config->get('config_limit_admin')
		);

		if ($active_filter) {
			foreach ($this->buildReturnFilterData($active_filter['conditions']) as $key => $value) {
				$filter_data[$key] = $value;
			}
		}

		$return_total = $this->model_sale_return->getTotalReturns($filter_data);

		$results = $this->model_sale_return->getReturns($filter_data);

		foreach ($results as $result) {
			$data['returns'][] = array(
				'return_id'     => $result['return_id'],
				'order_id'      => $result['order_id'],
				'customer'      => $result['customer'],
				'product'       => $result['product'],
				'model'         => $result['model'],
				'return_status' => $result['return_status'],
				'date_added'    => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'date_modified' => date($this->language->get('date_format_short'), strtotime($result['date_modified'])),
				'edit'          => $this->url->link('sale/return/edit', 'user_token=' . $this->session->data['user_token'] . '&return_id=' . $result['return_id'] . $url, true)
			);
		}

		$data['user_token'] = $this->session->data['user_token'];

		if (isset($this->session->data['error'])) {
			$data['error_warning'] = $this->session->data['error'];

			unset($this->session->data['error']);
		} elseif (isset($this->error['warning'])) {
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

		if (isset($this->request->get['filter_return_id'])) {
			$url .= '&filter_return_id=' . $this->request->get['filter_return_id'];
		}

		if (isset($this->request->get['filter_order_id'])) {
			$url .= '&filter_order_id=' . $this->request->get['filter_order_id'];
		}

		if (isset($this->request->get['filter_customer'])) {
			$url .= '&filter_customer=' . urlencode(html_entity_decode($this->request->get['filter_customer'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_product'])) {
			$url .= '&filter_product=' . urlencode(html_entity_decode($this->request->get['filter_product'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_model'])) {
			$url .= '&filter_model=' . urlencode(html_entity_decode($this->request->get['filter_model'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_return_status_id'])) {
			$url .= '&filter_return_status_id=' . $this->request->get['filter_return_status_id'];
		}

		if (isset($this->request->get['filter_date_added'])) {
			$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
		}

		if (isset($this->request->get['filter_date_modified'])) {
			$url .= '&filter_date_modified=' . $this->request->get['filter_date_modified'];
		}

		if ($order == 'ASC') {
			$url .= '&order=DESC';
		} else {
			$url .= '&order=ASC';
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['sort_return_id'] = $this->url->link('sale/return', 'user_token=' . $this->session->data['user_token'] . '&sort=r.return_id' . $url, true);
		$data['sort_order_id'] = $this->url->link('sale/return', 'user_token=' . $this->session->data['user_token'] . '&sort=r.order_id' . $url, true);
		$data['sort_customer'] = $this->url->link('sale/return', 'user_token=' . $this->session->data['user_token'] . '&sort=customer' . $url, true);
		$data['sort_product'] = $this->url->link('sale/return', 'user_token=' . $this->session->data['user_token'] . '&sort=r.product' . $url, true);
		$data['sort_model'] = $this->url->link('sale/return', 'user_token=' . $this->session->data['user_token'] . '&sort=r.model' . $url, true);
		$data['sort_status'] = $this->url->link('sale/return', 'user_token=' . $this->session->data['user_token'] . '&sort=status' . $url, true);
		$data['sort_date_added'] = $this->url->link('sale/return', 'user_token=' . $this->session->data['user_token'] . '&sort=r.date_added' . $url, true);
		$data['sort_date_modified'] = $this->url->link('sale/return', 'user_token=' . $this->session->data['user_token'] . '&sort=r.date_modified' . $url, true);

		$url = '';

		if (isset($this->request->get['filter_return_id'])) {
			$url .= '&filter_return_id=' . $this->request->get['filter_return_id'];
		}

		if (isset($this->request->get['filter_order_id'])) {
			$url .= '&filter_order_id=' . $this->request->get['filter_order_id'];
		}

		if (isset($this->request->get['filter_customer'])) {
			$url .= '&filter_customer=' . urlencode(html_entity_decode($this->request->get['filter_customer'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_product'])) {
			$url .= '&filter_product=' . urlencode(html_entity_decode($this->request->get['filter_product'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_model'])) {
			$url .= '&filter_model=' . urlencode(html_entity_decode($this->request->get['filter_model'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_return_status_id'])) {
			$url .= '&filter_return_status_id=' . $this->request->get['filter_return_status_id'];
		}

		if (isset($this->request->get['filter_date_added'])) {
			$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
		}

		if (isset($this->request->get['filter_date_modified'])) {
			$url .= '&filter_date_modified=' . $this->request->get['filter_date_modified'];
		}

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		$pagination = new Pagination();
		$pagination->total = $return_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('sale/return', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = $pagination->renderResults($this->language->get('text_pagination'));

		$data['filter_return_id'] = $filter_return_id;
		$data['filter_order_id'] = $filter_order_id;
		$data['filter_customer'] = $filter_customer;
		$data['filter_product'] = $filter_product;
		$data['filter_model'] = $filter_model;
		$data['filter_return_status_id'] = $filter_return_status_id;
		$data['filter_date_added'] = $filter_date_added;
		$data['filter_date_modified'] = $filter_date_modified;

		$this->load->model('localisation/return_status');

		$data['return_statuses'] = $this->model_localisation_return_status->getReturnStatuses();

		$data['sort'] = $sort;
		$data['order'] = $order;

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('sale/return_list', $data));
	}

	protected function getForm() {
		$data['text_form'] = !isset($this->request->get['return_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');
		$data['text_form_subtitle'] = !isset($this->request->get['return_id'])
		    ? $this->language->get('text_add_return_subtitle')
		    : $this->language->get('text_edit_return_subtitle');

		$data['user_token'] = $this->session->data['user_token'];

		if (isset($this->request->get['return_id'])) {
			$data['return_id'] = (int)$this->request->get['return_id'];
		} else {
			$data['return_id'] = 0;
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['order_id'])) {
			$data['error_order_id'] = $this->error['order_id'];
		} else {
			$data['error_order_id'] = '';
		}

		if (isset($this->error['firstname'])) {
			$data['error_firstname'] = $this->error['firstname'];
		} else {
			$data['error_firstname'] = '';
		}

		if (isset($this->error['lastname'])) {
			$data['error_lastname'] = $this->error['lastname'];
		} else {
			$data['error_lastname'] = '';
		}

		if (isset($this->error['email'])) {
			$data['error_email'] = $this->error['email'];
		} else {
			$data['error_email'] = '';
		}

		if (isset($this->error['telephone'])) {
			$data['error_telephone'] = $this->error['telephone'];
		} else {
			$data['error_telephone'] = '';
		}

		if (isset($this->error['product'])) {
			$data['error_product'] = $this->error['product'];
		} else {
			$data['error_product'] = '';
		}

		if (isset($this->error['model'])) {
			$data['error_model'] = $this->error['model'];
		} else {
			$data['error_model'] = '';
		}

		if (isset($this->error['products'])) {
			$data['error_products'] = $this->error['products'];
		} else {
			$data['error_products'] = '';
		}

		if (isset($this->error['amount'])) {
			$data['error_amount'] = $this->error['amount'];
		} else {
			$data['error_amount'] = '';
		}

		$url = '';

		if (isset($this->request->get['filter_return_id'])) {
			$url .= '&filter_return_id=' . $this->request->get['filter_return_id'];
		}

		if (isset($this->request->get['filter_order_id'])) {
			$url .= '&filter_order_id=' . $this->request->get['filter_order_id'];
		}

		if (isset($this->request->get['filter_customer'])) {
			$url .= '&filter_customer=' . urlencode(html_entity_decode($this->request->get['filter_customer'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_product'])) {
			$url .= '&filter_product=' . urlencode(html_entity_decode($this->request->get['filter_product'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_model'])) {
			$url .= '&filter_model=' . urlencode(html_entity_decode($this->request->get['filter_model'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_return_status_id'])) {
			$url .= '&filter_return_status_id=' . $this->request->get['filter_return_status_id'];
		}

		if (isset($this->request->get['filter_date_added'])) {
			$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
		}

		if (isset($this->request->get['filter_date_modified'])) {
			$url .= '&filter_date_modified=' . $this->request->get['filter_date_modified'];
		}

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		if (!isset($this->request->get['return_id'])) {
			$data['action'] = $this->url->link('sale/return/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		} else {
			$data['action'] = $this->url->link('sale/return/edit', 'user_token=' . $this->session->data['user_token'] . '&return_id=' . $this->request->get['return_id'] . $url, true);
		}

		$data['cancel'] = $this->url->link('sale/return', 'user_token=' . $this->session->data['user_token'] . $url, true);

		if (isset($this->request->get['return_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$return_info = $this->model_sale_return->getReturn($this->request->get['return_id']);
		}

		$order_id = (int)($this->request->get['order_id'] ?? 0);

		if (isset($this->request->post['order_id'])) {
			$data['order_id'] = $this->request->post['order_id'];
		} elseif (!empty($return_info)) {
			$data['order_id'] = $return_info['order_id'];
		} else {
			$data['order_id'] = '';
		}

		if (isset($this->request->post['date_ordered'])) {
			$data['date_ordered'] = $this->request->post['date_ordered'];
		} elseif (!empty($return_info)) {
			$data['date_ordered'] = ($return_info['date_ordered'] != '0000-00-00' ? $return_info['date_ordered'] : '');
		} else {
			$data['date_ordered'] = '';
		}

		if (isset($this->request->post['customer'])) {
			$data['customer'] = $this->request->post['customer'];
		} elseif (!empty($return_info)) {
			$data['customer'] = $return_info['customer'];
		} else {
			$data['customer'] = '';
		}

		if (isset($this->request->post['customer_id'])) {
			$data['customer_id'] = $this->request->post['customer_id'];
		} elseif (!empty($return_info)) {
			$data['customer_id'] = $return_info['customer_id'];
		} else {
			$data['customer_id'] = '';
		}

		if (isset($this->request->post['firstname'])) {
			$data['firstname'] = $this->request->post['firstname'];
		} elseif (!empty($return_info)) {
			$data['firstname'] = $return_info['firstname'];
		} else {
			$data['firstname'] = '';
		}

		if (isset($this->request->post['lastname'])) {
			$data['lastname'] = $this->request->post['lastname'];
		} elseif (!empty($return_info)) {
			$data['lastname'] = $return_info['lastname'];
		} else {
			$data['lastname'] = '';
		}

		if (isset($this->request->post['email'])) {
			$data['email'] = $this->request->post['email'];
		} elseif (!empty($return_info)) {
			$data['email'] = $return_info['email'];
		} else {
			$data['email'] = '';
		}

		if (isset($this->request->post['telephone'])) {
			$data['telephone'] = $this->request->post['telephone'];
		} elseif (!empty($return_info)) {
			$data['telephone'] = $return_info['telephone'];
		} else {
			$data['telephone'] = '';
		}

		if (isset($this->request->post['product'])) {
			$data['product'] = $this->request->post['product'];
		} elseif (!empty($return_info)) {
			$data['product'] = $return_info['product'];
		} else {
			$data['product'] = '';
		}

		if (isset($this->request->post['product_id'])) {
			$data['product_id'] = $this->request->post['product_id'];
		} elseif (!empty($return_info)) {
			$data['product_id'] = $return_info['product_id'];
		} else {
			$data['product_id'] = '';
		}

		if (isset($this->request->post['model'])) {
			$data['model'] = $this->request->post['model'];
		} elseif (!empty($return_info)) {
			$data['model'] = $return_info['model'];
		} else {
			$data['model'] = '';
		}

		if (isset($this->request->post['quantity'])) {
			$data['quantity'] = $this->request->post['quantity'];
		} elseif (!empty($return_info)) {
			$data['quantity'] = $return_info['quantity'];
		} else {
			$data['quantity'] = '';
		}

		if (isset($this->request->post['opened'])) {
			$data['opened'] = $this->request->post['opened'];
		} elseif (!empty($return_info)) {
			$data['opened'] = $return_info['opened'];
		} else {
			$data['opened'] = '';
		}

		if (isset($this->request->post['return_reason_id'])) {
			$data['return_reason_id'] = $this->request->post['return_reason_id'];
		} elseif (!empty($return_info)) {
			$data['return_reason_id'] = $return_info['return_reason_id'];
		} else {
			$data['return_reason_id'] = '';
		}

		$this->load->model('localisation/return_reason');

		$data['return_reasons'] = $this->model_localisation_return_reason->getReturnReasons();

		if (isset($this->request->post['return_action_id'])) {
			$data['return_action_id'] = $this->request->post['return_action_id'];
		} elseif (!empty($return_info)) {
			$data['return_action_id'] = $return_info['return_action_id'];
		} else {
			$data['return_action_id'] = '';
		}

		$this->load->model('localisation/return_action');

		$data['return_actions'] = $this->model_localisation_return_action->getReturnActions();

		if (isset($this->request->post['comment'])) {
			$data['comment'] = $this->request->post['comment'];
		} elseif (!empty($return_info)) {
			$data['comment'] = $return_info['comment'];
		} else {
			$data['comment'] = '';
		}

		if (isset($this->request->post['return_status_id'])) {
			$data['return_status_id'] = $this->request->post['return_status_id'];
		} elseif (!empty($return_info)) {
			$data['return_status_id'] = $return_info['return_status_id'];
		} else {
			$data['return_status_id'] = '';
		}

		$this->load->model('localisation/return_status');

		$data['return_statuses'] = $this->model_localisation_return_status->getReturnStatuses();

		// Return type, refund amount and items
		if (isset($this->request->post['type'])) {
			$data['type'] = $this->request->post['type'];
		} elseif (!empty($return_info)) {
			$data['type'] = $return_info['type'];
		} else {
			$data['type'] = 'full';
		}

		if (isset($this->request->post['amount'])) {
			$data['amount'] = $this->request->post['amount'];
		} elseif (!empty($return_info)) {
			$data['amount'] = $return_info['amount'];
		} else {
			$data['amount'] = 0;
		}

		if (isset($this->request->post['refund'])) {
			$data['refund'] = !empty($this->request->post['refund']);
		} elseif (!empty($return_info)) {
			$data['refund'] = (float)$return_info['amount'] > 0 && !$return_info['refunded'];
		} else {
			$data['refund'] = true;
		}

		$data['refunded'] = !empty($return_info['refunded']);
		$data['paid_amount_raw'] = $data['paid_amount_raw'] ?? 0;

		$data['return_products'] = array();

		if (is_array($this->request->post['products'] ?? null)) {
			foreach ($this->request->post['products'] as $product) {
				$data['return_products'][] = array(
					'order_product_id' => (int)($product['order_product_id'] ?? 0),
					'product_id'       => (int)($product['product_id'] ?? 0),
					'variant_id'       => (int)($product['variant_id'] ?? 0),
					'name'             => $product['name'] ?? '',
					'model'            => $product['model'] ?? '',
					'ordered'          => (int)($product['ordered'] ?? 0),
					'quantity'         => (int)($product['quantity'] ?? 0),
					'price'            => (float)($product['price'] ?? 0),
					'total'            => (float)($product['total'] ?? 0),
					'checked'          => !empty($product['checked']),
				);
			}
		} elseif (!empty($return_info)) {
			$this->load->model('sale/order');
			$order_products = $this->model_sale_order->getOrderProducts((int)$return_info['order_id']);

			$ordered_by_opid = array();

			foreach ($order_products as $order_product) {
				$ordered_by_opid[(int)$order_product['order_product_id']] = (int)$order_product['quantity'];
			}

			foreach ($this->model_sale_return->getReturnProducts((int)$return_info['return_id']) as $product) {
				$data['return_products'][] = array(
					'order_product_id' => (int)$product['order_product_id'],
					'product_id'       => (int)$product['product_id'],
					'variant_id'       => (int)$product['variant_id'],
					'name'             => $product['name'],
					'model'            => $product['model'],
					'ordered'          => $ordered_by_opid[(int)$product['order_product_id']] ?? (int)$product['quantity'],
					'quantity'         => (int)$product['quantity'],
					'price'            => (float)$product['price'],
					'total'            => (float)$product['total'],
					'checked'          => true,
				);
			}
		} elseif ($order_id && empty($return_info)) {
			$this->load->model('sale/order');

			$order_products = $this->model_sale_order->getOrderProducts($order_id);

			foreach ($order_products as $order_product) {
				$data['return_products'][] = array(
					'order_product_id' => (int)$order_product['order_product_id'],
					'product_id'       => (int)$order_product['product_id'],
					'variant_id'       => (int)($order_product['variant_id'] ?? 0),
					'name'             => $order_product['name'],
					'model'            => $order_product['model'],
					'ordered'          => (int)$order_product['quantity'],
					'quantity'         => 0,
					'price'            => (float)$order_product['price'],
					'total'            => 0,
					'checked'          => false,
				);
			}
		}

		// Pre-fill order details when a return is created from an order
		if (!isset($this->request->post['order_id']) && empty($return_info) && $order_id) {
			$this->load->model('sale/order');

			$order_info = $this->model_sale_order->getOrder($order_id);

			if ($order_info) {
				$data['order_id'] = $order_id;
				$data['date_ordered'] = date('Y-m-d', strtotime($order_info['date_added']));
				$data['customer_id'] = $order_info['customer_id'];
				$data['customer'] = trim($order_info['firstname'] . ' ' . $order_info['lastname']);
				$data['firstname'] = $order_info['firstname'];
				$data['lastname'] = $order_info['lastname'];
				$data['email'] = $order_info['email'];
				$data['telephone'] = $order_info['telephone'];
				$data['paid_amount_raw'] = (float)$order_info['paid_amount'];
			}
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('sale/return_form', $data));
	}

	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'sale/return')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (empty($this->request->post['order_id'])) {
			$this->error['order_id'] = $this->language->get('error_order_id');
		}

		if ((utf8_strlen($this->request->post['firstname']) < 1) || (utf8_strlen($this->request->post['firstname']) > 32)) {
			$this->error['firstname'] = $this->language->get('error_firstname');
		}

		if ((utf8_strlen($this->request->post['lastname']) < 1) || (utf8_strlen($this->request->post['lastname']) > 32)) {
			$this->error['lastname'] = $this->language->get('error_lastname');
		}

		if ((utf8_strlen($this->request->post['email']) > 96) || !filter_var($this->request->post['email'], FILTER_VALIDATE_EMAIL)) {
			$this->error['email'] = $this->language->get('error_email');
		}

		if ((utf8_strlen($this->request->post['telephone']) < 3) || (utf8_strlen($this->request->post['telephone']) > 32)) {
			$this->error['telephone'] = $this->language->get('error_telephone');
		}

		$has_items = false;

		if (is_array($this->request->post['products'] ?? null)) {
			foreach ($this->request->post['products'] as $product) {
				if ((int)($product['quantity'] ?? 0) > 0) {
					$has_items = true;
					break;
				}
			}
		}

		if ($has_items) {
			// Product details come from the order line items
		} else {
			if ((utf8_strlen($this->request->post['product'] ?? '') < 1) || (utf8_strlen($this->request->post['product'] ?? '') > 255)) {
				$this->error['product'] = $this->language->get('error_product');
			}

			if ((utf8_strlen($this->request->post['model'] ?? '') < 1) || (utf8_strlen($this->request->post['model'] ?? '') > 64)) {
				$this->error['model'] = $this->language->get('error_model');
			}

			if ((int)($this->request->post['quantity'] ?? 0) < 1) {
				$this->error['products'] = $this->language->get('error_products');
			}
		}

		if ((float)($this->request->post['amount'] ?? 0) < 0) {
			$this->error['amount'] = $this->language->get('error_amount');
		}

		if (empty($this->request->post['return_reason_id'])) {
			$this->error['reason'] = $this->language->get('error_reason');
		}

		if ($this->error && !isset($this->error['warning'])) {
			$this->error['warning'] = $this->language->get('error_warning');
		}

		return !$this->error;
	}

	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'sale/return')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	public function history() {
		$this->load->language('sale/return');

		$this->load->model('sale/return');

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$data['histories'] = array();

		$results = $this->model_sale_return->getReturnHistories($this->request->get['return_id'], ($page - 1) * 10, 10);

		foreach ($results as $result) {
			$data['histories'][] = array(
				'notify'     => $result['notify'] ? $this->language->get('text_yes') : $this->language->get('text_no'),
				'status'     => $result['status'],
				'comment'    => nl2br($result['comment']),
				'date_added' => date($this->language->get('date_format_short'), strtotime($result['date_added']))
			);
		}

		$history_total = $this->model_sale_return->getTotalReturnHistories($this->request->get['return_id']);

		$pagination = new Pagination();
		$pagination->total = $history_total;
		$pagination->page = $page;
		$pagination->limit = 10;
		$pagination->url = $this->url->link('sale/return/history', 'user_token=' . $this->session->data['user_token'] . '&return_id=' . $this->request->get['return_id'] . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = $pagination->renderResults($this->language->get('text_pagination'));

		$this->response->setOutput($this->load->view('sale/return_history', $data));
	}
	
	public function addHistory() {
		$this->load->language('sale/return');

		$json = array();

		if (!$this->user->hasPermission('modify', 'sale/return')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$this->load->model('sale/return');

			$this->model_sale_return->addReturnHistory($this->request->get['return_id'], $this->request->post['return_status_id'], $this->request->post['comment'], $this->request->post['notify']);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}	

	/**
	 * Convert saved filter conditions into return model filter_data.
	 */
	private function buildReturnFilterData(array $conditions): array {
		$data = array();

		foreach ($conditions as $condition) {
			$field = (string)($condition['field'] ?? '');
			$value = $condition['value'] ?? '';

			switch ($field) {
				case 'return_id':
					$data['filter_return_id'] = (string)$value;
					break;
				case 'order_id':
					$data['filter_order_id'] = (string)$value;
					break;
				case 'customer':
					$data['filter_customer'] = (string)$value;
					break;
				case 'product':
					$data['filter_product'] = (string)$value;
					break;
				case 'model':
					$data['filter_model'] = (string)$value;
					break;
				case 'return_status_id':
					$data['filter_return_status_id'] = is_array($value) ? implode(',', array_map('intval', $value)) : (string)$value;
					break;
				case 'date_added':
					$data['filter_date_added'] = (string)$value;
					break;
				case 'date_modified':
					$data['filter_date_modified'] = (string)$value;
					break;
			}
		}

		return $data;
	}
}