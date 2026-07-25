<?php
class ModelExtensionTotalBxgy extends Model {
	public function getTotal($total) {
		$this->load->language('checkout/cart');

		$bxgy_lib = new Bxgy($this->registry);
		$cart_products = $this->cart->getProducts();

		if (empty($cart_products)) {
			return;
		}

		$discounts = $bxgy_lib->getPerProductDiscounts($cart_products);

		if (empty($discounts)) {
			return;
		}

		$total_discount = 0;

		foreach ($cart_products as $cart_product) {
			$pid = (int) $cart_product['product_id'];

			if (isset($discounts[$pid])) {
				$total_discount += $discounts[$pid]['discount_amount'] * (int) $cart_product['quantity'];
			}
		}

		if ($total_discount > 0) {
			$discount = min($total_discount, $total['total']);

			$total['totals'][] = array(
				'code'       => 'bxgy',
				'title'      => $this->language->get('text_bxgy_discount'),
				'value'      => -$discount,
				'sort_order' => $this->config->get('total_bxgy_sort_order') ?: 2
			);

			$total['total'] -= $discount;
		}
	}
}
