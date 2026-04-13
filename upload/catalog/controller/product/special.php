<?php
class ControllerProductSpecial extends Controller {
	public function index() {
		$this->load->language('product/special');

		$this->load->model('catalog/product');
		$this->load->model('catalog/category');

		$this->load->model('tool/image');

		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'p.sort_order';
		}

		if (isset($this->request->get['order'])) {
			$order = $this->request->get['order'];
		} else {
			$order = 'ASC';
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		if (isset($this->request->get['limit']) && (int)$this->request->get['limit'] > 0) {
			$limit = (int)$this->request->get['limit'];
		} else {
			$limit = $this->config->get('theme_' . $this->config->get('config_theme') . '_product_limit');
		}

		$this->document->setTitle($this->language->get('heading_title'));

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_empty']    = $this->language->get('text_empty');

		// UI strings used in template
		$data['text_products'] = $this->language->get('text_products');
		$data['text_quick_view'] = $this->language->get('text_quick_view');
		$data['text_sale'] = $this->language->get('text_sale');
		$data['text_special_tagline'] = $this->language->get('text_special_tagline');
		// short word for "reviews" (used in listing templates)
		$data['text_reviews'] = $this->language->get('text_reviews_word');

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		if (isset($this->request->get['limit'])) {
			$url .= '&limit=' . $this->request->get['limit'];
		}

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('product/special', $url)
		);

		$data['text_compare'] = sprintf($this->language->get('text_compare'), (isset($this->session->data['compare']) ? count($this->session->data['compare']) : 0));

		$data['compare'] = $this->url->link('product/compare');

		$data['products'] = array();

		$filter_data = array(
			'sort'  => $sort,
			'order' => $order,
			'start' => ($page - 1) * $limit,
			'limit' => $limit
		);

		$product_total = $this->model_catalog_product->getTotalProductSpecials();
		$data['product_total'] = $product_total;

		$results = $this->model_catalog_product->getProductSpecials($filter_data);

		foreach ($results as $result) {
			if ($result['image']) {
				$image = $this->model_tool_image->resize($result['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height'));
			} else {
				$image = $this->model_tool_image->resize('placeholder.png', $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height'));
			}

			if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				$price = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
			} else {
				$price = false;
			}

			if (!is_null($result['special']) && (float)$result['special'] >= 0) {
				$special = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				$tax_price = (float)$result['special'];
			} else {
				$special = false;
				$tax_price = (float)$result['price'];
			}

			if ($this->config->get('config_tax')) {
				$tax = $this->currency->format($tax_price, $this->session->data['currency']);
			} else {
				$tax = false;
			}

			if ($this->config->get('config_review_status')) {
				$rating = (int)$result['rating'];
			} else {
				$rating = false;
			}

			$stock_quantity = (int)($result['quantity'] ?? 0);

			if ($stock_quantity <= 0) {
				$stock = !empty($result['stock_status']) ? $result['stock_status'] : '';
			} elseif ($this->config->get('config_stock_display')) {
				$stock = $stock_quantity;
			} else {
				$stock = $this->language->get('text_instock');
			}

			if ($stock === 'text_instock') {
				$stock = 'In Stock';
			}

			$category_name = '';
			$product_categories = $this->model_catalog_product->getCategories($result['product_id']);

			if (!empty($product_categories[0]['category_id'])) {
				$category_info = $this->model_catalog_category->getCategory((int)$product_categories[0]['category_id']);

				if ($category_info && !empty($category_info['name'])) {
					$category_name = $category_info['name'];
				}
			}

			$data['products'][] = array(
				'product_id'  => $result['product_id'],
				'thumb'       => $image,
				'name'        => $result['name'],
				'description' => utf8_substr(trim(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8'))), 0, $this->config->get('theme_' . $this->config->get('config_theme') . '_product_description_length')) . '..',
				'category'    => $category_name,
				'price'       => $price,
				'special'     => $special,
				'tax'         => $tax,
				'minimum'     => $result['minimum'] > 0 ? $result['minimum'] : 1,
				'rating'      => $result['rating'],
				'reviews'     => isset($result['reviews']) ? $result['reviews'] : 0,
				'stock'       => $stock,
				'is_in_stock' => ($stock_quantity > 0),
				'href'        => $this->url->link('product/product', 'product_id=' . $result['product_id'] . $url)
			);
		}

		$url = '';

		if (isset($this->request->get['limit'])) {
			$url .= '&limit=' . $this->request->get['limit'];
		}

		$data['sorts'] = array();

		$data['sorts'][] = array(
			'text'  => $this->language->get('text_default'),
			'value' => 'p.sort_order-ASC',
			'href'  => $this->url->link('product/special', 'sort=p.sort_order&order=ASC' . $url)
		);

		$data['sorts'][] = array(
			'text'  => $this->language->get('text_name_asc'),
			'value' => 'pd.name-ASC',
			'href'  => $this->url->link('product/special', 'sort=pd.name&order=ASC' . $url)
		);

		$data['sorts'][] = array(
			'text'  => $this->language->get('text_name_desc'),
			'value' => 'pd.name-DESC',
			'href'  => $this->url->link('product/special', 'sort=pd.name&order=DESC' . $url)
		);

		$data['sorts'][] = array(
			'text'  => $this->language->get('text_price_asc'),
			'value' => 'ps.price-ASC',
			'href'  => $this->url->link('product/special', 'sort=ps.price&order=ASC' . $url)
		);

		$data['sorts'][] = array(
			'text'  => $this->language->get('text_price_desc'),
			'value' => 'ps.price-DESC',
			'href'  => $this->url->link('product/special', 'sort=ps.price&order=DESC' . $url)
		);

		if ($this->config->get('config_review_status')) {
			$data['sorts'][] = array(
				'text'  => $this->language->get('text_rating_desc'),
				'value' => 'rating-DESC',
				'href'  => $this->url->link('product/special', 'sort=rating&order=DESC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_rating_asc'),
				'value' => 'rating-ASC',
				'href'  => $this->url->link('product/special', 'sort=rating&order=ASC' . $url)
			);
		}

		$data['sorts'][] = array(
				'text'  => $this->language->get('text_model_asc'),
				'value' => 'p.model-ASC',
				'href'  => $this->url->link('product/special', 'sort=p.model&order=ASC' . $url)
		);

		$data['sorts'][] = array(
			'text'  => $this->language->get('text_model_desc'),
			'value' => 'p.model-DESC',
			'href'  => $this->url->link('product/special', 'sort=p.model&order=DESC' . $url)
		);

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		$data['limits'] = array();

		$limits = array_unique(array($this->config->get('theme_' . $this->config->get('config_theme') . '_product_limit'), 25, 50, 75, 100));

		sort($limits);

		foreach($limits as $value) {
			$data['limits'][] = array(
				'text'  => $value,
				'value' => $value,
				'href'  => $this->url->link('product/special', $url . '&limit=' . $value)
			);
		}

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['limit'])) {
			$url .= '&limit=' . $this->request->get['limit'];
		}

		$pagination = new Pagination();
		$pagination->total = $product_total;
		$pagination->page = $page;
		$pagination->limit = $limit;
		$pagination->url = $this->url->link('product/special', $url . '&page={page}');

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($product_total) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($product_total - $limit)) ? $product_total : ((($page - 1) * $limit) + $limit), $product_total, ceil($product_total / $limit));

		// http://googlewebmastercentral.blogspot.com/2011/09/pagination-with-relnext-and-relprev.html
		if ($page == 1) {
		    $this->document->addLink($this->url->link('product/special', '', true), 'canonical');
		} else {
		    $this->document->addLink($this->url->link('product/special', 'page='. $page , true), 'canonical');
		}		
		
		if ($page > 1) {
			$this->document->addLink($this->url->link('product/special', (($page - 2) ? '&page='. ($page - 1) : ''), true), 'prev');
		}

		if ($limit && ceil($product_total / $limit) > $page) {
		    $this->document->addLink($this->url->link('product/special', 'page='. ($page + 1), true), 'next');
		}

		$data['sort'] = $sort;
		$data['order'] = $order;
		$data['limit'] = $limit;

		$data['continue'] = '/';

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		// short word for "reviews" (used in listing templates)
		$data['text_reviews'] = $this->language->get('text_reviews_word');

		// Load-more AJAX
		$lm_params = 'sort=' . $sort . '&order=' . $order . '&limit=' . $limit;
		$data['load_more_url']   = HTTP_SERVER . 'index.php?route=product/special/loadmore&' . $lm_params;
		$data['has_more']        = $product_total > (($page - 1) * $limit + count($data['products']));
		$data['products_loaded'] = ($page - 1) * $limit + count($data['products']);
		$data['text_load_more']  = $this->language->get('text_load_more');
		$data['page']            = $page;

		$this->response->setOutput($this->load->view('product/special', $data));
	}

	/**
	 * AJAX load-more endpoint.
	 * Returns JSON: { "html": "<product cards>", "count": N, "total": N }
	 */
	public function loadmore() {
		$this->load->language('product/special');
		$this->load->model('catalog/product');
		$this->load->model('catalog/category');
		$this->load->model('tool/image');

		$this->response->addHeader('Content-Type: application/json');

		$sort  = isset($this->request->get['sort'])  ? $this->request->get['sort']  : 'p.sort_order';
		$order = isset($this->request->get['order']) ? $this->request->get['order'] : 'ASC';
		$page  = isset($this->request->get['page'])  ? (int)$this->request->get['page'] : 1;

		if ($page < 1) {
			$page = 1;
		}

		if (isset($this->request->get['limit']) && (int)$this->request->get['limit'] > 0) {
			$limit = (int)$this->request->get['limit'];
		} else {
			$limit = (int)$this->config->get('theme_' . $this->config->get('config_theme') . '_product_limit');
		}

		$filter_data = array(
			'sort'  => $sort,
			'order' => $order,
			'start' => ($page - 1) * $limit,
			'limit' => $limit
		);

		$product_total = $this->model_catalog_product->getTotalProductSpecials();
		$results       = $this->model_catalog_product->getProductSpecials($filter_data);

		$wishlist_ids = array();
		if ($this->customer->isLogged()) {
			$this->load->model('account/wishlist');
			foreach ($this->model_account_wishlist->getWishlist() as $w) {
				$wishlist_ids[] = (int)$w['product_id'];
			}
		} elseif (isset($this->session->data['wishlist'])) {
			$wishlist_ids = array_map('intval', $this->session->data['wishlist']);
		}

		$products = array();
		foreach ($results as $result) {
			$image = $result['image']
				? $this->model_tool_image->resize($result['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height'))
				: $this->model_tool_image->resize('placeholder.png', $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height'));

			if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				$price = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
			} else {
				$price = false;
			}

			if (!is_null($result['special']) && (float)$result['special'] >= 0) {
				$special = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
			} else {
				$special = false;
			}

			$stock_quantity = (int)($result['quantity'] ?? 0);

			if ($stock_quantity <= 0) {
				$stock = !empty($result['stock_status']) ? $result['stock_status'] : '';
			} elseif ($this->config->get('config_stock_display')) {
				$stock = $stock_quantity;
			} else {
				$stock = $this->language->get('text_instock');
			}

			if ($stock === 'text_instock') {
				$stock = 'In Stock';
			}

			$category_name = '';
			$product_categories = $this->model_catalog_product->getCategories($result['product_id']);
			if (!empty($product_categories[0]['category_id'])) {
				$cat_info = $this->model_catalog_category->getCategory((int)$product_categories[0]['category_id']);
				if ($cat_info && !empty($cat_info['name'])) {
					$category_name = $cat_info['name'];
				}
			}

			$products[] = array(
				'product_id'  => $result['product_id'],
				'thumb'       => $image,
				'name'        => $result['name'],
				'model'       => isset($result['model']) ? $result['model'] : '',
				'description' => utf8_substr(trim(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8'))), 0, $this->config->get('theme_' . $this->config->get('config_theme') . '_product_description_length')) . '..',
				'category'    => $category_name,
				'price'       => $price,
				'special'     => $special,
				'minimum'     => $result['minimum'] > 0 ? $result['minimum'] : 1,
				'rating'      => (int)$result['rating'],
				'reviews'     => isset($result['reviews']) ? (int)$result['reviews'] : 0,
				'stock'       => $stock,
				'is_in_stock' => ($stock_quantity > 0),
				'in_wishlist' => in_array((int)$result['product_id'], $wishlist_ids) ? 1 : 0,
				'href'        => $this->url->link('product/product', 'product_id=' . $result['product_id'])
			);
		}

		$html = '';
		foreach ($products as $product) {
			$html .= $this->load->view('product/product_card_ajax', array(
				'product'          => $product,
				'text_quick_view'  => $this->language->get('text_quick_view'),
				'text_reviews'     => $this->language->get('text_reviews_word'),
				'text_sale'        => $this->language->get('text_sale'),
				'button_cart'      => $this->language->get('button_cart'),
				'btn_quick_hover'  => 'hover:bg-red-600',
				'link_hover'       => 'hover:text-blue-600 transition',
				'btn_cart_classes' => 'bg-blue-600 text-white hover:bg-blue-700',
			));
		}

		$this->response->setOutput(json_encode(array(
			'html'  => $html,
			'count' => count($products),
			'total' => $product_total,
		)));
	}
}
