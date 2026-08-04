<?php
class ControllerCustomerCustomerApproval extends Controller {
	public function index() {
		$this->load->language('customer/customer_approval');

		$this->document->setTitle($this->language->get('heading_title'));

		if (isset($this->session->data['warning'])) {
			$data['error_warning'] = $this->session->data['warning'];

			unset($this->session->data['warning']);
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if (isset($this->request->get['filter_name'])) {
			$filter_name = $this->request->get['filter_name'];
		} else {
			$filter_name = '';
		}

		if (isset($this->request->get['filter_email'])) {
			$filter_email = $this->request->get['filter_email'];
		} else {
			$filter_email = '';
		}

		if (isset($this->request->get['filter_customer_group_id'])) {
			$filter_customer_group_id = $this->request->get['filter_customer_group_id'];
		} else {
			$filter_customer_group_id = '';
		}

		if (isset($this->request->get['filter_type'])) {
			$filter_type = $this->request->get['filter_type'];
		} else {
			$filter_type = '';
		}

		if (isset($this->request->get['filter_date_added'])) {
			$filter_date_added = $this->request->get['filter_date_added'];
		} else {
			$filter_date_added = '';
		}

		$url = '';

		if (isset($this->request->get['filter_name'])) {
			$url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_email'])) {
			$url .= '&filter_email=' . urlencode(html_entity_decode($this->request->get['filter_email'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_customer_group_id'])) {
			$url .= '&filter_customer_group_id=' . $this->request->get['filter_customer_group_id'];
		}

		if (isset($this->request->get['filter_type'])) {
			$url .= '&filter_type=' . $this->request->get['filter_type'];
		}

		if (isset($this->request->get['filter_date_added'])) {
			$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['text_list_subtitle'] = $this->language->get('text_list_subtitle');
		$data['filter_name'] = $filter_name;
		$data['filter_email'] = $filter_email;
		$data['filter_customer_group_id'] = $filter_customer_group_id;
		$data['filter_type'] = $filter_type;
		$data['filter_date_added'] = $filter_date_added;

		// Per-admin saved filters (Shopify-style tabs)
		$active_filter = $this->getActiveUserFilter('customer_approval');

		$this->load->model('user/user_filter');

		$user_id = (int)$this->user->getId();
		$saved_filters = $this->model_user_user_filter->getFilters($user_id, 'customer_approval');

		$this->load->model('customer/customer_approval');

		$tab_counts = array(
			'all' => $this->model_customer_customer_approval->getTotalCustomerApprovals(array())
		);

		foreach ($saved_filters as $saved) {
			$tab_counts['custom_' . $saved['filter_id']] = $this->model_customer_customer_approval->getTotalCustomerApprovals($this->buildApprovalFilterData($saved['conditions']));
		}

		$data['user_filter'] = $this->renderUserFilter('customer_approval', 'customer/customer_approval', array(
			array('key' => 'name', 'label' => $this->language->get('entry_name'), 'type' => 'text'),
			array('key' => 'email', 'label' => $this->language->get('entry_email'), 'type' => 'text'),
			array('key' => 'customer_group_id', 'label' => $this->language->get('entry_customer_group'), 'type' => 'multi', 'options' => $this->getApprovalGroupOptions()),
			array('key' => 'type', 'label' => $this->language->get('entry_type'), 'type' => 'select', 'options' => array(
				array('value' => 'customer', 'label' => $this->language->get('text_customer')),
				array('value' => 'affiliate', 'label' => $this->language->get('text_affiliate'))
			)),
			array('key' => 'date_added', 'label' => $this->language->get('entry_date_added'), 'type' => 'date')
		), $tab_counts);

		$data['active_filter'] = $active_filter;

		$data['user_token'] = $this->session->data['user_token'];

		$this->load->model('customer/customer_group');

		$data['customer_groups'] = $this->model_customer_customer_group->getCustomerGroups();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('customer/customer_approval', $data));
	}

	public function customer_approval() {
		$this->load->language('customer/customer_approval');

		if (isset($this->request->get['filter_name'])) {
			$filter_name = $this->request->get['filter_name'];
		} else {
			$filter_name = '';
		}

		if (isset($this->request->get['filter_email'])) {
			$filter_email = $this->request->get['filter_email'];
		} else {
			$filter_email = '';
		}

		if (isset($this->request->get['filter_customer_group_id'])) {
			$filter_customer_group_id = $this->request->get['filter_customer_group_id'];
		} else {
			$filter_customer_group_id = '';
		}

		if (isset($this->request->get['filter_type'])) {
			$filter_type = $this->request->get['filter_type'];
		} else {
			$filter_type = '';
		}

		if (isset($this->request->get['filter_date_added'])) {
			$filter_date_added = $this->request->get['filter_date_added'];
		} else {
			$filter_date_added = '';
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$data['customer_approvals'] = array();

		$filter_data = array(
			'filter_name'              => $filter_name,
			'filter_email'             => $filter_email,
			'filter_customer_group_id' => $filter_customer_group_id,
			'filter_type'              => $filter_type,
			'filter_date_added'        => $filter_date_added,
			'start'                    => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit'                    => $this->config->get('config_limit_admin')
		);

		// Apply active saved filter
		$active_filter = $this->getActiveUserFilter('customer_approval');

		if ($active_filter) {
			foreach ($this->buildApprovalFilterData($active_filter['conditions']) as $key => $value) {
				$filter_data[$key] = $value;
			}
		}

		$this->load->model('customer/customer_approval');

		$customer_approval_total = $this->model_customer_customer_approval->getTotalCustomerApprovals($filter_data);

		$results = $this->model_customer_customer_approval->getCustomerApprovals($filter_data);

		foreach ($results as $result) {
			$data['customer_approvals'][] = array(
				'customer_id'    => $result['customer_id'],
				'name'           => $result['name'],
				'email'          => $result['email'],
				'customer_group' => $result['customer_group'],
				'type'           => $this->language->get('text_' . $result['type']),
				'date_added'     => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'approve'        => $this->url->link('customer/customer_approval/approve', 'user_token=' . $this->session->data['user_token'] . '&customer_id=' . $result['customer_id'] . '&type=' . $result['type'], true),
				'deny'           => $this->url->link('customer/customer_approval/deny', 'user_token=' . $this->session->data['user_token'] . '&customer_id=' . $result['customer_id'] . '&type=' . $result['type'], true),
				'edit'           => $this->url->link('customer/customer/edit', 'user_token=' . $this->session->data['user_token'] . '&customer_id=' . $result['customer_id'], true)
			);
		}

		$url = '';

		if (isset($this->request->get['filter_name'])) {
			$url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_email'])) {
			$url .= '&filter_email=' . urlencode(html_entity_decode($this->request->get['filter_email'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_customer_group_id'])) {
			$url .= '&filter_customer_group_id=' . $this->request->get['filter_customer_group_id'];
		}

		if (isset($this->request->get['filter_type'])) {
			$url .= '&filter_type=' . $this->request->get['filter_type'];
		}

		if (isset($this->request->get['filter_date_added'])) {
			$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
		}

		$pagination = new Pagination();
		$pagination->total = $customer_approval_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('customer/customer_approval/customer_approval', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($customer_approval_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($customer_approval_total - $this->config->get('config_limit_admin'))) ? $customer_approval_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $customer_approval_total, ceil($customer_approval_total / $this->config->get('config_limit_admin')));

		$this->response->setOutput($this->load->view('customer/customer_approval_list', $data));
	}

	public function approve() {
		$this->load->language('customer/customer_approval');

		$json = array();

		if (!$this->user->hasPermission('modify', 'customer/customer_approval')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$this->load->model('customer/customer_approval');

			if ($this->request->get['type'] == 'customer') {
				$this->model_customer_customer_approval->approveCustomer($this->request->get['customer_id']);
			} elseif ($this->request->get['type'] == 'affiliate') {
				$this->model_customer_customer_approval->approveAffiliate($this->request->get['customer_id']);
			}

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function deny() {
		$this->load->language('customer/customer_approval');

		$json = array();

		if (!$this->user->hasPermission('modify', 'customer/customer_approval')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$this->load->model('customer/customer_approval');

			if ($this->request->get['type'] == 'customer') {
				$this->model_customer_customer_approval->denyCustomer($this->request->get['customer_id']);
			} elseif ($this->request->get['type'] == 'affiliate') {
				$this->model_customer_customer_approval->denyAffiliate($this->request->get['customer_id']);
			}

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Convert saved filter conditions into approval model filter_data.
	 */
	private function buildApprovalFilterData(array $conditions): array {
		$data = array();

		foreach ($conditions as $condition) {
			$field = (string)($condition['field'] ?? '');
			$value = $condition['value'] ?? '';

			switch ($field) {
				case 'name':
					$data['filter_name'] = (string)$value;
					break;
				case 'email':
					$data['filter_email'] = (string)$value;
					break;
				case 'customer_group_id':
					$data['filter_customer_group_id'] = is_array($value) ? implode(',', array_map('intval', $value)) : (string)$value;
					break;
				case 'type':
					$data['filter_type'] = (string)$value;
					break;
				case 'date_added':
					$data['filter_date_added'] = (string)$value;
					break;
			}
		}

		return $data;
	}

	private function getApprovalGroupOptions(): array {
		$this->load->model('customer/customer_group');

		$options = array();

		foreach ($this->model_customer_customer_group->getCustomerGroups() as $group) {
			$options[] = array(
				'value' => $group['customer_group_id'],
				'label' => $group['name']
			);
		}

		return $options;
	}
}
