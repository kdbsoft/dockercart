<?php
class ControllerExtensionModuleFeatured extends Controller {
	public function index($setting) {
		$this->load->language('extension/module/featured');

		$this->load->model('catalog/product');
		$this->load->model('catalog/category');

		$this->load->model('tool/image');

		$data['products'] = array();

		if (!$setting['limit']) {
			$setting['limit'] = 4;
		}

		if (!empty($setting['product'])) {
			$products = array_slice($setting['product'], 0, (int)$setting['limit']);

			// Build wishlist IDs set once before the product loop
			$wishlist_ids = array();
			if ($this->customer->isLogged()) {
				$this->load->model('account/wishlist');
				foreach ($this->model_account_wishlist->getWishlist() as $w) {
					$wishlist_ids[] = (int)$w['product_id'];
				}
			} elseif (isset($this->session->data['wishlist'])) {
				$wishlist_ids = array_map('intval', $this->session->data['wishlist']);
			}

			foreach ($products as $product_id) {
				$product_info = $this->model_catalog_product->getProduct($product_id);

				if ($product_info) {
					if ($product_info['image']) {
						$image = $this->model_tool_image->resize($product_info['image'], $setting['width'], $setting['height']);
					} else {
						$image = $this->model_tool_image->resize('placeholder.png', $setting['width'], $setting['height']);
					}

					if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
						$price = $this->currency->format($this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
					} else {
						$price = false;
					}

					if (!is_null($product_info['special']) && (float)$product_info['special'] >= 0) {
						$special = $this->currency->format($this->tax->calculate($product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
						$tax_price = (float)$product_info['special'];
					} else {
						$special = false;
						$tax_price = (float)$product_info['price'];
					}

					// Compute discount percent (integer) if special price exists
					$discount_percent = 0;
					if (!is_null($product_info['special']) && $product_info['price'] > 0) {
						$discount_percent = (int) round((1 - ((float)$product_info['special'] / (float)$product_info['price'])) * 100);
						if ($discount_percent < 0) { $discount_percent = 0; }
					}
		
					if ($this->config->get('config_tax')) {
						$tax = $this->currency->format($tax_price, $this->session->data['currency']);
					} else {
						$tax = false;
					}

					if ($this->config->get('config_review_status')) {
						$rating = $product_info['rating'];
					} else {
						$rating = false;
					}

					$category_name = '';
					$product_categories = $this->model_catalog_product->getCategories($product_info['product_id']);

					if (!empty($product_categories[0]['category_id'])) {
						$category_info = $this->model_catalog_category->getCategory((int)$product_categories[0]['category_id']);

						if ($category_info && !empty($category_info['name'])) {
							$category_name = $category_info['name'];
						}
					}

					$data['products'][] = array(
						'product_id'  => $product_info['product_id'],
						'thumb'       => $image,
						'name'        => $product_info['name'],
						'model'       => $product_info['model'],
						'manufacturer'=> isset($product_info['manufacturer']) ? $product_info['manufacturer'] : '',
						'category'    => $category_name,
						'description' => utf8_substr(strip_tags(html_entity_decode($product_info['description'], ENT_QUOTES, 'UTF-8')), 0, $this->config->get('theme_' . $this->config->get('config_theme') . '_product_description_length')) . '..',
						'price'       => $price,
						'special'     => $special,
						'discount'    => $discount_percent,
						'tax'         => $tax,
						'rating'      => $rating,
						'reviews'     => $product_info['reviews'],
					'in_wishlist' => in_array((int)$product_info['product_id'], $wishlist_ids) ? 1 : 0,
						'href'        => $this->url->link('product/product', 'product_id=' . $product_info['product_id'])
					);
				}
			}
		}

		if ($data['products']) {
			// Language strings for template
			$data['text_reviews'] = $this->language->get('text_reviews');
			$data['text_section_small'] = $this->language->get('text_section_small');
			$data['text_quick_view'] = $this->language->get('text_quick_view');
			$data['button_cart'] = $this->language->get('button_cart');
			$data['heading_title'] = $this->language->get('heading_title');
			return $this->load->view('extension/module/featured', $data);
		}
	}
}