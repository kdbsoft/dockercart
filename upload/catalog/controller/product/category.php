<?php
class ControllerProductCategory extends Controller {
	public function index() {
		$this->load->language('product/category');

		$this->load->model('catalog/category');

		$this->load->model('catalog/product');

		$this->load->model('tool/image');

		if (isset($this->request->get['filter'])) {
			$filter = $this->request->get['filter'];
		} else {
			$filter = '';
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

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		if (isset($this->request->get['path'])) {
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

			$path = '';

			$parts = explode('_', (string)$this->request->get['path']);

			$category_id = (int)array_pop($parts);

			foreach ($parts as $path_id) {
				if (!$path) {
					$path = (int)$path_id;
				} else {
					$path .= '_' . (int)$path_id;
				}

				$category_info = $this->model_catalog_category->getCategory($path_id);

				if ($category_info) {
					$data['breadcrumbs'][] = array(
						'text' => $category_info['name'],
						'href' => $this->url->link('product/category', 'path=' . $path . $url)
					);
				}
			}
		} else {
			$category_id = 0;
		}

		$category_info = $this->model_catalog_category->getCategory($category_id);

		if ($category_info) {
			$this->document->setTitle($category_info['meta_title']);
			$this->document->setDescription($category_info['meta_description']);
			$this->document->setKeywords($category_info['meta_keyword']);

			$data['heading_title'] = $category_info['name'];

			$data['original_category_name'] = $category_info['name'];
			$data['show_refine'] = empty($filter) && empty($this->request->get['dcf']);

			// Prepare "Shop All" URL without filter
			$shop_all_url = '';

			if (isset($this->request->get['sort'])) {
				$shop_all_url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$shop_all_url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['limit'])) {
				$shop_all_url .= '&limit=' . $this->request->get['limit'];
			}

			if (isset($this->request->get['dcf'])) {
				$shop_all_url .= '&dcf=' . $this->request->get['dcf'];
			}

			$data['shop_all_href'] = $this->url->link('product/category', 'path=' . $this->request->get['path'] . $shop_all_url);
			
			// Prepare current URL with all parameters
			$current_url = '';

			if (isset($this->request->get['filter'])) {
				$current_url .= '&filter=' . $this->request->get['filter'];
			}

			if (isset($this->request->get['sort'])) {
				$current_url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$current_url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['limit'])) {
				$current_url .= '&limit=' . $this->request->get['limit'];
			}

			if (isset($this->request->get['dcf'])) {
				$current_url .= '&dcf=' . $this->request->get['dcf'];
			}

			$data['current_href'] = $this->url->link('product/category', 'path=' . $this->request->get['path'] . $current_url);
			$base_href = $this->url->link('product/category', 'path=' . $this->request->get['path']);
			$data['show_shop_all'] = $data['current_href'] !== $base_href;

			// Set the last category breadcrumb
			$data['breadcrumbs'][] = array(
				'text' => $category_info['name'],
				'href' => $this->url->link('product/category', 'path=' . $this->request->get['path'])
			);

			if ($category_info['image']) {
				$data['thumb'] = $this->model_tool_image->resize($category_info['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_category_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_category_height'));
			} else {
				$data['thumb'] = '';
			}

			$data['description'] = html_entity_decode($category_info['description'], ENT_QUOTES, 'UTF-8');
			$data['compare'] = $this->url->link('product/compare');

			$url = '';

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
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

			if (isset($this->request->get['dcf'])) {
				$url .= '&dcf=' . $this->request->get['dcf'];
			}

			$data['categories'] = array();
			$subcategory_ids = array(); // category_id => name map for product labelling

			$results = $this->model_catalog_category->getCategories($category_id);

			foreach ($results as $result) {
				$filter_data = array(
					'filter_category_id'  => $result['category_id'],
					'filter_sub_category' => true
				);

				$category_total = 0;

				if ($this->config->get('config_product_count')) {
					$category_total = (int)$this->model_catalog_product->getTotalProducts($filter_data);
				}

				$subcategory_ids[$result['category_id']] = $result['name'];

			// Prepare a resized thumb for subcategory icons (fallback to first product image, then placeholder)
			if ($result['image']) {
				$sub_thumb = $this->model_tool_image->resize($result['image'], 140, 140);
			} else {
				$first_product_image = $this->model_catalog_category->getFirstProductImageByCategoryId($result['category_id']);
				if (!empty($first_product_image)) {
					$sub_thumb = $this->model_tool_image->resize($first_product_image, 140, 140);
				} else {
					$sub_thumb = $this->model_tool_image->resize('placeholder.png', 140, 140);
				}
			}

			$data['categories'][] = array(
				'name' => $result['name'],
				'total' => $category_total,
				'description' => utf8_substr(trim(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8'))), 0, 100) . '...',
				'href' => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '_' . $result['category_id'] . $url),
				'thumb' => $sub_thumb
			);
			}

			$has_subcategories = !empty($subcategory_ids);

			$filter_data = array(
				'filter_category_id'  => $category_id,
				'filter_sub_category' => $has_subcategories,
				'filter_filter'       => $filter,
				'sort'                => $sort,
				'order'               => $order,
				'start'               => ($page - 1) * $limit,
				'limit'               => $limit
			);

			$product_total = $this->model_catalog_product->getTotalProducts($filter_data);
			$data['product_total'] = $product_total;
			$data['subcategory_total'] = count($data['categories']);
			$data['brand_total'] = $this->getCategoryBrandCount($category_id);

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

			$results = $this->model_catalog_product->getProducts($filter_data);

			$data['products'] = array();

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
					'stock'       => $stock,
					'is_in_stock' => ($stock_quantity > 0),
					'in_wishlist' => in_array((int)$result['product_id'], $wishlist_ids) ? 1 : 0,
					'category'    => '',
					'href'        => $this->url->link('product/product', 'path=' . $this->request->get['path'] . '&product_id=' . $result['product_id'] . $url)
				);
			}

			// If category has subcategories, attach the direct subcategory name to each product (one batch query)
			if (!empty($subcategory_ids) && !empty($data['products'])) {
				$product_ids_list = implode(',', array_map('intval', array_column($data['products'], 'product_id')));
				$subcat_ids_list  = implode(',', array_map('intval', array_keys($subcategory_ids)));
				$subcat_query = $this->db->query(
					"SELECT p2c.product_id, cd.name
					 FROM `" . DB_PREFIX . "product_to_category` p2c
					 LEFT JOIN `" . DB_PREFIX . "category_description` cd
					        ON (p2c.category_id = cd.category_id AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "')
					 WHERE p2c.category_id IN (" . $subcat_ids_list . ")
					   AND p2c.product_id  IN (" . $product_ids_list . ")"
				);
				$product_subcategory = array();
				foreach ($subcat_query->rows as $row) {
					$product_subcategory[(int)$row['product_id']] = $row['name'];
				}
				foreach ($data['products'] as &$prod) {
					if (isset($product_subcategory[$prod['product_id']])) {
						$prod['category'] = $product_subcategory[$prod['product_id']];
					}
				}
				unset($prod);
			}

			$url = '';

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
			}

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

			if (isset($this->request->get['dcf'])) {
				$url .= '&dcf=' . $this->request->get['dcf'];
			}

			$data['sorts'] = array();

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_default'),
				'value' => 'p.sort_order-ASC',
				'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=p.sort_order&order=ASC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_name_asc'),
				'value' => 'pd.name-ASC',
				'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=pd.name&order=ASC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_name_desc'),
				'value' => 'pd.name-DESC',
				'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=pd.name&order=DESC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_price_asc'),
				'value' => 'p.price-ASC',
				'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=p.price&order=ASC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_price_desc'),
				'value' => 'p.price-DESC',
				'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=p.price&order=DESC' . $url)
			);

			if ($this->config->get('config_review_status')) {
				$data['sorts'][] = array(
					'text'  => $this->language->get('text_rating_desc'),
					'value' => 'rating-DESC',
					'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=rating&order=DESC' . $url)
				);

				$data['sorts'][] = array(
					'text'  => $this->language->get('text_rating_asc'),
					'value' => 'rating-ASC',
					'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=rating&order=ASC' . $url)
				);
			}

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_model_asc'),
				'value' => 'p.model-ASC',
				'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=p.model&order=ASC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_model_desc'),
				'value' => 'p.model-DESC',
				'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=p.model&order=DESC' . $url)
			);

			$url = '';

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['dcf'])) {
				$url .= '&dcf=' . $this->request->get['dcf'];
			}

			$data['limits'] = array();

			$limits = array_unique(array($this->config->get('theme_' . $this->config->get('config_theme') . '_product_limit'), 25, 50, 75, 100));

			sort($limits);

			foreach($limits as $value) {
				$data['limits'][] = array(
					'text'  => $value,
					'value' => $value,
					'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . $url . '&limit=' . $value)
				);
			}

			$url = '';

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
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

			if (isset($this->request->get['dcf'])) {
				$url .= '&dcf=' . $this->request->get['dcf'];
			}

			$pagination = new Pagination();
			$pagination->total = $product_total;
			$pagination->page = $page;
			$pagination->limit = $limit;
			$pagination->url = $this->url->link('product/category', 'path=' . $this->request->get['path'] . $url . '&page={page}');

			$data['pagination'] = $pagination->render();

			$data['results'] = sprintf($this->language->get('text_pagination'), ($product_total) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($product_total - $limit)) ? $product_total : ((($page - 1) * $limit) + $limit), $product_total, ceil($product_total / $limit));

			// http://googlewebmastercentral.blogspot.com/2011/09/pagination-with-relnext-and-relprev.html
			if ($page == 1) {
			    $this->document->addLink($this->url->link('product/category', 'path=' . $category_info['category_id']), 'canonical');
			} else {
				$this->document->addLink($this->url->link('product/category', 'path=' . $category_info['category_id'] . '&page='. $page), 'canonical');
			}
			
			if ($page > 1) {
			    $this->document->addLink($this->url->link('product/category', 'path=' . $category_info['category_id'] . (($page - 2) ? '&page='. ($page - 1) : '')), 'prev');
			}

			if ($limit && ceil($product_total / $limit) > $page) {
			    $this->document->addLink($this->url->link('product/category', 'path=' . $category_info['category_id'] . '&page='. ($page + 1)), 'next');
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

			// Additional localized strings used by the category template
			$data['text_subcategories'] = $this->language->get('text_subcategories');
			$data['text_shop_all'] = $this->language->get('text_shop_all');
			$data['text_models'] = $this->language->get('text_models');
			$data['text_products'] = $this->language->get('text_products');
			$data['text_quick_view'] = $this->language->get('text_quick_view');
			$data['text_category_description'] = $this->language->get('text_category_description');

			// Load-more AJAX
			$lm_params = 'path=' . $this->request->get['path'] . '&sort=' . $sort . '&order=' . $order . '&limit=' . $limit;
			if ($filter) { $lm_params .= '&filter=' . urlencode($filter); }
			if (isset($this->request->get['dcf'])) { $lm_params .= '&dcf=' . rawurlencode($this->request->get['dcf']); }
			$data['load_more_url']    = HTTP_SERVER . 'index.php?route=product/category/loadmore&' . $lm_params;
			$data['has_more']         = $product_total > (($page - 1) * $limit + count($data['products']));
			$data['products_loaded']  = ($page - 1) * $limit + count($data['products']);
			$data['text_load_more']   = $this->language->get('text_load_more');
			$data['page']             = $page;
			$data['text_catalog_depth'] = $this->language->get('text_catalog_depth');
			$data['text_catalog_depth_desc'] = $this->language->get('text_catalog_depth_desc');
			$data['text_brand_coverage'] = $this->language->get('text_brand_coverage');
			$data['text_brand_coverage_desc'] = $this->language->get('text_brand_coverage_desc');
			$data['text_support'] = $this->language->get('text_support');
			$data['text_support_desc'] = $this->language->get('text_support_desc');

			$category_feature_defaults = array(
				array(
					'icon' => 'layers-3',
					'title' => $this->language->get('text_catalog_depth'),
					'text' => sprintf($this->language->get('text_catalog_depth_desc'), $data['product_total'], $data['subcategory_total']),
					'sort_order' => 0
				),
				array(
					'icon' => 'badge-check',
					'title' => $this->language->get('text_brand_coverage'),
					'text' => sprintf($this->language->get('text_brand_coverage_desc'), $data['brand_total']),
					'sort_order' => 1
				),
				array(
					'icon' => 'headset',
					'title' => $this->language->get('text_support'),
					'text' => $this->language->get('text_support_desc'),
					'sort_order' => 2
				)
			);
			$data['category_features'] = $this->resolveThemeFeatures('dockercart_theme_category_features', $category_feature_defaults);
			foreach ($data['category_features'] as &$category_feature) {
				if (!empty($category_feature['text']) && substr_count($category_feature['text'], '%s') >= 2) {
					$category_feature['text'] = sprintf($category_feature['text'], $data['product_total'], $data['subcategory_total']);
				} elseif (!empty($category_feature['text']) && substr_count($category_feature['text'], '%s') === 1) {
					$category_feature['text'] = sprintf($category_feature['text'], $data['brand_total']);
				}
			}
			unset($category_feature);

			$this->response->setOutput($this->load->view('product/category', $data));
		} else {
			$url = '';

			if (isset($this->request->get['path'])) {
				$url .= '&path=' . $this->request->get['path'];
			}

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
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

			if (isset($this->request->get['dcf'])) {
				$url .= '&dcf=' . $this->request->get['dcf'];
			}

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_error'),
				'href' => $this->url->link('product/category', $url)
			);

			$this->document->setTitle($this->language->get('text_error'));

			$data['continue'] = '/';

			$this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 404 Not Found');

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			$this->response->setOutput($this->load->view('error/not_found', $data));
		}
	}

	private function resolveThemeFeatures($setting_key, $defaults = array()) {
		$raw_value = $this->config->get($setting_key);

		if (!is_string($raw_value) || $raw_value === '') {
			return $defaults;
		}

		$decoded = json_decode($raw_value, true);
		if (!is_array($decoded)) {
			return $defaults;
		}

		$language_id = (int)$this->config->get('config_language_id');
		$features = array();

		foreach ($decoded as $feature) {
			if (!is_array($feature)) {
				continue;
			}

			$icon = isset($feature['icon']) ? (string)$feature['icon'] : 'truck';
			if (!preg_match('/^[a-z0-9\-]+$/', $icon)) {
				$icon = 'truck';
			}

			$title = '';
			if (isset($feature['title']) && is_array($feature['title'])) {
				if (isset($feature['title'][$language_id]) && trim((string)$feature['title'][$language_id]) !== '') {
					$title = trim((string)$feature['title'][$language_id]);
				} else {
					foreach ($feature['title'] as $title_candidate) {
						$title_candidate = trim((string)$title_candidate);
						if ($title_candidate !== '') {
							$title = $title_candidate;
							break;
						}
					}
				}
			}

			$text = '';
			if (isset($feature['text']) && is_array($feature['text'])) {
				if (isset($feature['text'][$language_id]) && trim((string)$feature['text'][$language_id]) !== '') {
					$text = trim((string)$feature['text'][$language_id]);
				} else {
					foreach ($feature['text'] as $text_candidate) {
						$text_candidate = trim((string)$text_candidate);
						if ($text_candidate !== '') {
							$text = $text_candidate;
							break;
						}
					}
				}
			}

			if ($title === '' && $text === '') {
				continue;
			}

			$features[] = array(
				'icon' => $icon,
				'title' => $title,
				'text' => $text,
				'sort_order' => isset($feature['sort_order']) ? (int)$feature['sort_order'] : 0
			);
		}

		usort($features, function($a, $b) {
			return (int)$a['sort_order'] <=> (int)$b['sort_order'];
		});

		return $features ? $features : $defaults;
	}

	private function getCategoryBrandCount($category_id) {
		$category_ids = $this->getCategoryTreeIds((int)$category_id);

		if (!$category_ids) {
			$category_ids = array((int)$category_id);
		}

		$category_ids = array_values(array_unique(array_map('intval', $category_ids)));
		$category_ids_sql = implode(',', $category_ids);

		$query = $this->db->query(
			"SELECT COUNT(DISTINCT p.manufacturer_id) AS total
			 FROM `" . DB_PREFIX . "product` p
			 LEFT JOIN `" . DB_PREFIX . "product_to_category` p2c ON (p.product_id = p2c.product_id)
			 LEFT JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p.product_id = p2s.product_id)
			 WHERE p.status = '1'
			   AND p.date_available <= NOW()
			   AND p.manufacturer_id > 0
			   AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'
			   AND p2c.category_id IN (" . $category_ids_sql . ")"
		);

		return isset($query->row['total']) ? (int)$query->row['total'] : 0;
	}

	private function getCategoryTreeIds($category_id) {
		$category_id = (int)$category_id;
		$ids = array($category_id);

		$children = $this->model_catalog_category->getCategories($category_id);
		foreach ($children as $child) {
			$ids = array_merge($ids, $this->getCategoryTreeIds((int)$child['category_id']));
		}

		return $ids;
	}

	/**
	 * AJAX load-more endpoint.
	 * Returns JSON: { "html": "<product cards>", "count": N, "total": N }
	 */
	public function loadmore() {
		$this->load->language('product/category');
		$this->load->model('catalog/category');
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$this->response->addHeader('Content-Type: application/json');

		$filter = isset($this->request->get['filter']) ? $this->request->get['filter'] : '';
		$sort   = isset($this->request->get['sort'])   ? $this->request->get['sort']   : 'p.sort_order';
		$order  = isset($this->request->get['order'])  ? $this->request->get['order']  : 'ASC';
		$page   = isset($this->request->get['page'])   ? (int)$this->request->get['page'] : 1;

		if ($page < 1) {
			$page = 1;
		}

		if (isset($this->request->get['limit']) && (int)$this->request->get['limit'] > 0) {
			$limit = (int)$this->request->get['limit'];
		} else {
			$limit = (int)$this->config->get('theme_' . $this->config->get('config_theme') . '_product_limit');
		}

		if (isset($this->request->get['path'])) {
			$parts       = explode('_', (string)$this->request->get['path']);
			$category_id = (int)array_pop($parts);
		} else {
			$category_id = 0;
		}

		$category_info = $this->model_catalog_category->getCategory($category_id);

		if (!$category_info) {
			$this->response->setOutput(json_encode(array('html' => '', 'count' => 0, 'total' => 0)));
			return;
		}

		$subcategories    = $this->model_catalog_category->getCategories($category_id);
		$has_subcategories = !empty($subcategories);

		$filter_data = array(
			'filter_category_id'  => $category_id,
			'filter_sub_category' => $has_subcategories,
			'filter_filter'       => $filter,
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

		$results  = $this->model_catalog_product->getProducts($filter_data);
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
				$special   = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				$tax_price = (float)$result['special'];
			} else {
				$special   = false;
				$tax_price = (float)$result['price'];
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
				'stock'       => $stock,
				'is_in_stock' => ($stock_quantity > 0),
				'in_wishlist' => in_array((int)$result['product_id'], $wishlist_ids) ? 1 : 0,
				'category'    => '',
				'href'        => $this->url->link('product/product', 'path=' . (isset($this->request->get['path']) ? $this->request->get['path'] : '') . '&product_id=' . $result['product_id'])
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
