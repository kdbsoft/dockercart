<?php
class ControllerProductSearch extends Controller {
	public function index() {
		$this->load->language('product/search');

		$this->load->model('catalog/category');

		$this->load->model('catalog/product');

		$this->load->model('tool/image');

		if (isset($this->request->get['search'])) {
			$search = html_entity_decode(html_entity_decode(trim((string)$this->request->get['search']), ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
		} else {
			$search = '';
		}

		if (isset($this->request->get['tag'])) {
			$tag = html_entity_decode(html_entity_decode(trim((string)$this->request->get['tag']), ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
		} elseif (isset($this->request->get['search'])) {
			$tag = $search;
		} else {
			$tag = '';
		}

		if (isset($this->request->get['description'])) {
			$description = $this->request->get['description'];
		} else {
			$description = '';
		}

		if (isset($this->request->get['category_id'])) {
			$category_id = $this->request->get['category_id'];
		} else {
			$category_id = 0;
		}

		if (isset($this->request->get['sub_category'])) {
			$sub_category = $this->request->get['sub_category'];
		} else {
			$sub_category = '';
		}

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

		if (isset($this->request->get['search'])) {
			$this->document->setTitle($this->language->get('heading_title') .  ' - ' . $this->request->get['search']);
		} elseif (isset($this->request->get['tag'])) {
			$this->document->setTitle($this->language->get('heading_title') .  ' - ' . $this->language->get('heading_tag') . $this->request->get['tag']);
		} else {
			$this->document->setTitle($this->language->get('heading_title'));
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$url = '';

		if (isset($this->request->get['search'])) {
			$url .= '&search=' . urlencode(html_entity_decode($this->request->get['search'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['tag'])) {
			$url .= '&tag=' . urlencode(html_entity_decode(trim($this->request->get['tag']), ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['description'])) {
			$url .= '&description=' . $this->request->get['description'];
		}

		if (isset($this->request->get['category_id'])) {
			$url .= '&category_id=' . $this->request->get['category_id'];
		}

		if (isset($this->request->get['sub_category'])) {
			$url .= '&sub_category=' . $this->request->get['sub_category'];
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

		if (isset($this->request->get['limit'])) {
			$url .= '&limit=' . $this->request->get['limit'];
		}

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('product/search', $url)
		);

		if (isset($this->request->get['search'])) {
			$data['heading_title'] = $this->language->get('heading_title') .  ' - ' . $this->request->get['search'];
		} elseif (isset($this->request->get['tag'])) {
			$data['heading_title'] = $this->language->get('heading_title') .  ' - ' . $this->language->get('heading_tag') . $this->request->get['tag'];
		} else {
			$data['heading_title'] = $this->language->get('heading_title');
		}

		$data['text_compare'] = sprintf($this->language->get('text_compare'), (isset($this->session->data['compare']) ? count($this->session->data['compare']) : 0));

		$data['compare'] = $this->url->link('product/compare');

		// UI strings
		$data['text_quick_view'] = $this->language->get('text_quick_view');
		$data['text_products'] = $this->language->get('text_products');
		// short word for "reviews"
		$data['text_reviews'] = $this->language->get('text_reviews_word');

		// 3 Level Category Search
		$data['categories'] = array();

		$categories_1 = $this->model_catalog_category->getCategories(0);

		foreach ($categories_1 as $category_1) {
			$level_2_data = array();

			$categories_2 = $this->model_catalog_category->getCategories($category_1['category_id']);

			foreach ($categories_2 as $category_2) {
				$level_3_data = array();

				$categories_3 = $this->model_catalog_category->getCategories($category_2['category_id']);

				foreach ($categories_3 as $category_3) {
					$level_3_data[] = array(
						'category_id' => $category_3['category_id'],
						'name'        => $category_3['name'],
					);
				}

				$level_2_data[] = array(
					'category_id' => $category_2['category_id'],
					'name'        => $category_2['name'],
					'children'    => $level_3_data
				);
			}

			$data['categories'][] = array(
				'category_id' => $category_1['category_id'],
				'name'        => $category_1['name'],
				'children'    => $level_2_data
			);
		}

		$data['products'] = array();
		$data['product_total'] = 0;

		if (isset($this->request->get['search']) || isset($this->request->get['tag'])) {
			$filter_data = array(
				'filter_name'         => $search,
				'filter_tag'          => $tag,
				'filter_description'  => $description,
				'filter_category_id'  => $category_id,
				'filter_sub_category' => $sub_category,
				'sort'                => $sort,
				'order'               => $order,
				'start'               => ($page - 1) * $limit,
				'limit'               => $limit
			);

			$product_total = $this->model_catalog_product->getTotalProducts($filter_data);
			$data['product_total'] = $product_total;

			// Build wishlist IDs set for in_wishlist flag
			$wishlist_ids = array();
			if ($this->customer->isLogged()) {
				$this->load->model('account/wishlist');
				foreach ($this->model_account_wishlist->getWishlist() as $w) {
					$wishlist_ids[] = (int)$w['product_id'];
				}
			} elseif (isset($this->session->data['wishlist'])) {
				$wishlist_ids = array_map('intval', $this->session->data['wishlist']);
			}

			$results = $this->model_catalog_product->getProducts($filter_data);

			// DockerCart Search (Manticore) stores the real total in registry after overriding getProducts
			$manticore_total = $this->registry->get('manticore_search_total');
			if ($manticore_total !== null) {
				$product_total = (int)$manticore_total;
				$data['product_total'] = $product_total;
			}

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

				$data['products'][] = array(
					'product_id'  => $result['product_id'],
					'thumb'       => $image,
					'name'        => $result['name'],
					'model'       => $result['model'],
					'description' => utf8_substr(trim(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8'))), 0, $this->config->get('theme_' . $this->config->get('config_theme') . '_product_description_length')) . '..',
					'price'       => $price,
					'special'     => $special,
					'tax'         => $tax,
					'minimum'     => $result['minimum'] > 0 ? $result['minimum'] : 1,
					'rating'      => $result['rating'],
					'reviews'     => isset($result['reviews']) ? $result['reviews'] : 0,
					'in_wishlist' => in_array((int)$result['product_id'], $wishlist_ids) ? 1 : 0,
					'href'        => $this->url->link('product/product', 'product_id=' . $result['product_id'] . $url)
				);
			}

			$url = '';

			if (isset($this->request->get['search'])) {
				$url .= '&search=' . urlencode(html_entity_decode($this->request->get['search'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['tag'])) {
				$url .= '&tag=' . urlencode(html_entity_decode($this->request->get['tag'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['description'])) {
				$url .= '&description=' . $this->request->get['description'];
			}

			if (isset($this->request->get['category_id'])) {
				$url .= '&category_id=' . $this->request->get['category_id'];
			}

			if (isset($this->request->get['sub_category'])) {
				$url .= '&sub_category=' . $this->request->get['sub_category'];
			}

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

			$data['sorts'] = array();

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_default'),
				'value' => 'p.sort_order-ASC',
				'href'  => $this->url->link('product/search', 'sort=p.sort_order&order=ASC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_name_asc'),
				'value' => 'pd.name-ASC',
				'href'  => $this->url->link('product/search', 'sort=pd.name&order=ASC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_name_desc'),
				'value' => 'pd.name-DESC',
				'href'  => $this->url->link('product/search', 'sort=pd.name&order=DESC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_price_asc'),
				'value' => 'p.price-ASC',
				'href'  => $this->url->link('product/search', 'sort=p.price&order=ASC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_price_desc'),
				'value' => 'p.price-DESC',
				'href'  => $this->url->link('product/search', 'sort=p.price&order=DESC' . $url)
			);

			if ($this->config->get('config_review_status')) {
				$data['sorts'][] = array(
					'text'  => $this->language->get('text_rating_desc'),
					'value' => 'rating-DESC',
					'href'  => $this->url->link('product/search', 'sort=rating&order=DESC' . $url)
				);

				$data['sorts'][] = array(
					'text'  => $this->language->get('text_rating_asc'),
					'value' => 'rating-ASC',
					'href'  => $this->url->link('product/search', 'sort=rating&order=ASC' . $url)
				);
			}

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_model_asc'),
				'value' => 'p.model-ASC',
				'href'  => $this->url->link('product/search', 'sort=p.model&order=ASC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_model_desc'),
				'value' => 'p.model-DESC',
				'href'  => $this->url->link('product/search', 'sort=p.model&order=DESC' . $url)
			);

			$url = '';

			if (isset($this->request->get['search'])) {
				$url .= '&search=' . urlencode(html_entity_decode($this->request->get['search'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['tag'])) {
				$url .= '&tag=' . urlencode(html_entity_decode($this->request->get['tag'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['description'])) {
				$url .= '&description=' . $this->request->get['description'];
			}

			if (isset($this->request->get['category_id'])) {
				$url .= '&category_id=' . $this->request->get['category_id'];
			}

			if (isset($this->request->get['sub_category'])) {
				$url .= '&sub_category=' . $this->request->get['sub_category'];
			}

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
					'href'  => $this->url->link('product/search', $url . '&limit=' . $value)
				);
			}

			$url = '';

			if (isset($this->request->get['search'])) {
				$url .= '&search=' . urlencode(html_entity_decode($this->request->get['search'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['tag'])) {
				$url .= '&tag=' . urlencode(html_entity_decode($this->request->get['tag'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['description'])) {
				$url .= '&description=' . $this->request->get['description'];
			}

			if (isset($this->request->get['category_id'])) {
				$url .= '&category_id=' . $this->request->get['category_id'];
			}

			if (isset($this->request->get['sub_category'])) {
				$url .= '&sub_category=' . $this->request->get['sub_category'];
			}

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
			$pagination->url = $this->url->link('product/search', $url . '&page={page}');

			$data['pagination'] = $pagination->render();

			$data['results'] = sprintf($this->language->get('text_pagination'), ($product_total) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($product_total - $limit)) ? $product_total : ((($page - 1) * $limit) + $limit), $product_total, ceil($product_total / $limit));

			if (isset($this->request->get['search']) && $this->config->get('config_customer_search')) {
				$this->load->model('account/search');

				if ($this->customer->isLogged()) {
					$customer_id = $this->customer->getId();
				} else {
					$customer_id = 0;
				}

				if (isset($this->request->server['REMOTE_ADDR'])) {
					$ip = $this->request->server['REMOTE_ADDR'];
				} else {
					$ip = '';
				}

				$search_data = array(
					'keyword'       => $search,
					'category_id'   => $category_id,
					'sub_category'  => $sub_category,
					'description'   => $description,
					'products'      => $product_total,
					'customer_id'   => $customer_id,
					'ip'            => $ip
				);

				$this->model_account_search->addSearch($search_data);
			}
		}

		$data['search'] = $search;
		$data['description'] = $description;
		$data['category_id'] = $category_id;
		$data['sub_category'] = $sub_category;

		$data['sort'] = $sort;
		$data['order'] = $order;
		$data['limit'] = $limit;

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		// short word for "reviews" (used in listing templates)
		$data['text_reviews'] = $this->language->get('text_reviews_word');

		// Load-more AJAX
		$lm_params = '';
		if (isset($this->request->get['search']))      { $lm_params .= '&search='      . urlencode(html_entity_decode($this->request->get['search'], ENT_QUOTES, 'UTF-8')); }
		if (isset($this->request->get['tag']))         { $lm_params .= '&tag='         . urlencode(html_entity_decode(trim($this->request->get['tag']), ENT_QUOTES, 'UTF-8')); }
		if (isset($this->request->get['description'])) { $lm_params .= '&description=' . $this->request->get['description']; }
		if (isset($this->request->get['category_id'])) { $lm_params .= '&category_id=' . $this->request->get['category_id']; }
		if (isset($this->request->get['sub_category'])) { $lm_params .= '&sub_category=' . $this->request->get['sub_category']; }
		$lm_params .= '&sort=' . $sort . '&order=' . $order . '&limit=' . $limit;
		$data['load_more_url']   = HTTP_SERVER . 'index.php?route=product/search/loadmore&' . ltrim($lm_params, '&');
		$data['has_more']        = isset($product_total) && $product_total > (($page - 1) * $limit + count($data['products']));
		$data['products_loaded'] = ($page - 1) * $limit + count($data['products']);
		$data['text_load_more']  = $this->language->get('text_load_more');
		$data['page']            = $page;

		$this->response->setOutput($this->load->view('product/search', $data));
	}

	/**
	 * AJAX load-more endpoint — works with Manticore search exactly like index().
	 * Returns JSON: { "html": "<product cards>", "count": N, "total": N }
	 */
	public function loadmore() {
		$this->load->language('product/search');
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$this->response->addHeader('Content-Type: application/json');

		$search      = isset($this->request->get['search'])      ? html_entity_decode(html_entity_decode(trim((string)$this->request->get['search']), ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8') : '';
		$tag         = isset($this->request->get['tag'])         ? html_entity_decode(html_entity_decode(trim((string)$this->request->get['tag']), ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8') : (isset($this->request->get['search']) ? $search : '');
		$description = isset($this->request->get['description']) ? $this->request->get['description'] : '';
		$category_id = isset($this->request->get['category_id'])  ? $this->request->get['category_id'] : 0;
		$sub_category = isset($this->request->get['sub_category']) ? $this->request->get['sub_category'] : '';
		$sort        = isset($this->request->get['sort'])        ? $this->request->get['sort']  : 'p.sort_order';
		$order       = isset($this->request->get['order'])       ? $this->request->get['order'] : 'ASC';
		$page        = isset($this->request->get['page'])        ? (int)$this->request->get['page'] : 1;

		if ($page < 1) {
			$page = 1;
		}

		if (isset($this->request->get['limit']) && (int)$this->request->get['limit'] > 0) {
			$limit = (int)$this->request->get['limit'];
		} else {
			$limit = (int)$this->config->get('theme_' . $this->config->get('config_theme') . '_product_limit');
		}

		if (!isset($this->request->get['search']) && !isset($this->request->get['tag'])) {
			$this->response->setOutput(json_encode(array('html' => '', 'count' => 0, 'total' => 0)));
			return;
		}

		$filter_data = array(
			'filter_name'         => $search,
			'filter_tag'          => $tag,
			'filter_description'  => $description,
			'filter_category_id'  => $category_id,
			'filter_sub_category' => $sub_category,
			'sort'                => $sort,
			'order'               => $order,
			'start'               => ($page - 1) * $limit,
			'limit'               => $limit
		);

		$product_total = $this->model_catalog_product->getTotalProducts($filter_data);

		$wishlist_ids = array();
		if ($this->customer->isLogged()) {
			$this->load->model('account/wishlist');
			foreach ($this->model_account_wishlist->getWishlist() as $w) {
				$wishlist_ids[] = (int)$w['product_id'];
			}
		} elseif (isset($this->session->data['wishlist'])) {
			$wishlist_ids = array_map('intval', $this->session->data['wishlist']);
		}

		$results = $this->model_catalog_product->getProducts($filter_data);

		// Honour Manticore override of total count (set by dockercart_search module)
		$manticore_total = $this->registry->get('manticore_search_total');
		if ($manticore_total !== null) {
			$product_total = (int)$manticore_total;
		}

		$url = '';
		if (isset($this->request->get['search'])) {
			$url .= '&search=' . urlencode(html_entity_decode($this->request->get['search'], ENT_QUOTES, 'UTF-8'));
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

			$products[] = array(
				'product_id'  => $result['product_id'],
				'thumb'       => $image,
				'name'        => $result['name'],
				'model'       => $result['model'],
				'description' => utf8_substr(trim(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8'))), 0, $this->config->get('theme_' . $this->config->get('config_theme') . '_product_description_length')) . '..',
				'price'       => $price,
				'special'     => $special,
				'minimum'     => $result['minimum'] > 0 ? $result['minimum'] : 1,
				'rating'      => (int)$result['rating'],
				'reviews'     => isset($result['reviews']) ? (int)$result['reviews'] : 0,
				'in_wishlist' => in_array((int)$result['product_id'], $wishlist_ids) ? 1 : 0,
				'category'    => '',
				'href'        => $this->url->link('product/product', 'product_id=' . $result['product_id'] . $url)
			);
		}

		$html = '';
		foreach ($products as $product) {
			$html .= $this->load->view('product/product_card_ajax', array(
				'product'          => $product,
				'text_quick_view'  => $this->language->get('text_quick_view'),
				'text_reviews'     => $this->language->get('text_reviews_word'),
				'text_sale'        => '',
				'button_cart'      => $this->language->get('button_cart'),
				'btn_quick_hover'  => 'hover:bg-blue-600',
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
