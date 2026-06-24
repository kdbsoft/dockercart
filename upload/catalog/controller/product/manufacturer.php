<?php
class ControllerProductManufacturer extends Controller {
	public function index() {
		$this->load->language('product/manufacturer');

		// Additional labels
		$data['text_browse_by_brand'] = $this->language->get('text_browse_by_brand') ?: 'Browse By Brand';
		$data['text_no_results'] = $this->language->get('text_no_results');
		$data['text_brand'] = $this->language->get('text_brand');
		$data['text_products'] = $this->language->get('text_products');

		$this->load->model('catalog/manufacturer');

		$this->load->model('tool/image');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_empty']    = $this->language->get('text_empty');

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_brand'),
			'href' => $this->url->link('product/manufacturer')
		);

		$data['categories'] = array();

		$results = $this->model_catalog_manufacturer->getManufacturers();

		foreach ($results as $result) {
			if (is_numeric(utf8_substr($result['name'], 0, 1))) {
				$key = '0 - 9';
			} else {
				$key = utf8_substr(utf8_strtoupper($result['name']), 0, 1);
			}

			if (!isset($data['categories'][$key])) {
				$data['categories'][$key]['name'] = $key;
			}

			// Generate logo thumbnail if image exists
			$thumb = '';
			if (!empty($result['image'])) {
				$thumb = $this->model_tool_image->resize($result['image'], 150, 80);
			}

			$data['categories'][$key]['manufacturer'][] = array(
				'name' => $result['name'],
				'image' => !empty($result['image']) ? $result['image'] : '',
				'thumb' => $thumb,
				'href' => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $result['manufacturer_id'])
			);
		}

		// Sort categories alphabetically with numbers at the end
		$sort_order = array();
		foreach ($data['categories'] as $key => $value) {
			$sort_order[$key] = ($key === '0 - 9') ? 'zzz_numbers' : $key;
		}
		asort($sort_order);
		
		$sorted_categories = array();
		foreach ($sort_order as $key => $value) {
			$sorted_categories[$key] = $data['categories'][$key];
		}
		$data['categories'] = $sorted_categories;

		$data['continue'] = '/';

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('product/manufacturer_list', $data));
	}

	public function info() {
		$this->load->language('product/manufacturer');
		$this->load->language('product/product');

		$this->load->model('catalog/manufacturer');

		$this->load->model('catalog/product');

		$this->load->model('catalog/category');

		$this->load->model('tool/image');

		if (isset($this->request->get['manufacturer_id'])) {
			$manufacturer_id = (int)$this->request->get['manufacturer_id'];
		} else {
			$manufacturer_id = 0;
		}

		$category_id = 0;
		if (isset($this->request->get['category_id'])) {
			$category_id = (int)$this->request->get['category_id'];
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
			$limit = (int)$this->config->get('theme_' . $this->config->get('config_theme') . '_product_limit');
		}

		$view_mode = 'grid';
		if (isset($this->request->cookie['dc_view_mode']) && in_array($this->request->cookie['dc_view_mode'], array('grid', 'list', 'table'))) {
			$view_mode = $this->request->cookie['dc_view_mode'];
		} elseif (isset($this->request->get['view']) && in_array($this->request->get['view'], array('grid', 'list', 'table'))) {
			$view_mode = $this->request->get['view'];
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_brand'),
			'href' => $this->url->link('product/manufacturer')
		);

		$manufacturer_info = $this->model_catalog_manufacturer->getManufacturer($manufacturer_id);

		if ($manufacturer_info) {
			// Get manufacturer description for current language
			$manufacturer_description = $this->model_catalog_manufacturer->getManufacturerDescription($manufacturer_id);

			if ($manufacturer_description) {
				// Set meta tags from description table
				if (!empty($manufacturer_description['meta_title'])) {
					$this->document->setTitle($manufacturer_description['meta_title']);
				} else {
					$this->document->setTitle($manufacturer_info['name']);
				}
				
				if (!empty($manufacturer_description['meta_description'])) {
					$this->document->setDescription($manufacturer_description['meta_description']);
				}
				
				if (!empty($manufacturer_description['meta_keyword'])) {
					$this->document->setKeywords($manufacturer_description['meta_keyword']);
				}
				
				$data['heading_title'] = $manufacturer_description['name'];
				$data['description'] = html_entity_decode($manufacturer_description['description'], ENT_QUOTES, 'UTF-8');
			} else {
				// Fallback to manufacturer table
				$this->document->setTitle($manufacturer_info['name']);
				$data['heading_title'] = $manufacturer_info['name'];
				$data['description'] = '';
			}

			// Manufacturer logo for hero banner
			if (!empty($manufacturer_info['image'])) {
				$data['thumb'] = $this->model_tool_image->resize($manufacturer_info['image'], 1440, 400);
			} else {
				$data['thumb'] = '';
			}

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

			if ($category_id) {
				$url .= '&category_id=' . $category_id;
			}

			$data['breadcrumbs'][] = array(
				'text' => $manufacturer_info['name'],
				'href' => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $this->request->get['manufacturer_id'] . $url)
			);

			$data['text_compare'] = sprintf($this->language->get('text_compare'), (isset($this->session->data['compare']) ? count($this->session->data['compare']) : 0));

			// Ensure labels for info page
			$data['text_brand'] = $this->language->get('text_brand');
			$data['text_about_brand'] = $this->language->get('text_about_brand') ?: 'About this brand';
			$data['text_products'] = $this->language->get('text_products');
			$data['text_view_grid'] = $this->language->get('text_view_grid');
			$data['text_view_list'] = $this->language->get('text_view_list');
			$data['text_view_table'] = $this->language->get('text_view_table');
			$data['text_quick_view'] = $this->language->get('text_quick_view');

			// Call for price
			$data['call_for_price_status'] = (int)$this->config->get('dockercart_theme_call_for_price_status');
			$data['call_for_price_phone'] = $this->config->get('config_telephone');

			$data['compare'] = $this->url->link('product/compare');

			$data['products'] = array();

			$filter_data = array(
				'filter_manufacturer_id' => $manufacturer_id,
				'sort'                   => $sort,
				'order'                  => $order,
				'start'                  => ($page - 1) * $limit,
				'limit'                  => $limit
			);

			if ($category_id) {
				$filter_data['filter_category_id'] = $category_id;
				$filter_data['filter_sub_category'] = true;
			}

			$product_total = $this->model_catalog_product->getTotalProducts($filter_data);
			$data['product_total'] = $product_total;

			$this->load->helper('plural');
			$lang_code = $this->language->get('code');
			$data['text_product_count'] = product_count_label($product_total, $lang_code);

			// Refine Search: build category cards for this manufacturer
			$data['refine_categories'] = array();
			$data['refine_category_name'] = '';
			$data['refine_parent_href'] = '';
			$data['refine_parent_name'] = '';
			$data['category_id'] = $category_id;

			$all_manufacturer_product_ids = $this->getCachedManufacturerProductIds($manufacturer_id);

			if (!empty($all_manufacturer_product_ids)) {
				$category_counts = $this->model_catalog_category->getCategoryProductCounts($all_manufacturer_product_ids);

				if (!empty($category_counts)) {
					$category_ids = array_keys($category_counts);
					$categories_info = $this->model_catalog_category->getCategoriesByIds($category_ids);

					$top_level_ids = array();
					foreach ($categories_info as $cat) {
						if ((int)$cat['parent_id'] === 0) {
							$top_level_ids[] = (int)$cat['category_id'];
						}
					}

					$refine_ids = array();
					if ($category_id === 0) {
						$refine_ids = $top_level_ids;
					} else {
						foreach ($categories_info as $cat) {
							if ((int)$cat['parent_id'] === $category_id) {
								$refine_ids[] = (int)$cat['category_id'];
							}
						}
					}

					if ($category_id > 0) {
						if (isset($categories_info[$category_id])) {
							$data['refine_category_name'] = $categories_info[$category_id]['name'];
						} else {
							$cat_info = $this->model_catalog_category->getCategory($category_id);
							if ($cat_info) {
								$data['refine_category_name'] = $cat_info['name'];
							}
						}

						$back_url = 'manufacturer_id=' . $manufacturer_id;
						if (isset($this->request->get['sort'])) {
							$back_url .= '&sort=' . $this->request->get['sort'];
						}
						if (isset($this->request->get['order'])) {
							$back_url .= '&order=' . $this->request->get['order'];
						}
						if (isset($this->request->get['limit'])) {
							$back_url .= '&limit=' . $this->request->get['limit'];
						}
						$data['refine_parent_href'] = $this->url->link('product/manufacturer/info', $back_url);
						$data['refine_parent_name'] = $manufacturer_info['name'];
					}

					$refine_data = array();
					foreach ($refine_ids as $cat_id) {
						if (!isset($categories_info[$cat_id])) {
							continue;
						}

						$cat = $categories_info[$cat_id];
						$total = isset($category_counts[$cat_id]) ? (int)$category_counts[$cat_id] : 0;

						if ($cat['image']) {
							$thumb = $this->model_tool_image->resize($cat['image'], 140, 140);
						} else {
							$first_image = $this->model_catalog_category->getFirstProductImageByCategoryId($cat_id);
							if (!empty($first_image)) {
								$thumb = $this->model_tool_image->resize($first_image, 140, 140);
							} else {
								$thumb = '';
							}
						}

						$refine_url = 'manufacturer_id=' . $manufacturer_id . '&category_id=' . $cat_id;
						if (isset($this->request->get['sort'])) {
							$refine_url .= '&sort=' . $this->request->get['sort'];
						}
						if (isset($this->request->get['order'])) {
							$refine_url .= '&order=' . $this->request->get['order'];
						}
						if (isset($this->request->get['limit'])) {
							$refine_url .= '&limit=' . $this->request->get['limit'];
						}

						$refine_data[] = array(
							'category_id'   => $cat_id,
							'name'          => $cat['name'],
							'total'         => $total,
							'product_label' => product_count_label($total, $lang_code),
							'thumb'         => $thumb,
							'href'          => $this->url->link('product/manufacturer/info', $refine_url)
						);
					}

					usort($refine_data, function($a, $b) {
						return $b['total'] - $a['total'];
					});

					$data['refine_categories'] = $refine_data;
				}
			}

			$data['text_refine'] = $this->language->get('text_refine');
			$data['text_refine_categories'] = $this->language->get('text_refine_categories');
			$data['text_back_to'] = $this->language->get('text_back_to');
			$data['text_all_brands'] = $this->language->get('text_all_brands');

			$results = $this->model_catalog_product->getProducts($filter_data);

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

				$discount_percent = 0;
				if (!is_null($result['special']) && (float)$result['price'] > 0) {
					$discount_percent = (int) round((1 - ((float)$result['special'] / (float)$result['price'])) * 100);
					if ($discount_percent < 0) { $discount_percent = 0; }
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
				$stock = !empty($result['preorder'])
					? $this->language->get('text_preorder')
					: $this->language->get('text_out_of_stock');
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
					'description' => utf8_substr(trim(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8'))), 0, $this->config->get('theme_' . $this->config->get('config_theme') . '_product_description_length')) . '..',
					'price'       => $price,
					'price_raw'   => (float)$result['price'],
					'special'     => $special,
					'discount'    => $discount_percent,
					'tax'         => $tax,
					'minimum'     => $result['minimum'] > 0 ? $result['minimum'] : 1,
					'rating'      => $result['rating'],
					'reviews'     => isset($result['reviews']) ? $result['reviews'] : 0,
					'stock'       => $stock,
					'is_in_stock' => ($stock_quantity > 0) || !empty($result['preorder']),
					'is_preorder' => empty($stock_quantity) && !empty($result['preorder']),
					'has_gift'    => !empty($result['has_gift']),
					'call_for_price' => !empty($result['call_for_price']),
					'href'        => $this->url->link('product/product', 'manufacturer_id=' . $result['manufacturer_id'] . '&product_id=' . $result['product_id'] . $url)
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
				'href'  => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $this->request->get['manufacturer_id'] . '&sort=p.sort_order&order=ASC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_name_asc'),
				'value' => 'pd.name-ASC',
				'href'  => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $this->request->get['manufacturer_id'] . '&sort=pd.name&order=ASC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_name_desc'),
				'value' => 'pd.name-DESC',
				'href'  => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $this->request->get['manufacturer_id'] . '&sort=pd.name&order=DESC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_price_asc'),
				'value' => 'p.price-ASC',
				'href'  => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $this->request->get['manufacturer_id'] . '&sort=p.price&order=ASC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_price_desc'),
				'value' => 'p.price-DESC',
				'href'  => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $this->request->get['manufacturer_id'] . '&sort=p.price&order=DESC' . $url)
			);

			if ($this->config->get('config_review_status')) {
				$data['sorts'][] = array(
					'text'  => $this->language->get('text_rating_desc'),
					'value' => 'rating-DESC',
					'href'  => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $this->request->get['manufacturer_id'] . '&sort=rating&order=DESC' . $url)
				);

				$data['sorts'][] = array(
					'text'  => $this->language->get('text_rating_asc'),
					'value' => 'rating-ASC',
					'href'  => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $this->request->get['manufacturer_id'] . '&sort=rating&order=ASC' . $url)
				);
			}

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_model_asc'),
				'value' => 'p.model-ASC',
				'href'  => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $this->request->get['manufacturer_id'] . '&sort=p.model&order=ASC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_model_desc'),
				'value' => 'p.model-DESC',
				'href'  => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $this->request->get['manufacturer_id'] . '&sort=p.model&order=DESC' . $url)
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
					'href'  => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $this->request->get['manufacturer_id'] . $url . '&limit=' . $value)
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
			$pagination->url = $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $this->request->get['manufacturer_id'] .  $url . '&page={page}');

			$data['pagination'] = $pagination->render();

			$data['results'] = sprintf($this->language->get('text_pagination'), ($product_total) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($product_total - $limit)) ? $product_total : ((($page - 1) * $limit) + $limit), $product_total, ceil($product_total / $limit));

			// http://googlewebmastercentral.blogspot.com/2011/09/pagination-with-relnext-and-relprev.html
			if ($page == 1) {
				$this->document->addLink($this->url->link('product/manufacturer/info', 'manufacturer_id=' . $this->request->get['manufacturer_id']), 'canonical');
			} else {
				$this->document->addLink($this->url->link('product/manufacturer/info', 'manufacturer_id=' . $this->request->get['manufacturer_id'] . '&page=' . $page), 'canonical');
			}
			
			if ($page > 1) {
				$this->document->addLink($this->url->link('product/manufacturer/info', 'manufacturer_id=' . $this->request->get['manufacturer_id'] . (($page - 2) ? '&page=' . ($page - 1) : '')), 'prev');
			}

			if ($limit && ceil($product_total / $limit) > $page) {
				$this->document->addLink($this->url->link('product/manufacturer/info', 'manufacturer_id=' . $this->request->get['manufacturer_id'] . '&page=' . ($page + 1)), 'next');
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
			$data['text_model'] = $this->language->get('text_model');
			$data['text_quantity'] = $this->language->get('text_quantity');

			// Load-more AJAX
			$lm_params = 'manufacturer_id=' . $manufacturer_id . '&sort=' . $sort . '&order=' . $order . '&limit=' . $limit;
			$data['load_more_url']   = HTTP_SERVER . 'index.php?route=product/manufacturer/loadmore&' . $lm_params;
			$data['has_more']        = $product_total > (($page - 1) * $limit + count($data['products']));
			$data['products_loaded'] = ($page - 1) * $limit + count($data['products']);
			$data['text_load_more']  = $this->language->get('text_load_more');
			$data['page']            = $page;
			$data['text_gift_badge'] = $this->language->get('text_gift_badge');
			$data['text_call_for_price'] = $this->language->get('text_call_for_price');
			$data['view_mode']       = $view_mode;

				$this->response->setOutput($this->load->view('product/manufacturer_info', $data));
		} else {
			$url = '';

			if (isset($this->request->get['manufacturer_id'])) {
				$url .= '&manufacturer_id=' . $this->request->get['manufacturer_id'];
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
				'text' => $this->language->get('text_error'),
				'href' => $this->url->link('product/manufacturer/info', $url)
			);

			$this->document->setTitle($this->language->get('text_error'));

			$data['heading_title'] = $this->language->get('text_error');

			$data['text_error'] = $this->language->get('text_error');

			$data['continue'] = '/';

			$this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 404 Not Found');

			$data['header'] = $this->load->controller('common/header');
			$data['footer'] = $this->load->controller('common/footer');
			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');

			$this->response->setOutput($this->load->view('error/not_found', $data));
		}
	}

	/**
	 * AJAX load-more endpoint for brand/manufacturer product pages.
	 * Returns JSON: { "html": "<product cards>", "count": N, "total": N }
	 */
	public function loadmore() {
		$this->load->language('product/manufacturer');
		$this->load->model('catalog/manufacturer');
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$this->response->addHeader('Content-Type: application/json');

		$manufacturer_id = isset($this->request->get['manufacturer_id']) ? (int)$this->request->get['manufacturer_id'] : 0;
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

		$view_mode = 'grid';
		if (isset($this->request->cookie['dc_view_mode']) && in_array($this->request->cookie['dc_view_mode'], array('grid', 'list', 'table'))) {
			$view_mode = $this->request->cookie['dc_view_mode'];
		} elseif (isset($this->request->get['view']) && in_array($this->request->get['view'], array('grid', 'list', 'table'))) {
			$view_mode = $this->request->get['view'];
		}

		$manufacturer_info = $this->model_catalog_manufacturer->getManufacturer($manufacturer_id);
		if (!$manufacturer_info) {
			$this->response->setOutput(json_encode(array('html' => '', 'count' => 0, 'total' => 0)));
			return;
		}

		$filter_data = array(
			'filter_manufacturer_id' => $manufacturer_id,
			'sort'                   => $sort,
			'order'                  => $order,
			'start'                  => ($page - 1) * $limit,
			'limit'                  => $limit
		);

		$product_total = $this->model_catalog_product->getTotalProducts($filter_data);
		$results       = $this->model_catalog_product->getProducts($filter_data);

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

			$discount_percent = 0;
			if (!is_null($result['special']) && (float)$result['price'] > 0) {
				$discount_percent = (int) round((1 - ((float)$result['special'] / (float)$result['price'])) * 100);
				if ($discount_percent < 0) { $discount_percent = 0; }
			}

			$stock_quantity = (int)($result['quantity'] ?? 0);

			if ($stock_quantity <= 0) {
				$stock = !empty($result['preorder'])
					? $this->language->get('text_preorder')
					: $this->language->get('text_out_of_stock');
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
				'model'       => isset($result['model']) ? $result['model'] : '',
				'description' => utf8_substr(trim(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8'))), 0, $this->config->get('theme_' . $this->config->get('config_theme') . '_product_description_length')) . '..',
				'category'    => '',
				'price'       => $price,
				'price_raw'   => (float)$result['price'],
				'special'     => $special,
				'discount'    => $discount_percent,
				'minimum'     => $result['minimum'] > 0 ? $result['minimum'] : 1,
				'rating'      => (int)$result['rating'],
				'reviews'     => isset($result['reviews']) ? (int)$result['reviews'] : 0,
				'stock'       => $stock,
				'is_in_stock' => ($stock_quantity > 0) || !empty($result['preorder']),
				'is_preorder' => empty($stock_quantity) && !empty($result['preorder']),
				'in_wishlist' => in_array((int)$result['product_id'], $wishlist_ids) ? 1 : 0,
				'has_gift'    => !empty($result['has_gift']),
				'href'        => $this->url->link('product/product', 'manufacturer_id=' . $result['manufacturer_id'] . '&product_id=' . $result['product_id'])
			);
		}

		$html = '';
		foreach ($products as $product) {
			$html .= $this->load->view('product/product_card_ajax', array(
				'product'          => $product,
				'view_mode'        => $view_mode,
				'text_quick_view'  => $this->language->get('text_quick_view'),
				'text_reviews'     => $this->language->get('text_reviews_word'),
				'text_sale'        => 'SALE',
				'text_gift_badge'  => $this->language->get('text_gift_badge'),
				'text_call_for_price' => $this->language->get('text_call_for_price'),
				'button_cart'      => $this->language->get('button_cart'),
				'btn_quick_hover'  => 'hover:bg-blue-600',
				'link_hover'       => 'hover:text-blue-600 transition',
				'btn_cart_classes' => 'bg-blue-600 text-white hover:bg-blue-700',
				'call_for_price_status' => (int)$this->config->get('dockercart_theme_call_for_price_status'),
				'call_for_price_phone' => $this->config->get('config_telephone'),
			));
		}

		$this->response->setOutput(json_encode(array(
			'html'  => $html,
			'count' => count($products),
			'total' => $product_total,
		)));
	}

	private function getCachedManufacturerProductIds($manufacturer_id) {
		$manufacturer_id = (int)$manufacturer_id;
		$cache_key = 'manufacturer.product_ids.' . (int)$this->config->get('config_store_id') . '.' . (int)$this->config->get('config_language_id') . '.' . $manufacturer_id;
		$product_ids = $this->cache->get($cache_key);

		if (is_array($product_ids)) {
			return $product_ids;
		}

		$query = $this->db->query("SELECT p.product_id FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE p.manufacturer_id = '" . $manufacturer_id . "' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'");

		$product_ids = array();
		foreach ($query->rows as $row) {
			$product_ids[] = (int)$row['product_id'];
		}

		$this->cache->set($cache_key, $product_ids, 1800);

		return $product_ids;
	}
}
