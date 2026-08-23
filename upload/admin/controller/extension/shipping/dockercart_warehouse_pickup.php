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

		// Warehouses that allow pickup (informational list).
		$warehouse = new \DockercartWarehouse($this->registry);
		$pickup_warehouses = [];

		foreach ($warehouse->getAllWarehouses() as $w) {
			if ($w['allow_pickup']) {
				$pickup_warehouses[] = $w;
			}
		}

		$data['pickup_warehouses'] = $pickup_warehouses;
		$data['warehouse_link'] = $this->url->link('warehouse/warehouse', 'user_token=' . $this->session->data['user_token'], true);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/shipping/dockercart_warehouse_pickup', $data));
	}

	public function install(): void {
		if ($this->user->hasPermission('modify', 'extension/shipping/dockercart_warehouse_pickup')) {
			$this->load->model('setting/extension');
			$this->model_setting_extension->addExtension('shipping', 'dockercart_warehouse_pickup');
			$this->load->model('setting/setting');
			$this->model_setting_setting->editSetting('shipping_dockercart_warehouse_pickup', ['shipping_dockercart_warehouse_pickup_status' => 1, 'shipping_dockercart_warehouse_pickup_sort_order' => 0]);
			$this->cache->delete('shipping');
		}
	}

	public function uninstall(): void {
		if ($this->user->hasPermission('modify', 'extension/shipping/dockercart_warehouse_pickup')) {
			$this->load->model('setting/extension');
			$this->model_setting_extension->deleteExtension('shipping', 'dockercart_warehouse_pickup');
			$this->load->model('setting/setting');
			$this->model_setting_setting->deleteSetting('shipping_dockercart_warehouse_pickup');
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