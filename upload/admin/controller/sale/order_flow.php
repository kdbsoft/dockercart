<?php
declare(strict_types=1);

class ControllerSaleOrderFlow extends Controller {
	private $error = array();

	public function index(): void {
		$this->load->language('sale/order_flow');

		$this->document->setTitle($this->language->get('heading_title'));

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->saveFlow();

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('sale/order_flow', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->getForm();
	}

	protected function validate(): bool {
		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	protected function saveFlow(): void {
		$this->load->model('localisation/order_status');

		$known_ids = array();

		foreach ($this->model_localisation_order_status->getOrderStatuses() as $status) {
			$known_ids[(int)$status['order_status_id']] = true;
		}

		$steps = array_values(array_unique(array_map('intval', (array)($this->request->post['step'] ?? []))));
		$steps = array_values(array_filter($steps, function ($id) use ($known_ids) {
			return $id > 0 && isset($known_ids[$id]);
		}));

		$transitions = array();
		$froms = (array)($this->request->post['transition_from'] ?? []);
		$tos = (array)($this->request->post['transition_to'] ?? []);

		foreach ($froms as $i => $from) {
			$from_id = (int)$from;
			$to_id = (int)($tos[$i] ?? 0);

			if ($from_id > 0 && $to_id > 0 && $from_id !== $to_id && isset($known_ids[$from_id]) && isset($known_ids[$to_id])) {
				$transitions[$from_id][] = $to_id;
			}
		}

		foreach ($transitions as &$targets) {
			$targets = array_values(array_unique($targets));
		}
		unset($targets);

		$this->setSetting('config_order_flow_steps', json_encode($steps), 1);
		$this->setSetting('config_order_flow_transitions', json_encode($transitions), 1);
	}

	protected function setSetting($key, $value, $serialized): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "setting` SET `value` = '" . $this->db->escape($value) . "', serialized = '" . (int)$serialized . "' WHERE store_id = '0' AND `code` = 'config' AND `key` = '" . $this->db->escape($key) . "'");

		if (!$this->db->countAffected()) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET store_id = '0', `code` = 'config', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape($value) . "', serialized = '" . (int)$serialized . "'");
		}
	}

	protected function getForm(): void {
		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['action'] = $this->url->link('sale/order_flow', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'], true);

		$this->load->model('localisation/order_status');

		$order_statuses = $this->model_localisation_order_status->getOrderStatuses();
		$data['order_statuses'] = $order_statuses;

		$status_names = array();

		foreach ($order_statuses as $status) {
			$status_names[(int)$status['order_status_id']] = $status['name'];
		}

		$steps = array_values(array_map('intval', (array)$this->config->get('config_order_flow_steps')));

		$data['steps'] = array();

		foreach ($steps as $step) {
			$data['steps'][] = array(
				'order_status_id' => (int)$step,
				'name'            => $status_names[(int)$step] ?? '',
			);
		}

		$step_ids = array_map('intval', $steps);

		$data['available_statuses'] = array();

		foreach ($order_statuses as $status) {
			if (!in_array((int)$status['order_status_id'], $step_ids, true)) {
				$data['available_statuses'][] = $status;
			}
		}

		$data['transitions'] = array();
		$transitions = (array)$this->config->get('config_order_flow_transitions');

		foreach ($transitions as $from => $targets) {
			foreach ((array)$targets as $to) {
				$data['transitions'][] = array(
					'from'      => (int)$from,
					'from_name' => $status_names[(int)$from] ?? '',
					'to'        => (int)$to,
					'to_name'   => $status_names[(int)$to] ?? '',
				);
			}
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('sale/order_flow', $data));
	}
}
