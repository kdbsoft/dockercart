<?php
class ControllerProductProduct extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('product/product');

		$data['breadcrumbs'] = array();
		$data['current_category_id'] = 0;

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$this->load->model('catalog/category');

		if (isset($this->request->get['path'])) {
			$path = '';

			$parts = explode('_', (string)$this->request->get['path']);

			// For each part in path, add breadcrumb
			foreach ($parts as $path_id) {
				if (!$path) {
					$path = $path_id;
				} else {
					$path .= '_' . $path_id;
				}

				$category_info = $this->model_catalog_category->getCategory($path_id);

				if ($category_info) {
					$breadcrumb_text = $category_info['name'];
					$breadcrumb_href = $this->url->link('product/category', 'path=' . $path);

					// Check if this breadcrumb already exists to avoid duplicates
					$breadcrumb_exists = false;
					foreach ($data['breadcrumbs'] as $bc) {
						if ($bc['text'] === $breadcrumb_text && $bc['href'] === $breadcrumb_href) {
							$breadcrumb_exists = true;
							break;
						}
					}

					if (!$breadcrumb_exists) {
						$data['breadcrumbs'][] = array(
							'text' => $breadcrumb_text,
							'href' => $breadcrumb_href
						);
					}

					$data['current_category_id'] = $path_id;
				}
			}
		}

		$this->load->model('catalog/manufacturer');

		if (isset($this->request->get['manufacturer_id'])) {
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_brand'),
				'href' => $this->url->link('product/manufacturer')
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

			$manufacturer_info = $this->model_catalog_manufacturer->getManufacturer($this->request->get['manufacturer_id']);

			if ($manufacturer_info) {
				$data['breadcrumbs'][] = array(
					'text' => $manufacturer_info['name'],
					'href' => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $this->request->get['manufacturer_id'] . $url)
				);
			}
		}

		if (isset($this->request->get['search']) || isset($this->request->get['tag'])) {
			$url = '';

			if (isset($this->request->get['search'])) {
				$url .= '&search=' . $this->request->get['search'];
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
				'text' => $this->language->get('text_search'),
				'href' => $this->url->link('product/search', $url)
			);
		}

		if (isset($this->request->get['product_id'])) {
			$product_id = (int)$this->request->get['product_id'];
		} else {
			$product_id = 0;
		}

		$this->load->model('catalog/product');

		$product_info = $this->model_catalog_product->getProduct($product_id);

		// If path is provided but the product does not belong to any of the
		// categories in the path, ignore the path instead of refusing to show
		// the product page. This allows direct links with irrelevant `path`
		// parameters to still open the product.
		if (isset($this->request->get['path'])) {
			$parts = explode('_', (string)$this->request->get['path']);

			if (empty($this->model_catalog_product->checkProductCategory($product_id, $parts))) {
				// Do not clear $product_info (which would cause a 404).
				// Remove the invalid path so breadcrumbs/url building won't use it.
				unset($this->request->get['path']);
				// Reset breadcrumbs to just the home link to avoid duplicates
				$data['breadcrumbs'] = array(
					array(
						'text' => $this->language->get('text_home'),
						'href' => $this->url->link('common/home')
					)
				);
			}
		}

		//check product page open from manufacturer page
		if (isset($this->request->get['manufacturer_id']) && !empty($product_info)) {
			if($product_info['manufacturer_id'] !=  $this->request->get['manufacturer_id']) {
				$product_info = array();
			}
		}

		if ($product_info) {
			$wishlist_ids = array();
			if ($this->customer->isLogged()) {
				$this->load->model('account/wishlist');
				foreach ($this->model_account_wishlist->getWishlist() as $w) {
					$wishlist_ids[] = (int)$w['product_id'];
				}
			} elseif (isset($this->session->data['wishlist'])) {
				$wishlist_ids = array_map('intval', $this->session->data['wishlist']);
			}

			$url = '';

			if (isset($this->request->get['path'])) {
				$url .= '&path=' . $this->request->get['path'];
			} else {
				// If no path provided, try to get from product's first category
				$product_categories = $this->model_catalog_product->getCategories($product_id);

				if (!empty($product_categories)) {
					// Get the path for the first category
					$first_category_id = $product_categories[0]['category_id'];
					$category_path = $this->getCategoryPath($first_category_id);

					if ($category_path) {
						$this->request->get['path'] = $category_path;
						$url .= '&path=' . $category_path;

						// Rebuild breadcrumbs with category path
						$path = '';
						$path_parts = explode('_', $category_path);

						foreach ($path_parts as $path_id) {
							if (!$path) {
								$path = $path_id;
							} else {
								$path .= '_' . $path_id;
							}

							$category_info = $this->model_catalog_category->getCategory($path_id);

							if ($category_info) {
								// Check if this breadcrumb already exists to avoid duplicates
								$breadcrumb_text = $category_info['name'];
								$breadcrumb_href = $this->url->link('product/category', 'path=' . $path);
								$breadcrumb_exists = false;

								foreach ($data['breadcrumbs'] as $bc) {
									if ($bc['text'] === $breadcrumb_text && $bc['href'] === $breadcrumb_href) {
										$breadcrumb_exists = true;
										break;
									}
								}

								if (!$breadcrumb_exists) {
									$data['breadcrumbs'][] = array(
										'text' => $breadcrumb_text,
										'href' => $breadcrumb_href
									);
								}

								$data['current_category_id'] = $path_id;
							}
						}
					}
				}
			}

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
			}

			if (isset($this->request->get['manufacturer_id'])) {
				$url .= '&manufacturer_id=' . $this->request->get['manufacturer_id'];
			}

			if (isset($this->request->get['search'])) {
				$url .= '&search=' . $this->request->get['search'];
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
				'text' => $product_info['name'],
				'href' => ''
			);

			$this->document->setTitle($product_info['meta_title']);
			$this->document->setDescription($product_info['meta_description']);
			$this->document->setKeywords($product_info['meta_keyword']);
			$this->document->addLink($this->url->link('product/product', 'product_id=' . $product_id), 'canonical');

			$data['heading_title'] = $product_info['name'];

			$minimum_quantity = isset($product_info['minimum']) ? (float)$product_info['minimum'] : 1;

			if ($minimum_quantity <= 0) {
				$minimum_quantity = 1;
			}

			$data['minimum'] = $this->formatQuantityValue($minimum_quantity);
			$data['text_minimum'] = sprintf($this->language->get('text_minimum'), $data['minimum']);

			$quantity_step = isset($product_info['quantity_step']) ? (float)$product_info['quantity_step'] : 1;

			if ($quantity_step <= 0) {
				$quantity_step = 1;
			}

			$data['quantity_step'] = $this->formatQuantityValue($quantity_step);
			$data['text_quantity_step'] = sprintf($this->language->get('text_quantity_step'), $data['quantity_step']);
			$data['text_login'] = sprintf($this->language->get('text_login'), $this->url->link('account/login', '', true), $this->url->link('account/register', '', true));
			// Localized label for the image zoom hint
			$data['text_zoom'] = $this->language->get('text_zoom');

			$this->load->model('catalog/review');

			$data['tab_review'] = sprintf($this->language->get('tab_review'), $product_info['reviews']);

			$data['product_id'] = $product_id;
			$data['schema_product_url'] = $this->url->link('product/product', 'product_id=' . $product_id);
			$data['in_wishlist'] = in_array($product_id, $wishlist_ids) ? 1 : 0;
			$data['in_compare'] = isset($this->session->data['compare']) && in_array($product_id, $this->session->data['compare']) ? 1 : 0;

			// Currency symbols for client-side formatting
			$display_currency = isset($this->session->data['currency']) ? $this->session->data['currency'] : $this->config->get('config_currency');
			$data['currency_symbol_left'] = $this->currency->getSymbolLeft($display_currency);
			$data['currency_symbol_right'] = $this->currency->getSymbolRight($display_currency);
			$data['currency_code'] = $display_currency;
			$data['currency_decimal_place'] = $this->currency->getDecimalPlace($display_currency);
			$data['config_symbol_left_space'] = (int)$this->config->get('config_symbol_left_space');
			$data['manufacturer'] = $product_info['manufacturer'];
			$data['manufacturers'] = $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $product_info['manufacturer_id']);
			$data['model'] = $product_info['model'];
			$data['reward'] = $product_info['reward'];
			$data['points'] = $product_info['points'];
			$data['description'] = html_entity_decode($product_info['description'], ENT_QUOTES, 'UTF-8');

			if ($product_info['quantity'] <= 0) {
				$data['stock'] = $product_info['preorder']
					? $this->language->get('text_preorder')
					: $this->language->get('text_out_of_stock');
			} elseif ($this->config->get('config_stock_display')) {
				$data['stock'] = $product_info['quantity'];
			} else {
				$data['stock'] = $this->language->get('text_instock');
			}

			$data['is_in_stock'] = ((int)$product_info['quantity'] > 0) || !empty($product_info['preorder']);
			$data['is_preorder'] = empty($product_info['quantity']) && !empty($product_info['preorder']);

			$this->load->model('tool/image');

			if ($product_info['image']) {
				$data['popup'] = ($this->request->server['HTTPS'] ? $this->config->get('config_ssl') : $this->config->get('config_url')) . 'image/' . $product_info['image'];
			} else {
				$data['popup'] = '';
			}

			if ($product_info['image']) {
				$image_size = @getimagesize(DIR_IMAGE . $product_info['image']);
				if ($image_size && $image_size[0] > 0 && $image_size[1] > 0) {
					$ratio = $image_size[1] / $image_size[0];
					if ($ratio > 1.15) {
						$data['image_orientation'] = 'portrait';
					} elseif ($ratio < 0.85) {
						$data['image_orientation'] = 'landscape';
					} else {
						$data['image_orientation'] = 'square';
					}
				} else {
					$data['image_orientation'] = 'landscape';
				}

				$display_w = 640;
				$display_h = 480;
				if ($data['image_orientation'] === 'portrait') {
					$display_w = 480;
					$display_h = 640;
				} elseif ($data['image_orientation'] === 'square') {
					$display_w = 640;
					$display_h = 640;
				}

				$data['display'] = $this->model_tool_image->resize($product_info['image'], $display_w, $display_h);
				$data['thumb'] = $this->model_tool_image->resize($product_info['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_additional_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_additional_height'));
			} else {
				$data['display'] = '';
				$data['thumb'] = '';
				$data['image_orientation'] = 'landscape';
			}

			$data['images'] = array();

			$results = $this->model_catalog_product->getProductImages($product_id, $this->config->get('config_language_id'));

			foreach ($results as $result) {
				$orientation = 'landscape';
				$image_size = @getimagesize(DIR_IMAGE . $result['image']);
				if ($image_size && $image_size[0] > 0 && $image_size[1] > 0) {
					$ratio = $image_size[1] / $image_size[0];
					if ($ratio > 1.15) {
						$orientation = 'portrait';
					} elseif ($ratio < 0.85) {
						$orientation = 'landscape';
					} else {
						$orientation = 'square';
					}
				}

				$display_w = 600;
				$display_h = 450;
				if ($orientation === 'portrait') {
					$display_w = 450;
					$display_h = 600;
				} elseif ($orientation === 'square') {
					$display_w = 600;
					$display_h = 600;
				}

			$data['images'][] = array(
				'image'       => $result['image'],
				'popup' => ($this->request->server['HTTPS'] ? $this->config->get('config_ssl') : $this->config->get('config_url')) . 'image/' . $result['image'],
				'display' => $this->model_tool_image->resize($result['image'], $display_w, $display_h),
				'thumb' => $this->model_tool_image->resize($result['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_additional_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_additional_height')),
				'orientation' => $orientation
			);
			}

			// Video
			$video_data = $this->model_catalog_product->getProductVideos($product_id, $this->config->get('config_language_id'));
			$data['video_type'] = !empty($video_data) ? $video_data[0]['video_type'] : '';
			$data['video'] = !empty($video_data) ? $video_data[0]['video'] : '';
			$data['video_url'] = '';

			if ($data['video_type'] === 'youtube' && $data['video']) {
				$video_id = $data['video'];
				if (preg_match('/[A-Za-z0-9_-]{11}/', $video_id, $m)) {
					$video_id = $m[0];
				}
				$data['video_url'] = 'https://www.youtube.com/embed/' . urlencode($video_id) . '?autoplay=1&mute=1&loop=1&playlist=' . urlencode($video_id) . '&controls=0&showinfo=0&rel=0&enablejsapi=1';
			} elseif ($data['video_type'] === 'mp4' && $data['video']) {
				if (filter_var($data['video'], FILTER_VALIDATE_URL)) {
					$data['video_url'] = $data['video'];
				} elseif (is_file(DIR_IMAGE . $data['video'])) {
					$data['video_url'] = ($this->request->server['HTTPS'] ? $this->config->get('config_ssl') : $this->config->get('config_url')) . 'image/' . ltrim($data['video'], '/');
				}
			}

			// 3D Model
			$data['model_3d'] = !empty($product_info['model_3d']) ? $product_info['model_3d'] : '';
			$data['model_3d_url'] = '';

			if ($data['model_3d'] && is_file(DIR_IMAGE . $data['model_3d'])) {
				$data['model_3d_url'] = ($this->request->server['HTTPS'] ? $this->config->get('config_ssl') : $this->config->get('config_url')) . 'image/' . ltrim($data['model_3d'], '/');
			}

			if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				$data['price'] = $this->currency->format($this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
			} else {
				$data['price'] = false;
			}

			$data['you_save_amount'] = false;

			if (!is_null($product_info['special']) && (float)$product_info['special'] >= 0) {
				$data['special'] = $this->currency->format($this->tax->calculate($product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				$tax_price = (float)$product_info['special'];
				$data['dc_base_price_value'] = (float)$this->currency->format($this->tax->calculate($product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency'], '', false);

				if ($data['price'] !== false && (float)$product_info['special'] < (float)$product_info['price']) {
					$data['you_save_amount'] = $this->currency->format(
						$this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax')) -
						$this->tax->calculate($product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax')),
						$this->session->data['currency']
					);
				}
			} else {
				$data['special'] = false;
				$tax_price = (float)$product_info['price'];
				$data['dc_base_price_value'] = (float)$this->currency->format($this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency'], '', false);
			}

			if ($data['price'] === false) {
				$data['dc_base_price_value'] = 0.0;
			}

			if ($this->config->get('config_tax')) {
				$data['tax'] = $this->currency->format($tax_price, $this->session->data['currency']);
			} else {
				$data['tax'] = false;
			}

			$discounts = $this->model_catalog_product->getProductDiscounts($product_id);

			$data['discounts'] = array();

			foreach ($discounts as $discount) {
				$data['discounts'][] = array(
					'quantity' => $discount['quantity'],
					'price'    => $this->currency->format($this->tax->calculate($discount['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency'])
				);
			}

			$gifts = $this->model_catalog_product->getProductGifts($product_id);

			$data['gifts'] = array();

			foreach ($gifts as $gift) {
				if ($gift['image']) {
					$gift_image = $this->model_tool_image->resize($gift['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_thumb_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_thumb_height'));
				} else {
					$gift_image = $this->model_tool_image->resize('placeholder.png', $this->config->get('theme_' . $this->config->get('config_theme') . '_image_thumb_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_thumb_height'));
				}

				$data['gifts'][] = array(
					'gift_product_id'  => $gift['gift_product_id'],
					'name'             => $gift['name'],
					'image'            => $gift_image,
					'price'            => $this->currency->format($this->tax->calculate($gift['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']),
					'href'             => $this->url->link('product/product', 'product_id=' . $gift['gift_product_id']),
					'minimum_quantity' => (int)$gift['minimum_quantity']
				);
			}

			$data['text_gift'] = $this->language->get('text_gift');
			$data['text_gift_minimum'] = $this->language->get('text_gift_minimum');

			$data['options'] = array();

			foreach ($this->model_catalog_product->getProductOptions($product_id) as $option) {
				$product_option_value_data = array();

				foreach ($option['product_option_value'] as $option_value) {
					if (($this->config->get('config_customer_price') && $this->customer->isLogged()) || !$this->config->get('config_customer_price')) {
						$price = $this->currency->format($this->tax->calculate($option_value['price'], $product_info['tax_class_id'], $this->config->get('config_tax') ? 'P' : false), $this->session->data['currency']);
					} else {
						$price = false;
					}

					$product_option_value_data[] = array(
						'product_option_value_id' => $option_value['product_option_value_id'],
						'option_value_id'         => $option_value['option_value_id'],
						'name'                    => $option_value['name'],
						'image'                   => $this->model_tool_image->resize($option_value['image'], 50, 50),
						'color_code'              => $option_value['color_code'],
						'price'                   => $price,
						'price_value'             => (float)$this->currency->format($this->tax->calculate($option_value['price'], $product_info['tax_class_id'], $this->config->get('config_tax') ? 'P' : false), $this->session->data['currency'], '', false),
						'price_prefix'            => $option_value['price_prefix'],
					'is_hit'                  => !empty($option_value['is_hit']),
					'color_images'            => isset($option_value['color_images']) ? $option_value['color_images'] : array(),
				);
				}

				usort($product_option_value_data, function ($a, $b) {
					$price_a = $a['price_value'] * ($a['price_prefix'] === '-' ? -1 : 1);
					$price_b = $b['price_value'] * ($b['price_prefix'] === '-' ? -1 : 1);

					if ($price_a != $price_b) {
						return ($price_a < $price_b) ? -1 : 1;
					}

					return strcmp($a['name'], $b['name']);
				});

				$data['options'][] = array(
					'product_option_id'    => $option['product_option_id'],
					'product_option_value' => $product_option_value_data,
					'option_id'            => $option['option_id'],
					'name'                 => $option['name'],
					'type'                 => $option['type'],
					'value'                => $option['value'],
					'required'             => $option['required']
				);
			}

		$gallery_map = array();

		if (!empty($product_info['image'])) {
			$gallery_map[$product_info['image']] = array(
				'display'     => $data['display'],
				'popup'       => $data['popup'],
				'orientation' => $data['image_orientation']
			);
		}

		foreach ($data['images'] as $img) {
			if (!empty($img['image'])) {
				$gallery_map[$img['image']] = $img;
			}
		}

		foreach ($data['options'] as &$option) {
			if ($option['type'] != 'color') {
				continue;
			}

			foreach ($option['product_option_value'] as &$option_value) {
				$resolved = array();

				if (!empty($option_value['color_images'])) {
					foreach ($option_value['color_images'] as $image_path) {
						if (isset($gallery_map[$image_path])) {
							$resolved[] = $gallery_map[$image_path];
						}
					}
				}

				$option_value['color_images'] = $resolved;
			}
			unset($option_value);

			break;
		}
		unset($option);

			if (!empty($product_info['is_configurable'])) {
				$pc = new ProductConfigurable($this->registry);

				$configurable = $pc->getConfigurable($product_id);
				$variants = $pc->getVariants($product_id);
				$axes = $pc->getConfigurableOptions($product_id);
				$default_variant = !empty($configurable['default_variant_id']) ? $pc->getVariant($configurable['default_variant_id']) : array();

				$axis_option_ids = array_column($axes, 'option_id');
				$formatted_axes = array();

				if (!empty($axis_option_ids)) {
					$po_query = $this->db->query("SELECT product_option_id, option_id FROM " . DB_PREFIX . "product_option WHERE product_id = '" . (int)$product_id . "' AND option_id IN (" . implode(',', array_map('intval', $axis_option_ids)) . ")");
					$po_map = array();
					$po_ids = array();

					foreach ($po_query->rows as $row) {
						$po_map[(int)$row['option_id']] = (int)$row['product_option_id'];
						$po_ids[] = (int)$row['product_option_id'];
					}

					$pov_map = array();

					if (!empty($po_ids)) {
						$pov_query = $this->db->query("SELECT product_option_value_id, option_value_id, product_option_id FROM " . DB_PREFIX . "product_option_value WHERE product_option_id IN (" . implode(',', $po_ids) . ")");
						foreach ($pov_query->rows as $row) {
							$pid = (int)$row['product_option_id'];
							$ov_id = (int)$row['option_value_id'];
							$pov_id = (int)$row['product_option_value_id'];
							$pov_map[$pid][$ov_id] = $pov_id;
						}
					}

					foreach ($axes as $axe) {
						$oid = (int)$axe['option_id'];
						$pid = isset($po_map[$oid]) ? $po_map[$oid] : 0;

						$formatted_axes[] = array(
							'option_id' => $oid,
							'po_id'     => $pid,
							'name'      => $axe['name'],
							'type'      => $axe['type'],
							'pov_map'   => isset($pov_map[$pid]) ? $pov_map[$pid] : new stdClass(),
						);
					}
				}

				$tax_class_id = (int)$product_info['tax_class_id'];
				$tax = $this->config->get('config_tax');
				$currency_code = isset($this->session->data['currency']) ? $this->session->data['currency'] : $this->config->get('config_currency');

				foreach ($variants as &$variant) {
					if (isset($variant['price']) && $variant['price'] !== '') {
						$variant['price'] = (float)$this->currency->format(
							$this->tax->calculate((float)$variant['price'], $tax_class_id, $tax),
							$currency_code, '', false
						);
					}
				}
				unset($variant);

				if (!empty($default_variant) && isset($default_variant['price'])) {
					$default_variant['price'] = (float)$this->currency->format(
						$this->tax->calculate((float)$default_variant['price'], $tax_class_id, $tax),
						$currency_code, '', false
					);
				}

				$data['is_configurable'] = true;
				$data['dc_variant_json'] = json_encode(array(
					'axes'               => $formatted_axes,
					'variants'           => $variants,
					'default_variant_id' => (int)($configurable['default_variant_id'] ?? 0),
					'default_variant'    => $default_variant,
				));

				// Schema.org variant data
				$base_url = $this->request->server['HTTPS'] ? $this->config->get('config_ssl') : $this->config->get('config_url');
				$base_price_fallback = !empty($data['dc_base_price_value']) ? (float)$data['dc_base_price_value'] : null;
				$prices = array();
				$schema_variants = array();

				foreach ($variants as $v) {
					if (!$v['status']) continue;

					$v_price = isset($v['price']) && $v['price'] !== '' ? (float)$v['price'] : $base_price_fallback;
					if ($v_price !== null) $prices[] = $v_price;

					$schema_variants[] = array(
						'variant_id'  => (int)$v['variant_id'],
						'sku'         => $v['sku'],
						'price'       => $v_price,
						'image'       => $v['image'] ? $base_url . 'image/' . $v['image'] : '',
						'url'         => $data['schema_product_url'] . '?variant_id=' . (int)$v['variant_id'],
						'is_in_stock' => (int)$v['quantity'] > 0,
					);
				}

				$data['schema_variants'] = $schema_variants;
				$data['schema_variant_low_price'] = $prices ? min($prices) : null;
				$data['schema_variant_high_price'] = $prices ? max($prices) : null;
				$data['schema_any_in_stock'] = !empty(array_filter(array_column($schema_variants, 'is_in_stock')));
				$data['schema_any_preorder'] = !empty($product_info['preorder']);
			}

			if (!isset($data['minimum'])) {
				$data['minimum'] = 1;
			}

			$data['review_status'] = $this->config->get('config_review_status');

			if ($this->config->get('config_review_guest') || $this->customer->isLogged()) {
				$data['review_guest'] = true;
			} else {
				$data['review_guest'] = false;
			}

			if ($this->customer->isLogged()) {
				$data['customer_name'] = $this->customer->getFirstName() . '&nbsp;' . $this->customer->getLastName();
			} else {
				$data['customer_name'] = '';
			}

			$data['reviews'] = sprintf($this->language->get('text_reviews'), (int)$product_info['reviews']);
			$data['rating'] = (int)$product_info['rating'];
			$data['review_count'] = (int)$product_info['reviews'];

			// UI language strings
			$data['text_model'] = $this->language->get('text_model');
			$data['text_delivery'] = $this->language->get('text_delivery');
			$data['text_delivery_desc'] = $this->language->get('text_delivery_desc');
			$data['text_warranty'] = $this->language->get('text_warranty');
			$data['text_warranty_desc'] = $this->language->get('text_warranty_desc');
			$data['text_returns'] = $this->language->get('text_returns');
			$data['text_returns_desc'] = $this->language->get('text_returns_desc');

			$product_feature_defaults = array(
				array(
					'icon' => 'truck',
					'title' => $this->language->get('text_delivery'),
					'text' => $this->language->get('text_delivery_desc'),
					'sort_order' => 0
				),
				array(
					'icon' => 'shield-check',
					'title' => $this->language->get('text_warranty'),
					'text' => $this->language->get('text_warranty_desc'),
					'sort_order' => 1
				),
				array(
					'icon' => 'refresh-ccw',
					'title' => $this->language->get('text_returns'),
					'text' => $this->language->get('text_returns_desc'),
					'sort_order' => 2
				)
			);
			$data['product_features'] = $this->resolveThemeFeatures('dockercart_theme_product_features', $product_feature_defaults);

			// Messenger contact links (for product page under features)
			$server = ($this->request->server['HTTPS'] ?? '') ? HTTPS_SERVER : HTTP_SERVER;
			$data['messenger_links'] = array();
			for ($i = 1; $i <= 10; $i++) {
				$image = (string)$this->config->get('dockercart_theme_messenger_' . $i . '_image');
				$link  = trim((string)$this->config->get('dockercart_theme_messenger_' . $i . '_link'));
				$name  = trim((string)$this->config->get('dockercart_theme_messenger_' . $i . '_name'));
				$image_path = ltrim($image, '/');
				if ($image_path !== '') {
					$data['messenger_links'][] = array(
						'image' => $server . 'image/' . $image_path,
						'link'  => $link,
						'name'  => $name,
					);
				}
			}
			$fab_raw = $this->config->get('dockercart_theme_messenger_fab_status');
			$data['messenger_fab_status'] = ($fab_raw !== null && (int)$fab_raw === 1);

			// Call for price
			$data['call_for_price_status'] = (int)$this->config->get('dockercart_theme_call_for_price_status');
			$data['call_for_price_phone'] = $this->config->get('config_telephone');
			$data['call_for_price'] = !empty($product_info['call_for_price']);

			$data['text_write_in_messenger'] = $this->language->get('text_write_in_messenger');
			$data['text_we_are_in_messengers'] = $this->language->get('text_we_are_in_messengers');
			$data['text_you_may_also_like'] = $this->language->get('text_you_may_also_like');
			$data['text_view_all'] = $this->language->get('text_view_all');
			$data['text_quick_view'] = $this->language->get('text_quick_view');
			$data['text_total'] = $this->language->get('text_total');
			$data['text_sale'] = $this->language->get('text_sale');
			$data['text_call_for_price'] = $this->language->get('text_call_for_price');

			// Captcha
			if ($this->config->get('captcha_' . $this->config->get('config_captcha') . '_status') && in_array('review', (array)$this->config->get('config_captcha_page'))) {
				$data['captcha'] = $this->load->controller('extension/captcha/' . $this->config->get('config_captcha'));
			} else {
				$data['captcha'] = '';
			}

			$data['share'] = $this->url->link('product/product', 'product_id=' . $product_id);

			$data['attribute_groups'] = $this->model_catalog_product->getProductAttributes($product_id);

			$data['products'] = array();

			$results = $this->model_catalog_product->getProductRelated($product_id);

			foreach ($results as $result) {
				if ($result['image']) {
					$image = $this->model_tool_image->resize($result['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_related_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_related_height'));
				} else {
					$image = $this->model_tool_image->resize('placeholder.png', $this->config->get('theme_' . $this->config->get('config_theme') . '_image_related_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_related_height'));
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
					$stock = !empty($result['preorder'])
						? $this->language->get('text_preorder')
						: $this->language->get('text_out_of_stock');
				} elseif ($this->config->get('config_stock_display')) {
					$stock = $stock_quantity;
				} else {
					$stock = $this->language->get('text_instock');
				}

				$discount_percent = 0;
				if (!is_null($result['special']) && $result['price'] > 0) {
					$discount_percent = (int)round((1 - ((float)$result['special'] / (float)$result['price'])) * 100);
					if ($discount_percent < 0) {
						$discount_percent = 0;
					}
				}

				$category_name = '';
				$product_categories = $this->model_catalog_product->getCategories((int)$result['product_id']);
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
					'model'       => $result['model'],
					'manufacturer'=> isset($result['manufacturer']) ? $result['manufacturer'] : '',
					'category'    => $category_name,
					'description' => utf8_substr(trim(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8'))), 0, $this->config->get('theme_' . $this->config->get('config_theme') . '_product_description_length')) . '..',
					'price'       => $price,
					'price_raw'   => (float)$result['price'],
					'special'     => $special,
					'discount'    => $discount_percent,
					'tax'         => $tax,
					'minimum'     => $this->formatQuantityValue(($result['minimum'] > 0 ? $result['minimum'] : 1)),
					'quantity_step' => (isset($result['quantity_step']) && (float)$result['quantity_step'] > 0) ? $result['quantity_step'] : 1,
					'stock'       => $stock,
					'is_in_stock' => ($stock_quantity > 0) || !empty($result['preorder']),
					'is_preorder' => empty($stock_quantity) && !empty($result['preorder']),
					'rating'      => $rating,
					'reviews'     => isset($result['reviews']) ? (int)$result['reviews'] : 0,
					'in_wishlist' => in_array((int)$result['product_id'], $wishlist_ids) ? 1 : 0,
					'call_for_price' => !empty($result['call_for_price']),
					'href'        => $this->url->link('product/product', 'product_id=' . $result['product_id'])
				);
			}

			$data['tags'] = array();

			if ($product_info['tag']) {
				$tags = explode(',', $product_info['tag']);

				foreach ($tags as $tag) {
					$data['tags'][] = array(
						'tag'  => trim($tag),
						'href' => $this->url->link('product/search', 'tag=' . urlencode(html_entity_decode(trim($tag), ENT_QUOTES, 'UTF-8')))
					);
				}
			}

		$data['bundles'] = array();

		$bundle_lib = new ProductBundle($this->registry);
		$bundle_results = $bundle_lib->getBundlesByProduct($product_id, (int)$this->config->get('config_store_id'));

		$bundles = array();

		foreach ($bundle_results as $bundle) {
			$bundle_products = $bundle_lib->getBundleProducts($bundle['bundle_id']);

			$products_data = array();
			$original_total = 0;

			foreach ($bundle_products as $bp) {
				$bp_info = $this->model_catalog_product->getProduct($bp['product_id']);

				if ($bp_info) {
					$bp_price = (float)$bp_info['price'];

					if (!is_null($bp_info['special']) && (float)$bp_info['special'] < $bp_price) {
						$bp_price = (float)$bp_info['special'];
					}

					$original_total += $bp_price;

					$products_data[] = array(
						'product_id'  => $bp_info['product_id'],
						'name'        => $bp_info['name'],
						'thumb'       => $this->model_tool_image->resize($bp_info['image'], 100, 100),
						'price'       => $this->currency->format($this->tax->calculate($bp_price, $bp_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']),
						'price_value' => $bp_price,
						'href'        => $this->url->link('product/product', 'product_id=' . $bp_info['product_id'])
					);
				}
			}

			if (count($products_data) >= 2) {
				$discount_value = (float)$bundle['discount_value'];
				$bundle_total = $original_total;

				if ($bundle['discount_type'] == 'percentage') {
					$bundle_total = $original_total * (1 - $discount_value / 100);
				} else {
					$bundle_total = max(0, $original_total - $discount_value);
				}

				if ($bundle['discount_type'] == 'percentage') {
					$discount_text = (int)$discount_value . '%';
				} else {
					$discount_text = $this->currency->format($discount_value, $this->session->data['currency']);
				}

				$all_in_stock = true;
				foreach ($bundle_products as $bp) {
					$bp_stock_info = $this->model_catalog_product->getProduct($bp['product_id']);
					if ($bp_stock_info && (float)$bp_stock_info['quantity'] <= 0 && empty($bp_stock_info['preorder'])) {
						$all_in_stock = false;
						break;
					}
				}

				$bundles[] = array(
					'bundle_id'                 => $bundle['bundle_id'],
					'name'                      => $bundle['name'],
					'products'                  => $products_data,
					'original_total'            => $original_total,
					'total'                     => $bundle_total,
					'original_total_formatted'  => $this->currency->format($original_total, $this->session->data['currency']),
					'total_formatted'           => $this->currency->format($bundle_total, $this->session->data['currency']),
					'you_save_formatted'        => $this->currency->format(max(0, $original_total - $bundle_total), $this->session->data['currency']),
					'discount_type'             => $bundle['discount_type'],
					'discount_value'            => $discount_value,
					'discount_text'             => $discount_text,
					'all_in_stock'              => $all_in_stock,
				);
			}
		}

		$data['bundles'] = $bundles;

		$data['text_bundle_title'] = $this->language->get('text_bundle_title');
		$data['text_bundle_save'] = $this->language->get('text_bundle_save');
		$data['button_bundle_add'] = $this->language->get('button_bundle_add');

		// Skip view tracking for known bots/crawlers
		$is_bot = false;
		$user_agent = $this->request->server['HTTP_USER_AGENT'] ?? '';

		if ($user_agent === '') {
			$is_bot = true;
		} else {
			$robots = explode("\n", str_replace(["\r\n", "\r"], "\n", trim((string)$this->config->get('config_robots'))));

			foreach ($robots as $robot) {
				$robot = trim($robot);

				if ($robot !== '' && mb_stripos($user_agent, $robot) !== false) {
					$is_bot = true;
					break;
				}
			}
		}

		if (!$is_bot) {
			$this->load->model('account/viewed');
			$this->model_account_viewed->addViewedProduct($product_id);
			$this->model_catalog_product->updateViewed($product_id);
		}

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

			$this->response->setOutput($this->load->view('product/product', $data));
		} else {
			$url = '';

			if (isset($this->request->get['path'])) {
				$url .= '&path=' . $this->request->get['path'];
			}

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
			}

			if (isset($this->request->get['manufacturer_id'])) {
				$url .= '&manufacturer_id=' . $this->request->get['manufacturer_id'];
			}

			if (isset($this->request->get['search'])) {
				$url .= '&search=' . $this->request->get['search'];
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
				'text' => $this->language->get('text_error'),
				'href' => $this->url->link('product/product', $url . '&product_id=' . $product_id)
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

	private function formatQuantityValue($value) {
		$formatted = number_format((float)$value, 2, '.', '');

		return rtrim(rtrim($formatted, '0'), '.');
	}

	public function review() {
		$this->load->language('product/product');

		$this->load->model('catalog/review');

		if (isset($this->request->get['product_id'])) {
			$product_id = (int)$this->request->get['product_id'];
		} else {
			$product_id = 0;
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$data['reviews'] = array();

		$review_total = $this->model_catalog_review->getTotalReviewsByProductId($product_id);

		$results = $this->model_catalog_review->getReviewsByProductId($product_id, ($page - 1) * 5, 5);

		foreach ($results as $result) {
			$data['reviews'][] = array(
				'author'     => $result['author'],
				'text'       => nl2br($result['text']),
				'rating'     => (int)$result['rating'],
				'date_added' => date($this->language->get('date_format_short'), strtotime($result['date_added']))
			);
		}

		$pagination = new Pagination();
		$pagination->total = $review_total;
		$pagination->page = $page;
		$pagination->limit = 5;
		$pagination->url = $this->url->link('product/product/review', 'product_id=' . $product_id . '&page={page}');

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($review_total) ? (($page - 1) * 5) + 1 : 0, ((($page - 1) * 5) > ($review_total - 5)) ? $review_total : ((($page - 1) * 5) + 5), $review_total, ceil($review_total / 5));

		$this->response->setOutput($this->load->view('product/review', $data));
	}

	public function write() {
		$this->load->language('product/product');

		$json = array();

		if (isset($this->request->get['product_id']) && $this->request->get['product_id']) {
			if ($this->request->server['REQUEST_METHOD'] == 'POST') {
				if ((utf8_strlen($this->request->post['name']) < 3) || (utf8_strlen($this->request->post['name']) > 25)) {
					$json['error'] = $this->language->get('error_name');
				}

				if ((utf8_strlen($this->request->post['text']) < 25) || (utf8_strlen($this->request->post['text']) > 1000)) {
					$json['error'] = $this->language->get('error_text');
				}

				if (empty($this->request->post['rating']) || $this->request->post['rating'] < 0 || $this->request->post['rating'] > 5) {
					$json['error'] = $this->language->get('error_rating');
				}

				// Captcha
				if ($this->config->get('captcha_' . $this->config->get('config_captcha') . '_status') && in_array('review', (array)$this->config->get('config_captcha_page'))) {
					$captcha = $this->load->controller('extension/captcha/' . $this->config->get('config_captcha') . '/validate');

					if ($captcha) {
						$json['error'] = $captcha;
					}
				}

				if (!isset($json['error'])) {
					$this->load->model('catalog/review');

					$this->model_catalog_review->addReview($this->request->get['product_id'], $this->request->post);

					$json['success'] = $this->language->get('text_success');
				}
			}
		} else {
			$json['error'] = $this->language->get('error_product');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function getCategoryPath($category_id, $visited = array()) {
		$path = '';

		// Prevent infinite loops if there are circular parent relationships
		if (in_array($category_id, $visited)) {
			return $path;
		}

		$this->load->model('catalog/category');
		$visited[] = $category_id;

		$category_info = $this->model_catalog_category->getCategory($category_id);

		if ($category_info) {
			if ($category_info['parent_id'] && $category_info['parent_id'] != $category_id) {
				$parent_path = $this->getCategoryPath($category_info['parent_id'], $visited);
				$path = $parent_path ? $parent_path . '_' . $category_id : $category_id;
			} else {
				$path = $category_id;
			}
		}

		return $path;
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
}
