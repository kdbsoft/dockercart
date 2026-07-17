<?php
class ModelExtensionTotalProductBundle extends Model {
	public function getTotal($total) {
		$bundle_lib = new ProductBundle($this->registry);
		$active_bundles = $bundle_lib->getActiveBundles((int)$this->config->get('config_store_id'));

		if (empty($active_bundles)) {
			return;
		}

		$cart_products = $this->cart->getProducts();
		$cart_by_product = array();

		foreach ($cart_products as $cart_product) {
			$pid = (int)$cart_product['product_id'];

			if (!isset($cart_by_product[$pid])) {
				$cart_by_product[$pid] = array(
					'quantity' => 0,
					'price'    => (float)$cart_product['price'],
				);
			}

			$cart_by_product[$pid]['quantity'] += (float)$cart_product['quantity'];
		}

		$total_discount = 0;

		foreach ($active_bundles as $bundle) {
			$bundle_products = $bundle_lib->getBundleProducts($bundle['bundle_id']);

			if (count($bundle_products) < 2) {
				continue;
			}

			$set_count = null;
			$set_price = 0;

			foreach ($bundle_products as $bp) {
				$pid = (int)$bp['product_id'];

				if (!isset($cart_by_product[$pid])) {
					$set_count = null;
					break;
				}

				$qty = $cart_by_product[$pid]['quantity'];
				$set_price += $cart_by_product[$pid]['price'];

				if ($set_count === null) {
					$set_count = $qty;
				} else {
					$set_count = min($set_count, $qty);
				}
			}

			if ($set_count === null || $set_count < 1) {
				continue;
			}

			$set_count = (int)floor($set_count);

			if ($set_count < 1) {
				continue;
			}

			$discount = $bundle_lib->calculateDiscount($set_price, $bundle['discount_type'], $bundle['discount_value'], $set_count);

			$total_discount += abs($discount);
		}

		if ($total_discount > 0) {
			$this->load->language('checkout/cart');

			$discount = min($total_discount, $total['total']);

			$total['totals'][] = array(
				'code'       => 'product_bundle',
				'title'      => $this->language->get('text_bundle_discount'),
				'value'      => -$discount,
				'sort_order' => $this->config->get('total_product_bundle_sort_order') ?: 3
			);

			$total['total'] -= $discount;
		}
	}
}
