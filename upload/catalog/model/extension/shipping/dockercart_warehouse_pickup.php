<?php
/**
 * DockerCart Warehouse Pickup (Catalog)
 *
 * Self-pickup shipping method. Produces one quote per warehouse that allows
 * pickup (allow_pickup=1) and can cover the whole cart. Choosing a pickup
 * warehouse forces the cart allocation to that warehouse.
 */

declare(strict_types=1);

class ModelExtensionShippingDockercartWarehousePickup extends Model {
	public function getQuote(array $address): array {
		$this->load->language('extension/shipping/dockercart_warehouse_pickup');

		if (!$this->config->get('shipping_dockercart_warehouse_pickup_status')) {
			return [];
		}

		$warehouse = new \DockercartWarehouse($this->registry);

		if (!$warehouse->isEnabled()) {
			return [];
		}

		$cart_products = $this->cart->getProducts();
		$quote_data = [];

		foreach ($warehouse->getWarehouses() as $w) {
			if (!$w['allow_pickup']) {
				continue;
			}

			// Must cover the whole cart.
			$covers = true;

			foreach ($cart_products as $product) {
				if (empty($product['subtract'])) {
					continue;
				}

				$available = $warehouse->getAvailableForLine((int)$product['product_id'], (int)($product['variant_id'] ?? 0), (int)$w['warehouse_id']);

				if ($available < (float)$product['quantity']) {
					$covers = false;
					break;
				}
			}

			if (!$covers) {
				continue;
			}

			$tax_class_id = $this->config->get('shipping_dockercart_warehouse_pickup_tax_class_id');

			$slot = $warehouse->nextPickupSlot((int)$w['warehouse_id']);

			$title = $w['name'];
			$detail = [];

			if (!empty($w['city'])) {
				$detail[] = $w['city'];
			}

			if (!empty($w['address_1'])) {
				$detail[] = $w['address_1'];
			}

			if ($detail) {
				$title .= ' — ' . implode(', ', $detail);
			}

			if (!empty($w['phone'])) {
				$title .= ' · ' . $w['phone'];
			}

			$description = trim($w['pickup_note'] ?? '');

			if ($slot && $slot['date']) {
				$description = sprintf($this->language->get('text_pickup_slot'), $slot['date'], $slot['time_from'], $slot['time_to']) . ($description ? "\n" . $description : '');
			}

			$cost = (float)$w['pickup_cost'];

			if ($cost > 0) {
				$text = $this->currency->format($this->tax->calculate($cost, $tax_class_id, $this->config->get('config_tax')), $this->session->data['currency']);
			} else {
				$text = $this->language->get('text_free');
			}

			$quote_data['pickup_' . (int)$w['warehouse_id']] = [
				'code' => 'dockercart_warehouse_pickup.pickup_' . (int)$w['warehouse_id'],
				'title' => $title,
				'cost' => $cost,
				'tax_class_id' => $tax_class_id,
				'text' => $text,
				'description' => $description,
			];
		}

		if (!$quote_data) {
			return [];
		}

		return [
			'code' => 'dockercart_warehouse_pickup',
			'title' => $this->language->get('text_title'),
			'quote' => $quote_data,
			'sort_order' => $this->config->get('shipping_dockercart_warehouse_pickup_sort_order'),
			'error' => false,
		];
	}
}