<?php
/**
 * DockerCart Warehouse Pickup Module
 * Self-pickup shipping extension backed by warehouse self-pickup settings.
 */

declare(strict_types=1);

class ControllerExtensionShippingDockercartWarehousePickup extends Controller {
	private $error = [];

	public function index() {
		$this->load->language('extension/shipping/dockercart_warehouse_pickup');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('shipping_dockercart_warehouse_pickup', $this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('extension/shipping/dockercart_warehouse_pickup', 'user_token=' . $this->session->data['user_token'], true));
		}

		$data['error_warning'] = $this->error['warning'] ?? '';
		$data['success'] = $this->session->data['success'] ?? '';
		unset($this->session->data['success']);

		$data['action'] = $this->url->link('extension/shipping/dockercart_warehouse_pickup', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true);
		$data['user_token'] = $this->session->data['user_token'];

		foreach (['status', 'sort_order', 'tax_class_id'] as $key) {
			$setting_key = 'shipping_dockercart_warehouse_pickup_' . $key;

			if (isset($this->request->post[$setting_key])) {
				$data[$setting_key] = $this->request->post[$setting_key];
			} else {
				$data[$setting_key] = $this->config->get($setting_key);
			}
		}

		$this->load->model('localisation/tax_class');
		$data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();

		// Warehouses that allow pickup.
		$warehouse = new \DockercartWarehouse($this->registry);
		$data['pickup_warehouses'] = [];

		foreach ($warehouse->getAllWarehouses() as $w) {
			if (!$w['allow_pickup']) {
				continue;
			}

			$address_parts = [];
			$city = trim((string)$w['city']);
			$postcode = trim((string)$w['postcode']);

			if ($city !== '') {
				$address_parts[] = $postcode !== '' ? $city . ', ' . $postcode : $city;
			} elseif ($postcode !== '') {
				$address_parts[] = $postcode;
			}

			foreach (['address_1', 'address_2'] as $part) {
				if (trim((string)$w[$part]) !== '') {
					$address_parts[] = trim((string)$w[$part]);
				}
			}

			$next_slot = $warehouse->nextPickupSlot((int)$w['warehouse_id']);

			$data['pickup_warehouses'][] = [
				'warehouse_id' => (int)$w['warehouse_id'],
				'name' => $w['name'],
				'status' => !empty($w['status']),
				'is_default' => !empty($w['is_default']),
				'address' => implode(', ', $address_parts),
				'phone' => (string)$w['phone'],
				'email' => (string)$w['email'],
				'map_url' => (string)$w['map_url'],
				'pickup_cost' => $this->currency->format((float)$w['pickup_cost'], $this->config->get('config_currency')),
				'prepare_days' => (int)$w['prepare_days'],
				'pickup_note' => (string)$w['pickup_note'],
				'next_pickup' => $next_slot ? date($this->language->get('date_format_short'), strtotime($next_slot['date'])) . ', ' . $next_slot['time_from'] . '–' . $next_slot['time_to'] : '',
				'edit' => $this->url->link('warehouse/warehouse/edit', 'user_token=' . $this->session->data['user_token'] . '&warehouse_id=' . $w['warehouse_id'], true),
			];
		}

		$data['warehouse_link'] = $this->url->link('warehouse/warehouse', 'user_token=' . $this->session->data['user_token'], true);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/shipping/dockercart_warehouse_pickup', $data));
	}

	public function install(): void {
		if ($this->user->hasPermission('modify', 'extension/shipping/dockercart_warehouse_pickup')) {
			$this->load->model('setting/setting');
			$this->model_setting_setting->editSetting('shipping_dockercart_warehouse_pickup', ['shipping_dockercart_warehouse_pickup_status' => 1, 'shipping_dockercart_warehouse_pickup_sort_order' => 0]);
			$this->cache->delete('shipping');
		}
	}

	public function uninstall(): void {
		if ($this->user->hasPermission('modify', 'extension/shipping/dockercart_warehouse_pickup')) {
			$this->cache->delete('shipping');
		}
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/shipping/dockercart_warehouse_pickup')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}