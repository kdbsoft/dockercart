<?php
class ControllerCatalogProduct extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('catalog/product');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/product');

		$this->getList();
	}

	public function add() {
		$this->load->language('catalog/product');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/product');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			// Auto-detect video from unified fields (no selector)
			$video_youtube = !empty($this->request->post['video_youtube']) ? $this->request->post['video_youtube'] : '';
			$video_mp4 = !empty($this->request->post['video_mp4']) ? $this->request->post['video_mp4'] : '';

			if (!empty($video_youtube)) {
				$this->request->post['video_type'] = 'youtube';
				$this->request->post['video'] = $this->extractYouTubeId($video_youtube);
			} elseif (!empty($video_mp4)) {
				$this->request->post['video_type'] = 'mp4';
				$this->request->post['video'] = $video_mp4;
			} else {
				$this->request->post['video_type'] = '';
				$this->request->post['video'] = '';
			}

			// Convert flat video fields to product_video array format
			if (!empty($this->request->post['video_type']) && !empty($this->request->post['video'])) {
				$this->request->post['product_video'] = array(
					array(
						'video_type'  => $this->request->post['video_type'],
						'video'       => $this->request->post['video'],
						'language_id' => '',
						'sort_order'  => 0
					)
				);
			}

			$product_id = $this->model_catalog_product->addProduct($this->request->post);

			if (!empty($this->request->post['product_bundle']) && is_array($this->request->post['product_bundle'])) {
				$this->load->model('catalog/product_bundle');

				$product_store = !empty($this->request->post['product_store']) ? array_map('intval', $this->request->post['product_store']) : array(0);

				foreach ($this->request->post['product_bundle'] as $bundle_entry) {
					if (empty($bundle_entry['bundle_product_ids']) || empty($bundle_entry['discount_value'])) {
						continue;
					}

					$product_ids = array_filter(array_map('intval', explode(',', $bundle_entry['bundle_product_ids'])));
					$all_products = array_unique(array_merge(array((int) $product_id), $product_ids));

					$data = array(
						'name'            => '',
						'discount_type'   => !empty($bundle_entry['discount_type']) ? $bundle_entry['discount_type'] : 'percentage',
						'discount_value'  => (float) $bundle_entry['discount_value'],
						'date_start'      => !empty($bundle_entry['date_start']) ? $bundle_entry['date_start'] : '0000-00-00',
						'date_end'        => !empty($bundle_entry['date_end']) ? $bundle_entry['date_end'] : '0000-00-00',
						'status'          => 1,
						'sort_order'      => 0,
						'auto_renew'      => !empty($bundle_entry['auto_renew']),
						'bundle_product'  => $all_products,
						'bundle_store'    => $product_store
					);

					if (empty($data['date_start'])) {
						$data['date_start'] = '0000-00-00';
					}

					if (empty($data['date_end'])) {
						$data['date_end'] = '0000-00-00';
					}

					$this->model_catalog_product_bundle->addBundle($data);
				}
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['filter_name'])) {
				$url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_model'])) {
				$url .= '&filter_model=' . urlencode(html_entity_decode($this->request->get['filter_model'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_sku'])) {
				$url .= '&filter_sku=' . urlencode(html_entity_decode($this->request->get['filter_sku'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_search'])) {
				$url .= '&filter_search=' . urlencode(html_entity_decode($this->request->get['filter_search'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_price'])) {
				$url .= '&filter_price=' . $this->request->get['filter_price'];
			}

			if (isset($this->request->get['filter_quantity_min'])) {
				$url .= '&filter_quantity_min=' . $this->request->get['filter_quantity_min'];
			}

			if (isset($this->request->get['filter_quantity_max'])) {
				$url .= '&filter_quantity_max=' . $this->request->get['filter_quantity_max'];
			}

			if (isset($this->request->get['filter_status'])) {
				$url .= '&filter_status=' . $this->request->get['filter_status'];
			}

			if (isset($this->request->get['filter_manufacturer'])) {
				$url .= '&filter_manufacturer=' . urlencode(html_entity_decode($this->request->get['filter_manufacturer'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_category'])) {
				$url .= '&filter_category=' . urlencode(html_entity_decode($this->request->get['filter_category'], ENT_QUOTES, 'UTF-8'));
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

			$this->response->redirect($this->url->link('catalog/product', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function edit() {
		$this->load->language('catalog/product');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/product');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			// Auto-detect video from unified fields (no selector)
			$video_youtube = !empty($this->request->post['video_youtube']) ? $this->request->post['video_youtube'] : '';
			$video_mp4 = !empty($this->request->post['video_mp4']) ? $this->request->post['video_mp4'] : '';

			if (!empty($video_youtube)) {
				$this->request->post['video_type'] = 'youtube';
				$this->request->post['video'] = $this->extractYouTubeId($video_youtube);
			} elseif (!empty($video_mp4)) {
				$this->request->post['video_type'] = 'mp4';
				$this->request->post['video'] = $video_mp4;
			} else {
				$this->request->post['video_type'] = '';
				$this->request->post['video'] = '';
			}

			// Convert flat video fields to product_video array format
			if (!empty($this->request->post['video_type']) && !empty($this->request->post['video'])) {
				$this->request->post['product_video'] = array(
					array(
						'video_type'  => $this->request->post['video_type'],
						'video'       => $this->request->post['video'],
						'language_id' => '',
						'sort_order'  => 0
					)
				);
			}

			$this->model_catalog_product->editProduct($this->request->get['product_id'], $this->request->post);

			$this->load->model('catalog/product_bundle');

			$current_product_id = (int) $this->request->get['product_id'];
			$product_store = !empty($this->request->post['product_store']) ? array_map('intval', $this->request->post['product_store']) : array(0);
			$submitted_bundle_ids = array();

			if (!empty($this->request->post['product_bundle']) && is_array($this->request->post['product_bundle'])) {
				foreach ($this->request->post['product_bundle'] as $bundle_entry) {
					if (empty($bundle_entry['bundle_product_ids']) || empty($bundle_entry['discount_value'])) {
						continue;
					}

					$product_ids = array_filter(array_map('intval', explode(',', $bundle_entry['bundle_product_ids'])));
					$all_products = array_unique(array_merge(array($current_product_id), $product_ids));

					$data = array(
						'name'            => '',
						'discount_type'   => !empty($bundle_entry['discount_type']) ? $bundle_entry['discount_type'] : 'percentage',
						'discount_value'  => (float) $bundle_entry['discount_value'],
						'date_start'      => !empty($bundle_entry['date_start']) ? $bundle_entry['date_start'] : '0000-00-00',
						'date_end'        => !empty($bundle_entry['date_end']) ? $bundle_entry['date_end'] : '0000-00-00',
						'status'          => 1,
						'sort_order'      => 0,
						'auto_renew'      => !empty($bundle_entry['auto_renew']),
						'bundle_product'  => $all_products,
						'bundle_store'    => $product_store
					);

					if (empty($data['date_start'])) {
						$data['date_start'] = '0000-00-00';
					}

					if (empty($data['date_end'])) {
						$data['date_end'] = '0000-00-00';
					}

					$bundle_id = !empty($bundle_entry['bundle_id']) ? (int) $bundle_entry['bundle_id'] : 0;

					if ($bundle_id) {
						$this->model_catalog_product_bundle->editBundle($bundle_id, $data);
						$submitted_bundle_ids[] = $bundle_id;
					} else {
						$this->model_catalog_product_bundle->addBundle($data);
					}
				}
			}

			$existing_bundles = $this->model_catalog_product_bundle->getBundlesByProduct($current_product_id);

			foreach ($existing_bundles as $eb) {
				if (!in_array((int) $eb['bundle_id'], $submitted_bundle_ids)) {
					$this->model_catalog_product_bundle->deleteBundle((int) $eb['bundle_id']);
				}
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['filter_name'])) {
				$url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_model'])) {
				$url .= '&filter_model=' . urlencode(html_entity_decode($this->request->get['filter_model'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_sku'])) {
				$url .= '&filter_sku=' . urlencode(html_entity_decode($this->request->get['filter_sku'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_search'])) {
				$url .= '&filter_search=' . urlencode(html_entity_decode($this->request->get['filter_search'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_price'])) {
				$url .= '&filter_price=' . $this->request->get['filter_price'];
			}

			if (isset($this->request->get['filter_quantity_min'])) {
				$url .= '&filter_quantity_min=' . $this->request->get['filter_quantity_min'];
			}

			if (isset($this->request->get['filter_quantity_max'])) {
				$url .= '&filter_quantity_max=' . $this->request->get['filter_quantity_max'];
			}

			if (isset($this->request->get['filter_status'])) {
				$url .= '&filter_status=' . $this->request->get['filter_status'];
			}

			if (isset($this->request->get['filter_manufacturer'])) {
				$url .= '&filter_manufacturer=' . urlencode(html_entity_decode($this->request->get['filter_manufacturer'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_category'])) {
				$url .= '&filter_category=' . urlencode(html_entity_decode($this->request->get['filter_category'], ENT_QUOTES, 'UTF-8'));
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

			$this->response->redirect($this->url->link('catalog/product', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function delete() {
		$this->load->language('catalog/product');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/product');

		$product_ids = [];

		if (isset($this->request->post['selected'])) {
			$product_ids = $this->request->post['selected'];
		} elseif (isset($this->request->get['product_id'])) {
			$product_ids = [(int) $this->request->get['product_id']];
		}

		if ($product_ids && $this->validateDelete()) {
			foreach ($product_ids as $product_id) {
				$this->model_catalog_product->deleteProduct((int) $product_id);
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['filter_name'])) {
				$url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_model'])) {
				$url .= '&filter_model=' . urlencode(html_entity_decode($this->request->get['filter_model'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_sku'])) {
				$url .= '&filter_sku=' . urlencode(html_entity_decode($this->request->get['filter_sku'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_search'])) {
				$url .= '&filter_search=' . urlencode(html_entity_decode($this->request->get['filter_search'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_price'])) {
				$url .= '&filter_price=' . $this->request->get['filter_price'];
			}

			if (isset($this->request->get['filter_quantity_min'])) {
				$url .= '&filter_quantity_min=' . $this->request->get['filter_quantity_min'];
			}

			if (isset($this->request->get['filter_quantity_max'])) {
				$url .= '&filter_quantity_max=' . $this->request->get['filter_quantity_max'];
			}

			if (isset($this->request->get['filter_status'])) {
				$url .= '&filter_status=' . $this->request->get['filter_status'];
			}

			if (isset($this->request->get['filter_manufacturer'])) {
				$url .= '&filter_manufacturer=' . urlencode(html_entity_decode($this->request->get['filter_manufacturer'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_category'])) {
				$url .= '&filter_category=' . urlencode(html_entity_decode($this->request->get['filter_category'], ENT_QUOTES, 'UTF-8'));
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

			$this->response->redirect($this->url->link('catalog/product', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	public function copy() {
		$this->load->language('catalog/product');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/product');

		$product_ids = [];

		if (isset($this->request->post['selected'])) {
			$product_ids = $this->request->post['selected'];
		} elseif (isset($this->request->get['product_id'])) {
			$product_ids = [(int) $this->request->get['product_id']];
		}

		if ($product_ids && $this->validateCopy()) {
			foreach ($product_ids as $product_id) {
				$this->model_catalog_product->copyProduct((int) $product_id);
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['filter_name'])) {
				$url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_model'])) {
				$url .= '&filter_model=' . urlencode(html_entity_decode($this->request->get['filter_model'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_sku'])) {
				$url .= '&filter_sku=' . urlencode(html_entity_decode($this->request->get['filter_sku'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_search'])) {
				$url .= '&filter_search=' . urlencode(html_entity_decode($this->request->get['filter_search'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_price'])) {
				$url .= '&filter_price=' . $this->request->get['filter_price'];
			}

			if (isset($this->request->get['filter_quantity_min'])) {
				$url .= '&filter_quantity_min=' . $this->request->get['filter_quantity_min'];
			}

			if (isset($this->request->get['filter_quantity_max'])) {
				$url .= '&filter_quantity_max=' . $this->request->get['filter_quantity_max'];
			}

			if (isset($this->request->get['filter_status'])) {
				$url .= '&filter_status=' . $this->request->get['filter_status'];
			}

			if (isset($this->request->get['filter_manufacturer'])) {
				$url .= '&filter_manufacturer=' . urlencode(html_entity_decode($this->request->get['filter_manufacturer'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_category'])) {
				$url .= '&filter_category=' . urlencode(html_entity_decode($this->request->get['filter_category'], ENT_QUOTES, 'UTF-8'));
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

			$this->response->redirect($this->url->link('catalog/product', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	protected function getList() {
		$data['heading_title'] = $this->language->get('heading_title');
		if (isset($this->request->get['filter_name'])) {
			$filter_name = $this->request->get['filter_name'];
		} else {
			$filter_name = '';
		}

		if (isset($this->request->get['filter_model'])) {
			$filter_model = $this->request->get['filter_model'];
		} else {
			$filter_model = '';
		}

		if (isset($this->request->get['filter_price'])) {
			$filter_price = $this->request->get['filter_price'];
		} else {
			$filter_price = '';
		}

		if (isset($this->request->get['filter_quantity_min'])) {
			$filter_quantity_min = $this->request->get['filter_quantity_min'];
		} else {
			$filter_quantity_min = '';
		}

		if (isset($this->request->get['filter_quantity_max'])) {
			$filter_quantity_max = $this->request->get['filter_quantity_max'];
		} else {
			$filter_quantity_max = '';
		}

		if (isset($this->request->get['filter_status'])) {
			$filter_status = $this->request->get['filter_status'];
		} else {
			$filter_status = '';
		}

		if (isset($this->request->get['filter_sku'])) {
			$filter_sku = $this->request->get['filter_sku'];
		} else {
			$filter_sku = '';
		}

		if (isset($this->request->get['filter_search'])) {
			$filter_search = $this->request->get['filter_search'];
		} else {
			$filter_search = '';
		}

		if (isset($this->request->get['filter_manufacturer'])) {
			$filter_manufacturer = $this->request->get['filter_manufacturer'];
		} else {
			$filter_manufacturer = '';
		}

		if (isset($this->request->get['filter_category'])) {
			$filter_category = $this->request->get['filter_category'];
		} else {
			$filter_category = '';
		}

		if (isset($this->request->get['filter_category_id'])) {
			$filter_category_id = (int)$this->request->get['filter_category_id'];
		} else {
			$filter_category_id = '';
		}

		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'pd.name';
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

		$url = '';

		if (isset($this->request->get['filter_name'])) {
			$url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_model'])) {
			$url .= '&filter_model=' . urlencode(html_entity_decode($this->request->get['filter_model'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_price'])) {
			$url .= '&filter_price=' . $this->request->get['filter_price'];
		}

		if (isset($this->request->get['filter_sku'])) {
			$url .= '&filter_sku=' . urlencode(html_entity_decode($this->request->get['filter_sku'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_quantity_min'])) {
			$url .= '&filter_quantity_min=' . $this->request->get['filter_quantity_min'];
		}

		if (isset($this->request->get['filter_quantity_max'])) {
			$url .= '&filter_quantity_max=' . $this->request->get['filter_quantity_max'];
		}

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}

		if (isset($this->request->get['filter_manufacturer'])) {
			$url .= '&filter_manufacturer=' . urlencode(html_entity_decode($this->request->get['filter_manufacturer'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_category'])) {
			$url .= '&filter_category=' . urlencode(html_entity_decode($this->request->get['filter_category'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_category_id'])) {
			$url .= '&filter_category_id=' . $this->request->get['filter_category_id'];
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

		$data['add'] = $this->url->link('catalog/product/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['copy'] = $this->url->link('catalog/product/copy', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['delete'] = $this->url->link('catalog/product/delete', 'user_token=' . $this->session->data['user_token'] . $url, true);

		// Per-admin saved filters (Shopify-style tabs)
		$active_filter = $this->getActiveUserFilter('product');

		$this->load->model('user/user_filter');

		$user_id = (int)$this->user->getId();
		$saved_filters = $this->model_user_user_filter->getFilters($user_id, 'product');

		$tab_counts = array(
			'all' => $this->model_catalog_product->getTotalProducts(array())
		);

		foreach ($saved_filters as $saved) {
			$tab_counts['custom_' . $saved['filter_id']] = $this->model_catalog_product->getTotalProducts($this->buildProductFilterData($saved['conditions']));
		}

		$status_options = array(
			array('value' => '1', 'label' => $this->language->get('text_enabled')),
			array('value' => '0', 'label' => $this->language->get('text_disabled'))
		);

		$data['user_filter'] = $this->renderUserFilter('product', 'catalog/product', array(
			array('key' => 'name', 'label' => $this->language->get('entry_name'), 'type' => 'text'),
			array('key' => 'model', 'label' => $this->language->get('entry_model'), 'type' => 'text'),
			array('key' => 'sku', 'label' => $this->language->get('entry_sku'), 'type' => 'text'),
			array('key' => 'price', 'label' => $this->language->get('entry_price'), 'type' => 'number'),
			array('key' => 'quantity', 'label' => $this->language->get('entry_quantity'), 'type' => 'number'),
			array('key' => 'status', 'label' => $this->language->get('entry_status'), 'type' => 'select', 'options' => $status_options),
			array('key' => 'manufacturer', 'label' => $this->language->get('entry_manufacturer'), 'type' => 'text')
		), $tab_counts, '', array(), array(
			'placeholder' => $this->language->get('text_search_products'),
			'url'         => $this->url->link('catalog/product/autocomplete', 'user_token=' . $this->session->data['user_token'], true)
		));

		$data['active_filter'] = $active_filter;

		$data['products'] = array();

		$filter_data = array(
			'filter_name'	      => $filter_name,
			'filter_model'	      => $filter_model,
			'filter_sku'	      => $filter_sku,
			'filter_search'	      => $filter_search,
			'filter_price'	      => $filter_price,
			'filter_quantity_min' => $filter_quantity_min,
			'filter_quantity_max' => $filter_quantity_max,
			'filter_status'       => $filter_status,
			'filter_manufacturer' => $filter_manufacturer,
			'filter_category'     => $filter_category,
			'filter_category_id'  => $filter_category_id,
			'sort'                => $sort,
			'order'               => $order,
			'start'               => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit'               => $this->config->get('config_limit_admin')
		);

		if ($active_filter) {
			foreach ($this->buildProductFilterData($active_filter['conditions']) as $key => $value) {
				$filter_data[$key] = $value;
			}
		}

		$this->load->model('tool/image');

		$product_total = $this->model_catalog_product->getTotalProducts($filter_data);

		$results = $this->model_catalog_product->getProducts($filter_data);

		$this->load->model('localisation/currency');
		$currency_map = [];

		foreach ($this->model_localisation_currency->getCurrencies() as $currency) {
			$currency_map[(int)$currency['currency_id']] = $currency['code'];
		}

		$product_ids = array_map(function($r) { return (int)$r['product_id']; }, $results);
		$categories_by_product = array();
		$option_qty_by_product = array();

		if ($product_ids) {
			$ids_str = implode(',', $product_ids);
			$cat_query = $this->db->query("SELECT p2c.product_id, GROUP_CONCAT(cd.name SEPARATOR ', ') AS categories, GROUP_CONCAT(p2c.category_id SEPARATOR ',') AS category_ids FROM " . DB_PREFIX . "product_to_category p2c LEFT JOIN " . DB_PREFIX . "category_description cd ON (p2c.category_id = cd.category_id AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "') WHERE p2c.product_id IN (" . $ids_str . ") GROUP BY p2c.product_id");

			foreach ($cat_query->rows as $row) {
				$categories_by_product[$row['product_id']] = $row;
			}

			$variant_qty_query = $this->db->query("SELECT pv.product_id, SUM(pv.quantity) AS total_qty, COUNT(*) AS values_count FROM " . DB_PREFIX . "product_configurable pc INNER JOIN " . DB_PREFIX . "product_variant pv ON (pv.product_id = pc.product_id) WHERE pc.is_configurable = '1' AND pv.status = '1' AND pc.product_id IN (" . $ids_str . ") GROUP BY pv.product_id");

			foreach ($variant_qty_query->rows as $row) {
				$option_qty_by_product[$row['product_id']] = array(
					'total_qty'    => (float)$row['total_qty'],
					'values_count' => (int)$row['values_count']
				);
			}
		}

		foreach ($results as $result) {
			if (is_file(DIR_IMAGE . $result['image'])) {
				$image = $this->model_tool_image->resize($result['image'], 40, 40);
			} else {
				$image = $this->model_tool_image->resize('no_image.png', 40, 40);
			}

			$price_info = $this->getPriceDisplayInfo($result['price'], (int)$result['currency_id'], $currency_map);
			$special = false;
			$special_raw = 0;

			$product_specials = $this->model_catalog_product->getProductSpecials($result['product_id']);

			foreach ($product_specials  as $product_special) {
				if (($product_special['date_start'] == '0000-00-00' || strtotime($product_special['date_start']) < time()) && ($product_special['date_end'] == '0000-00-00' || strtotime($product_special['date_end']) > time())) {
					$special_price_info = $this->getPriceDisplayInfo($product_special['price'], (int)$result['currency_id'], $currency_map);
					$special = $special_price_info['formatted'];
					$special_raw = $product_special['price'];

					break;
				}
			}

			$pid = $result['product_id'];
			$cat_data = isset($categories_by_product[$pid]) ? $categories_by_product[$pid] : null;
			$opt_qty = isset($option_qty_by_product[$pid]) ? $option_qty_by_product[$pid] : null;
			$has_options = $opt_qty !== null;
			$option_count = $opt_qty ? (int)$opt_qty['values_count'] : 0;

			if ($has_options && $opt_qty) {
				$display_qty = $this->formatQuantityForDisplay($opt_qty['total_qty']);
			} else {
				$display_qty = $this->formatQuantityForDisplay($result['quantity']);
			}

			$main_cat_id = (int)($result['main_category_id'] ?? 0);
			$cat_display = '';

			if ($cat_data && $cat_data['categories']) {
				$cat_names = array_map('trim', explode(', ', $cat_data['categories']));
				$cat_ids_arr = array_map('intval', explode(',', $cat_data['category_ids']));

				$display_parts = [];
				foreach ($cat_names as $idx => $cat_name) {
					$cid = $cat_ids_arr[$idx] ?? 0;
					if ($main_cat_id && $cid === $main_cat_id) {
						$display_parts[] = '<strong>' . htmlspecialchars($cat_name, ENT_QUOTES, 'UTF-8') . '</strong>';
					} else {
						$display_parts[] = htmlspecialchars($cat_name, ENT_QUOTES, 'UTF-8');
					}
				}
				$cat_display = implode(', ', $display_parts);
			}

			$data['products'][] = array(
				'product_id'    => $pid,
				'image'         => $image,
				'image_path'    => $result['image'],
				'name'          => $result['name'],
				'name_raw'      => $result['name'],
				'model'         => $result['model'],
				'model_raw'     => $result['model'],
				'price'         => $price_info['formatted'],
				'price_raw'     => $result['price'],
				'price_currency_code' => $price_info['code'],
				'special'       => $special,
				'special_raw'   => $special_raw,
				'quantity'      => $display_qty,
				'quantity_raw'  => $has_options ? 0 : $result['quantity'],
				'status'        => $result['status'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
				'status_raw'    => $result['status'],
				'categories'    => $cat_display,
				'categories_raw'=> $cat_data ? $cat_data['category_ids'] : '',
				'has_options'   => $has_options,
				'option_count'  => $option_count,
				'option_qty_sum'=> $opt_qty ? (float)$opt_qty['total_qty'] : 0,
				'option_values_count' => $opt_qty ? (int)$opt_qty['values_count'] : 0,
				'edit'          => $this->url->link('catalog/product/edit', 'user_token=' . $this->session->data['user_token'] . '&product_id=' . $pid . $url, true),
				'copy'          => $this->url->link('catalog/product/copy', 'user_token=' . $this->session->data['user_token'] . '&product_id=' . $pid . $url, true),
				'delete'        => $this->url->link('catalog/product/delete', 'user_token=' . $this->session->data['user_token'] . '&product_id=' . $pid . $url, true)
			);
		}

		$data['user_token'] = $this->session->data['user_token'];

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if (isset($this->request->post['selected'])) {
			$data['selected'] = (array)$this->request->post['selected'];
		} else {
			$data['selected'] = array();
		}

		$url = '';

		if (isset($this->request->get['filter_name'])) {
			$url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_model'])) {
			$url .= '&filter_model=' . urlencode(html_entity_decode($this->request->get['filter_model'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_price'])) {
			$url .= '&filter_price=' . $this->request->get['filter_price'];
		}

		if (isset($this->request->get['filter_sku'])) {
			$url .= '&filter_sku=' . urlencode(html_entity_decode($this->request->get['filter_sku'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_quantity_min'])) {
			$url .= '&filter_quantity_min=' . $this->request->get['filter_quantity_min'];
		}

		if (isset($this->request->get['filter_quantity_max'])) {
			$url .= '&filter_quantity_max=' . $this->request->get['filter_quantity_max'];
		}

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}

		if (isset($this->request->get['filter_manufacturer'])) {
			$url .= '&filter_manufacturer=' . urlencode(html_entity_decode($this->request->get['filter_manufacturer'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_category'])) {
			$url .= '&filter_category=' . urlencode(html_entity_decode($this->request->get['filter_category'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_category_id'])) {
			$url .= '&filter_category_id=' . $this->request->get['filter_category_id'];
		}

		if ($order == 'ASC') {
			$url .= '&order=DESC';
		} else {
			$url .= '&order=ASC';
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['sort_name'] = $this->url->link('catalog/product', 'user_token=' . $this->session->data['user_token'] . '&sort=pd.name' . $url, true);
		$data['sort_model'] = $this->url->link('catalog/product', 'user_token=' . $this->session->data['user_token'] . '&sort=p.model' . $url, true);
		$data['sort_price'] = $this->url->link('catalog/product', 'user_token=' . $this->session->data['user_token'] . '&sort=p.price' . $url, true);
		$data['sort_quantity'] = $this->url->link('catalog/product', 'user_token=' . $this->session->data['user_token'] . '&sort=p.quantity' . $url, true);
		$data['sort_status'] = $this->url->link('catalog/product', 'user_token=' . $this->session->data['user_token'] . '&sort=p.status' . $url, true);
		$data['sort_order'] = $this->url->link('catalog/product', 'user_token=' . $this->session->data['user_token'] . '&sort=p.sort_order' . $url, true);

		$url = '';

		if (isset($this->request->get['filter_name'])) {
			$url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_model'])) {
			$url .= '&filter_model=' . urlencode(html_entity_decode($this->request->get['filter_model'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_price'])) {
			$url .= '&filter_price=' . $this->request->get['filter_price'];
		}

		if (isset($this->request->get['filter_sku'])) {
			$url .= '&filter_sku=' . urlencode(html_entity_decode($this->request->get['filter_sku'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_quantity_min'])) {
			$url .= '&filter_quantity_min=' . $this->request->get['filter_quantity_min'];
		}

		if (isset($this->request->get['filter_quantity_max'])) {
			$url .= '&filter_quantity_max=' . $this->request->get['filter_quantity_max'];
		}

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}

		if (isset($this->request->get['filter_manufacturer'])) {
			$url .= '&filter_manufacturer=' . urlencode(html_entity_decode($this->request->get['filter_manufacturer'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_category'])) {
			$url .= '&filter_category=' . urlencode(html_entity_decode($this->request->get['filter_category'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_category_id'])) {
			$url .= '&filter_category_id=' . $this->request->get['filter_category_id'];
		}

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		$pagination = new Pagination();
		$pagination->total = $product_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('catalog/product', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($product_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($product_total - $this->config->get('config_limit_admin'))) ? $product_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $product_total, ceil($product_total / $this->config->get('config_limit_admin')));

		$data['filter_name'] = $filter_name;
		$data['filter_model'] = $filter_model;
		$data['filter_sku'] = $filter_sku;
		$data['filter_search'] = $filter_search;
		$data['filter_price'] = $filter_price;
		$data['filter_quantity_min'] = $filter_quantity_min;
		$data['filter_quantity_max'] = $filter_quantity_max;
		$data['filter_status'] = $filter_status;
		$data['filter_manufacturer'] = $filter_manufacturer;
		$data['filter_category'] = $filter_category;
		$data['filter_category_id'] = $filter_category_id;

		$data['sort'] = $sort;
		$data['order'] = $order;

		$data['text_min'] = $this->language->get('text_min');
		$data['text_max'] = $this->language->get('text_max');
		$data['text_options'] = $this->language->get('text_option') . 's';
		$data['text_variant'] = $this->language->get('text_variant');
		$data['column_category'] = $this->language->get('column_category');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_select_category'] = $this->language->get('text_select_category');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/product_list', $data));
	}

	protected function getForm() {
		$this->load->language('catalog/product');

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_form'] = !isset($this->request->get['product_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');

		// Load additional sections AFTER the base language file so their
		// shared keys (text_add/text_edit) do not override the product ones.
		$this->load->language('catalog/product_bundle');
		$this->load->language('catalog/product_configurable');
		$data['text_form_subtitle'] = !isset($this->request->get['product_id'])
		    ? $this->language->get('text_add_product_subtitle')
		    : $this->language->get('text_edit_product_subtitle');

		$data['text_drag_to_reorder'] = $this->language->get('text_drag_to_reorder');

		// Panel titles
		$data['text_panel_description_title']    = $this->language->get('text_panel_description_title');
		$data['text_panel_description_subtitle'] = $this->language->get('text_panel_description_subtitle');
		$data['text_panel_identifiers_title']    = $this->language->get('text_panel_identifiers_title');
		$data['text_panel_identifiers_subtitle'] = $this->language->get('text_panel_identifiers_subtitle');
		$data['text_panel_organization_title']   = $this->language->get('text_panel_organization_title');
		$data['text_panel_organization_subtitle'] = $this->language->get('text_panel_organization_subtitle');
		$data['text_panel_dimensions_title']     = $this->language->get('text_panel_dimensions_title');
		$data['text_panel_dimensions_subtitle']  = $this->language->get('text_panel_dimensions_subtitle');
		$data['text_panel_settings_title']       = $this->language->get('text_panel_settings_title');
		$data['text_panel_settings_subtitle']    = $this->language->get('text_panel_settings_subtitle');
		$data['text_panel_pricing_title']        = $this->language->get('text_panel_pricing_title');
		$data['text_panel_pricing_subtitle']     = $this->language->get('text_panel_pricing_subtitle');
		$data['text_panel_inventory_title']      = $this->language->get('text_panel_inventory_title');
		$data['text_panel_inventory_subtitle']   = $this->language->get('text_panel_inventory_subtitle');
		$data['text_panel_media_title']          = $this->language->get('text_panel_media_title');
		$data['text_panel_media_subtitle']       = $this->language->get('text_panel_media_subtitle');
		$data['text_panel_attributes_title']     = $this->language->get('text_panel_attributes_title');
		$data['text_panel_attributes_subtitle']  = $this->language->get('text_panel_attributes_subtitle');
		$data['text_help_attributes']         = $this->language->get('text_help_attributes');
		$data['text_attribute_set_selector']   = $this->language->get('text_attribute_set_selector');
		$data['button_attribute_set_load']     = $this->language->get('button_attribute_set_load');
		$data['text_help_attribute_set']       = $this->language->get('text_help_attribute_set');
		$data['text_attribute_set_loaded']     = $this->language->get('text_attribute_set_loaded');
		$data['text_attribute_set_empty']      = $this->language->get('text_attribute_set_empty');
		$data['text_attribute_set_replace_confirm'] = $this->language->get('text_attribute_set_replace_confirm');
		$data['text_no_attribute_sets']       = $this->language->get('text_no_attribute_sets');
		$data['text_panel_options_title']        = $this->language->get('text_panel_options_title');
		$data['text_panel_options_subtitle']     = $this->language->get('text_panel_options_subtitle');
		$data['text_help_options_mode']        = $this->language->get('text_help_options_mode');
		$data['text_option_set_selector']       = $this->language->get('text_option_set_selector');
		$data['button_option_set_load']         = $this->language->get('button_option_set_load');
		$data['text_help_option_set']           = $this->language->get('text_help_option_set');
		$data['text_option_set_loaded']         = $this->language->get('text_option_set_loaded');
		$data['text_option_set_empty']          = $this->language->get('text_option_set_empty');
		$data['text_option_set_replace_confirm'] = $this->language->get('text_option_set_replace_confirm');
		$data['text_no_option_sets']            = $this->language->get('text_no_option_sets');
		$data['text_panel_discounts_title']      = $this->language->get('text_panel_discounts_title');
		$data['text_panel_discounts_subtitle']   = $this->language->get('text_panel_discounts_subtitle');
		$data['text_panel_specials_title']       = $this->language->get('text_panel_specials_title');
		$data['text_panel_specials_subtitle']    = $this->language->get('text_panel_specials_subtitle');
		$data['text_panel_gifts_title']          = $this->language->get('text_panel_gifts_title');
		$data['text_panel_gifts_subtitle']       = $this->language->get('text_panel_gifts_subtitle');
		$data['text_panel_rewards_title']        = $this->language->get('text_panel_rewards_title');
		$data['text_panel_rewards_subtitle']     = $this->language->get('text_panel_rewards_subtitle');
		$data['text_panel_seo_title']            = $this->language->get('text_panel_seo_title');
		$data['text_panel_seo_subtitle']         = $this->language->get('text_panel_seo_subtitle');
		$data['text_panel_design_title']         = $this->language->get('text_panel_design_title');
		$data['text_panel_design_subtitle']      = $this->language->get('text_panel_design_subtitle');

		$data['text_no_discounts'] = $this->language->get('text_no_discounts');
		$data['text_no_specials']  = $this->language->get('text_no_specials');
		$data['text_no_gifts']     = $this->language->get('text_no_gifts');
		$data['text_panel_promo_title']    = $this->language->get('text_panel_promo_title');
		$data['text_panel_promo_subtitle'] = $this->language->get('text_panel_promo_subtitle');
		$data['text_no_promo']             = $this->language->get('text_no_promo');
		$data['text_promo_discount']       = $this->language->get('text_promo_discount');
		$data['text_promo_special']        = $this->language->get('text_promo_special');
		$data['text_promo_gift']           = $this->language->get('text_promo_gift');
		$data['text_promo_bxgy']          = $this->language->get('text_promo_bxgy');
		$data['button_promo_add']          = $this->language->get('button_promo_add');
		$data['text_no_bxgy']             = $this->language->get('text_no_bxgy');
		$data['entry_bxgy_reward_product']   = $this->language->get('entry_bxgy_reward_product');
		$data['entry_bxgy_trigger_quantity'] = $this->language->get('entry_bxgy_trigger_quantity');
		$data['entry_bxgy_discount_value']   = $this->language->get('entry_bxgy_discount_value');
		$data['help_bxgy_reward_product']    = $this->language->get('help_bxgy_reward_product');
		$data['help_bxgy_trigger_quantity']  = $this->language->get('help_bxgy_trigger_quantity');
		$data['help_bxgy_discount_value']    = $this->language->get('help_bxgy_discount_value');

		$data['text_panel_bundles_title']    = $this->language->get('text_panel_bundles_title');
		$data['text_panel_bundles_subtitle'] = $this->language->get('text_panel_bundles_subtitle');
		$data['text_no_bundles']             = $this->language->get('text_no_bundles');
		$data['text_promo_bundle']           = $this->language->get('text_promo_bundle');
		$data['button_bundle_add']           = $this->language->get('button_bundle_add');
		$data['button_bundle_edit']          = $this->language->get('button_bundle_edit');
		$data['button_bundle_remove']        = $this->language->get('button_bundle_remove');
		$data['button_bundle_delete']        = $this->language->get('button_bundle_delete');
		$data['entry_bundle_name']           = $this->language->get('entry_bundle_name');
		$data['entry_bundle_products']       = $this->language->get('entry_bundle_products');
		$data['help_bundle_products']        = $this->language->get('help_bundle_products');
		$data['error_bundle_products']       = $this->language->get('error_bundle_products');
		$data['error_bundle_discount']       = $this->language->get('error_bundle_discount');
		$data['text_bundle_delete_confirm']  = $this->language->get('text_bundle_delete_confirm');
		$data['text_bundle_remove_confirm']  = $this->language->get('text_bundle_remove_confirm');
		$data['text_bundle_other_products']  = $this->language->get('text_bundle_other_products');

		// Sidebar card titles
		$data['text_seo_card']    = $this->language->get('text_seo_card');
		$data['text_design_card'] = $this->language->get('text_design_card');
		$data['text_seo_preview'] = $this->language->get('text_seo_preview');
		$data['text_organization_card'] = $this->language->get('text_organization_card');
		$data['text_status_card'] = $this->language->get('text_status_card');
		$data['entry_main_category'] = $this->language->get('entry_main_category');
		$data['help_main_category'] = $this->language->get('help_main_category');
		$data['help_category'] = $this->language->get('help_category');
		$data['button_select_category'] = $this->language->get('button_select_category');
		$data['text_select_category'] = $this->language->get('text_select_category');
		$data['entry_categories_display'] = $this->language->get('entry_categories_display');
		$data['text_picker_type_to_search'] = $this->language->get('text_picker_type_to_search');
		$data['text_picker_no_results'] = $this->language->get('text_picker_no_results');
		$data['text_picker_error'] = $this->language->get('text_picker_error');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['name'])) {
			$data['error_name'] = $this->error['name'];
		} else {
			$data['error_name'] = array();
		}

		if (isset($this->error['meta_title'])) {
			$data['error_meta_title'] = $this->error['meta_title'];
		} else {
			$data['error_meta_title'] = array();
		}

		if (isset($this->error['model'])) {
			$data['error_model'] = $this->error['model'];
		} else {
			$data['error_model'] = '';
		}

		if (isset($this->error['keyword'])) {
			$data['error_keyword'] = $this->error['keyword'];
		} else {
			$data['error_keyword'] = '';
		}

		if (isset($this->error['option'])) {
			$data['error_option'] = $this->error['option'];
		} else {
			$data['error_option'] = array();
		}

		if (isset($this->error['default_variant'])) {
			$data['error_default_variant'] = $this->error['default_variant'];
		} else {
			$data['error_default_variant'] = '';
		}

		$url = '';

		if (isset($this->request->get['filter_name'])) {
			$url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_model'])) {
			$url .= '&filter_model=' . urlencode(html_entity_decode($this->request->get['filter_model'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_price'])) {
			$url .= '&filter_price=' . $this->request->get['filter_price'];
		}

		if (isset($this->request->get['filter_quantity'])) {
			$url .= '&filter_quantity=' . $this->request->get['filter_quantity'];
		}

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
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

		if (!isset($this->request->get['product_id'])) {
			$data['action'] = $this->url->link('catalog/product/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		} else {
			$data['action'] = $this->url->link('catalog/product/edit', 'user_token=' . $this->session->data['user_token'] . '&product_id=' . $this->request->get['product_id'] . $url, true);
		}

		$data['cancel'] = $this->url->link('catalog/product', 'user_token=' . $this->session->data['user_token'] . $url, true);

		if (isset($this->request->get['product_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$product_info = $this->model_catalog_product->getProduct($this->request->get['product_id']);
		}

		$data['user_token'] = $this->session->data['user_token'];

		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();

		if (isset($this->request->post['product_description'])) {
			$data['product_description'] = $this->request->post['product_description'];
		} elseif (isset($this->request->get['product_id'])) {
			$data['product_description'] = $this->model_catalog_product->getProductDescriptions($this->request->get['product_id']);
		} else {
			$data['product_description'] = array();
		}

		$data['product_description'] = $this->decodeDescriptionFields($data['product_description'], array('name', 'meta_title'));

		// Product name for page header
		if (isset($this->request->post['product_description'][$this->config->get('config_language_id')]['name'])) {
			$data['product_name'] = $this->request->post['product_description'][$this->config->get('config_language_id')]['name'];
		} elseif (!empty($product_info)) {
			$descriptions = $this->model_catalog_product->getProductDescriptions($this->request->get['product_id']);
			$data['product_name'] = isset($descriptions[$this->config->get('config_language_id')]['name']) ? $descriptions[$this->config->get('config_language_id')]['name'] : '';
		} else {
			$data['product_name'] = '';
		}

		if (isset($this->request->post['model'])) {
			$data['model'] = $this->request->post['model'];
		} elseif (!empty($product_info)) {
			$data['model'] = $product_info['model'];
		} else {
			$data['model'] = '';
		}

		if (isset($this->request->post['main_category_id'])) {
			$data['main_category_id'] = $this->request->post['main_category_id'];
		} elseif (!empty($product_info)) {
			$data['main_category_id'] = $product_info['main_category_id'];
		} else {
			$data['main_category_id'] = 0;
		}

		if (isset($this->request->post['sku'])) {
			$data['sku'] = $this->request->post['sku'];
		} elseif (!empty($product_info)) {
			$data['sku'] = $product_info['sku'];
		} else {
			$data['sku'] = '';
		}

		if (isset($this->request->post['upc'])) {
			$data['upc'] = $this->request->post['upc'];
		} elseif (!empty($product_info)) {
			$data['upc'] = $product_info['upc'];
		} else {
			$data['upc'] = '';
		}

		if (isset($this->request->post['ean'])) {
			$data['ean'] = $this->request->post['ean'];
		} elseif (!empty($product_info)) {
			$data['ean'] = $product_info['ean'];
		} else {
			$data['ean'] = '';
		}

		if (isset($this->request->post['jan'])) {
			$data['jan'] = $this->request->post['jan'];
		} elseif (!empty($product_info)) {
			$data['jan'] = $product_info['jan'];
		} else {
			$data['jan'] = '';
		}

		if (isset($this->request->post['isbn'])) {
			$data['isbn'] = $this->request->post['isbn'];
		} elseif (!empty($product_info)) {
			$data['isbn'] = $product_info['isbn'];
		} else {
			$data['isbn'] = '';
		}

		if (isset($this->request->post['mpn'])) {
			$data['mpn'] = $this->request->post['mpn'];
		} elseif (!empty($product_info)) {
			$data['mpn'] = $product_info['mpn'];
		} else {
			$data['mpn'] = '';
		}

		if (isset($this->request->post['location'])) {
			$data['location'] = $this->request->post['location'];
		} elseif (!empty($product_info)) {
			$data['location'] = $product_info['location'];
		} else {
			$data['location'] = '';
		}

		$this->load->model('setting/store');

		$data['stores'] = array();

		$data['stores'][] = array(
			'store_id' => 0,
			'name'     => $this->language->get('text_default')
		);

		$stores = $this->model_setting_store->getStores();

		foreach ($stores as $store) {
			$data['stores'][] = array(
				'store_id' => $store['store_id'],
				'name'     => $store['name']
			);
		}

		if (isset($this->request->post['product_store'])) {
			$data['product_store'] = $this->request->post['product_store'];
		} elseif (isset($this->request->get['product_id'])) {
			$data['product_store'] = $this->model_catalog_product->getProductStores($this->request->get['product_id']);
		} else {
			$data['product_store'] = array(0);
		}

		if (isset($this->request->post['shipping'])) {
			$data['shipping'] = $this->request->post['shipping'];
		} elseif (!empty($product_info)) {
			$data['shipping'] = $product_info['shipping'];
		} else {
			$data['shipping'] = 1;
		}

		if (isset($this->request->post['price'])) {
			$data['price'] = $this->request->post['price'];
		} elseif (!empty($product_info)) {
			$data['price'] = $product_info['price'];
		} else {
			$data['price'] = '';
		}

		if (isset($this->request->post['call_for_price'])) {
			$data['call_for_price'] = $this->request->post['call_for_price'];
		} elseif (!empty($product_info)) {
			$data['call_for_price'] = $product_info['call_for_price'];
		} else {
			$data['call_for_price'] = 0;
		}

		$this->load->model('localisation/tax_class');

		$data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();

		if (isset($this->request->post['tax_class_id'])) {
			$data['tax_class_id'] = $this->request->post['tax_class_id'];
		} elseif (!empty($product_info)) {
			$data['tax_class_id'] = $product_info['tax_class_id'];
		} else {
			$data['tax_class_id'] = 0;
		}

		if (isset($this->request->post['date_available'])) {
			$data['date_available'] = $this->request->post['date_available'];
		} elseif (!empty($product_info)) {
			$data['date_available'] = ($product_info['date_available'] != '0000-00-00') ? $product_info['date_available'] : '';
		} else {
			$data['date_available'] = date('Y-m-d');
		}

		if (isset($this->request->post['quantity'])) {
			$data['quantity'] = $this->request->post['quantity'];
		} elseif (!empty($product_info)) {
			$data['quantity'] = $this->formatQuantityForDisplay($product_info['quantity']);
		} else {
			$data['quantity'] = 1;
		}

		if (isset($this->request->post['minimum'])) {
			$data['minimum'] = $this->request->post['minimum'];
		} elseif (!empty($product_info)) {
			$data['minimum'] = $this->formatQuantityForDisplay($product_info['minimum']);
		} else {
			$data['minimum'] = 1;
		}

		if (isset($this->request->post['quantity_step'])) {
			$data['quantity_step'] = $this->request->post['quantity_step'];
		} elseif (!empty($product_info) && isset($product_info['quantity_step'])) {
			$data['quantity_step'] = $this->formatQuantityForDisplay($product_info['quantity_step']);
		} else {
			$data['quantity_step'] = 1;
		}

		if (isset($this->request->post['subtract'])) {
			$data['subtract'] = $this->request->post['subtract'];
		} elseif (!empty($product_info)) {
			$data['subtract'] = $product_info['subtract'];
		} else {
			$data['subtract'] = 1;
		}

		if (isset($this->request->post['sort_order'])) {
			$data['sort_order'] = $this->request->post['sort_order'];
		} elseif (!empty($product_info)) {
			$data['sort_order'] = $product_info['sort_order'];
		} else {
			$data['sort_order'] = 1;
		}

		if (isset($this->request->post['preorder'])) {
			$data['preorder'] = (int)$this->request->post['preorder'];
		} elseif (!empty($product_info)) {
			$data['preorder'] = (int)$product_info['preorder'];
		} else {
			$data['preorder'] = 0;
		}

		if (isset($this->request->post['discontinued'])) {
			$data['discontinued'] = (int)$this->request->post['discontinued'];
		} elseif (!empty($product_info)) {
			$data['discontinued'] = (int)$product_info['discontinued'];
		} else {
			$data['discontinued'] = 0;
		}

		if (isset($this->request->post['status'])) {
			$data['status'] = $this->request->post['status'];
		} elseif (!empty($product_info)) {
			$data['status'] = $product_info['status'];
		} else {
			$data['status'] = true;
		}

		if (isset($this->request->post['weight'])) {
			$data['weight'] = $this->request->post['weight'];
		} elseif (!empty($product_info)) {
			$data['weight'] = $product_info['weight'];
		} else {
			$data['weight'] = '';
		}

		$this->load->model('localisation/weight_class');

		$data['weight_classes'] = $this->model_localisation_weight_class->getWeightClasses();

		if (isset($this->request->post['weight_class_id'])) {
			$data['weight_class_id'] = $this->request->post['weight_class_id'];
		} elseif (!empty($product_info)) {
			$data['weight_class_id'] = $product_info['weight_class_id'];
		} else {
			$data['weight_class_id'] = $this->config->get('config_weight_class_id');
		}

		if (isset($this->request->post['length'])) {
			$data['length'] = $this->request->post['length'];
		} elseif (!empty($product_info)) {
			$data['length'] = $product_info['length'];
		} else {
			$data['length'] = '';
		}

		if (isset($this->request->post['width'])) {
			$data['width'] = $this->request->post['width'];
		} elseif (!empty($product_info)) {
			$data['width'] = $product_info['width'];
		} else {
			$data['width'] = '';
		}

		if (isset($this->request->post['height'])) {
			$data['height'] = $this->request->post['height'];
		} elseif (!empty($product_info)) {
			$data['height'] = $product_info['height'];
		} else {
			$data['height'] = '';
		}

		$this->load->model('localisation/length_class');

		$data['length_classes'] = $this->model_localisation_length_class->getLengthClasses();

		if (isset($this->request->post['length_class_id'])) {
			$data['length_class_id'] = $this->request->post['length_class_id'];
		} elseif (!empty($product_info)) {
			$data['length_class_id'] = $product_info['length_class_id'];
		} else {
			$data['length_class_id'] = $this->config->get('config_length_class_id');
		}

		$this->load->model('catalog/manufacturer');

		if (isset($this->request->post['manufacturer_id'])) {
			$data['manufacturer_id'] = $this->request->post['manufacturer_id'];
		} elseif (!empty($product_info)) {
			$data['manufacturer_id'] = $product_info['manufacturer_id'];
		} else {
			$data['manufacturer_id'] = 0;
		}

		if (isset($this->request->post['manufacturer'])) {
			$data['manufacturer'] = $this->request->post['manufacturer'];
		} elseif (!empty($product_info)) {
			$manufacturer_info = $this->model_catalog_manufacturer->getManufacturer($product_info['manufacturer_id']);

			if ($manufacturer_info) {
				$data['manufacturer'] = $manufacturer_info['name'];
			} else {
				$data['manufacturer'] = '';
			}
		} else {
			$data['manufacturer'] = '';
		}

		$data['manufacturer'] = $this->decodeHtmlEntitiesForDisplay($data['manufacturer']);

		// Categories
		$this->load->model('catalog/category');

		if (isset($this->request->post['product_category'])) {
			$categories = $this->request->post['product_category'];
		} elseif (isset($this->request->get['product_id'])) {
			$categories = $this->model_catalog_product->getProductCategories($this->request->get['product_id']);
		} else {
			$categories = array();
		}

		$data['product_categories'] = array();

		foreach ($categories as $category_id) {
			$category_info = $this->model_catalog_category->getCategory($category_id);

			if ($category_info) {
				$data['product_categories'][] = array(
					'category_id' => $category_info['category_id'],
					'name'        => ($category_info['path']) ? $category_info['path'] . ' &gt; ' . $category_info['name'] : $category_info['name']
				);
			}
		}

		// Main category name
		$data['main_category_name'] = '';
		if (!empty($data['main_category_id'])) {
			$main_cat = $this->model_catalog_category->getCategory($data['main_category_id']);
			if ($main_cat) {
				$data['main_category_name'] = ($main_cat['path']) ? $main_cat['path'] . ' &gt; ' . $main_cat['name'] : $main_cat['name'];
			}
		}

		// Attributes
		$this->load->model('catalog/attribute');

		if (isset($this->request->post['product_attribute'])) {
			$product_attributes = $this->request->post['product_attribute'];
		} elseif (isset($this->request->get['product_id'])) {
			$product_attributes = $this->model_catalog_product->getProductAttributes($this->request->get['product_id']);
		} else {
			$product_attributes = array();
		}

		$data['product_attributes'] = array();

		foreach ($product_attributes as $product_attribute) {
			$attribute_info = $this->model_catalog_attribute->getAttribute($product_attribute['attribute_id']);

			if ($attribute_info) {
				$data['product_attributes'][] = array(
					'attribute_id'                  => $product_attribute['attribute_id'],
					'name'                          => $this->decodeHtmlEntitiesForDisplay($attribute_info['name']),
					'product_attribute_description' => $product_attribute['product_attribute_description']
				);
			}
		}

		// Attribute sets (quick-load bundles of attributes)
		$this->load->model('catalog/attribute_set');

		$data['attribute_sets'] = $this->model_catalog_attribute_set->getAttributeSets(array(
			'sort'  => 'astd.name',
			'order' => 'ASC'
		));

		// Option sets (quick-load bundles of options)
		$this->load->model('catalog/option_set');

		$data['option_sets'] = $this->model_catalog_option_set->getOptionSets(array(
			'sort'  => 'ostd.name',
			'order' => 'ASC'
		));

		// Options
		$this->load->model('catalog/option');

		if (isset($this->request->post['product_option'])) {
			$product_options = $this->request->post['product_option'];
		} elseif (isset($this->request->get['product_id'])) {
			$product_options = $this->model_catalog_product->getProductOptions($this->request->get['product_id']);
		} else {
			$product_options = array();
		}

		$data['product_options'] = array();

		foreach ($product_options as $product_option) {
			$product_option_value_data = array();

			if (isset($product_option['product_option_value'])) {
				foreach ($product_option['product_option_value'] as $product_option_value) {
				$product_option_value_data[] = array(
					'product_option_value_id' => $product_option_value['product_option_value_id'],
					'option_value_id'         => $product_option_value['option_value_id'],
					'price'                   => $product_option_value['price'],
					'price_prefix'            => isset($product_option_value['price_prefix']) ? $product_option_value['price_prefix'] : '+',
					'points'                  => $product_option_value['points'],
					'points_prefix'           => isset($product_option_value['points_prefix']) ? $product_option_value['points_prefix'] : '+',
					'weight'                  => $product_option_value['weight'],
					'weight_prefix'           => isset($product_option_value['weight_prefix']) ? $product_option_value['weight_prefix'] : '+',
					'is_hit'                  => isset($product_option_value['is_hit']) ? (int)$product_option_value['is_hit'] : 0,
					'customer_group_prices'   => isset($product_option_value['customer_group_prices']) ? $product_option_value['customer_group_prices'] : array()
				);
				}
			}

			$data['product_options'][] = array(
				'product_option_id'    => $product_option['product_option_id'],
				'product_option_value' => $product_option_value_data,
				'option_id'            => $product_option['option_id'],
				'name'                 => $this->decodeHtmlEntitiesForDisplay($product_option['name']),
				'type'                 => $product_option['type'],
				'value'                => isset($product_option['value']) ? $product_option['value'] : '',
				'required'             => $product_option['required'],
				'is_axis'              => false
			);
		}

		$this->load->model('catalog/product_configurable');

		$product_id_for_form = isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0;
		$data['product_id'] = $product_id_for_form;
		$data['is_configurable'] = false;
		$data['axis_option_ids'] = array();

		if ($product_id_for_form) {
			$data['is_configurable'] = $this->model_catalog_product_configurable->isConfigurable($product_id_for_form);

			if ($data['is_configurable']) {
				$axes = $this->model_catalog_product_configurable->getConfigurableOptions($product_id_for_form);

				foreach ($axes as $axis) {
					$data['axis_option_ids'][] = (int)$axis['option_id'];
				}

				foreach ($data['product_options'] as &$po) {
					if (in_array((int)$po['option_id'], $data['axis_option_ids'])) {
						$po['is_axis'] = true;
					}
				}

			unset($po);
			}
		}

		$data['option_values'] = array();

		foreach ($data['product_options'] as $product_option) {
			if ($product_option['type'] == 'select' || $product_option['type'] == 'radio' || $product_option['type'] == 'checkbox' || $product_option['type'] == 'image' || $product_option['type'] == 'color') {
				if (!isset($data['option_values'][$product_option['option_id']])) {
					$data['option_values'][$product_option['option_id']] = $this->model_catalog_option->getOptionValues($product_option['option_id']);
				}
			}
		}

		$this->load->model('customer/customer_group');

		$data['customer_groups'] = $this->model_customer_customer_group->getCustomerGroups();

		if (isset($this->request->post['product_discount'])) {
			$product_discounts = $this->request->post['product_discount'];
		} elseif (isset($this->request->get['product_id'])) {
			$product_discounts = $this->model_catalog_product->getProductDiscounts($this->request->get['product_id']);
		} else {
			$product_discounts = array();
		}

		$data['product_discounts'] = array();

		foreach ($product_discounts as $product_discount) {
			$data['product_discounts'][] = array(
				'customer_group_id' => $product_discount['customer_group_id'],
				'quantity'          => $product_discount['quantity'],
				'priority'          => $product_discount['priority'],
				'price'             => $product_discount['price'],
				'date_start'        => ($product_discount['date_start'] != '0000-00-00') ? $product_discount['date_start'] : '',
				'date_end'          => ($product_discount['date_end'] != '0000-00-00') ? $product_discount['date_end'] : '',
				'auto_renew'        => !empty($product_discount['auto_renew']),
				'date_added'        => $product_discount['date_added'] ?? '0000-00-00 00:00:00'
			);
		}

		if (isset($this->request->post['product_special'])) {
			$product_specials = $this->request->post['product_special'];
		} elseif (isset($this->request->get['product_id'])) {
			$product_specials = $this->model_catalog_product->getProductSpecials($this->request->get['product_id']);
		} else {
			$product_specials = array();
		}

		$data['product_specials'] = array();

		foreach ($product_specials as $product_special) {
			$data['product_specials'][] = array(
				'customer_group_id' => $product_special['customer_group_id'],
				'priority'          => $product_special['priority'],
				'price'             => $product_special['price'],
				'date_start'        => ($product_special['date_start'] != '0000-00-00') ? $product_special['date_start'] : '',
				'date_end'          => ($product_special['date_end'] != '0000-00-00') ? $product_special['date_end'] : '',
				'auto_renew'        => !empty($product_special['auto_renew']),
				'date_added'        => $product_special['date_added'] ?? '0000-00-00 00:00:00'
			);
		}

		if (isset($this->request->post['product_gift'])) {
			$product_gifts = $this->request->post['product_gift'];
		} elseif (isset($this->request->get['product_id'])) {
			$product_gifts = $this->model_catalog_product->getProductGifts($this->request->get['product_id']);
		} else {
			$product_gifts = array();
		}

		$data['product_gifts'] = array();

		foreach ($product_gifts as $product_gift) {
			$data['product_gifts'][] = array(
				'gift_product_id'   => $product_gift['gift_product_id'],
				'gift_product_name' => $product_gift['gift_product_name'] ?? '',
				'minimum_quantity'  => $product_gift['minimum_quantity'],
				'date_start'        => ($product_gift['date_start'] != '0000-00-00') ? $product_gift['date_start'] : '',
				'date_end'          => ($product_gift['date_end'] != '0000-00-00') ? $product_gift['date_end'] : '',
				'auto_renew'        => !empty($product_gift['auto_renew']),
				'date_added'        => $product_gift['date_added'] ?? '0000-00-00 00:00:00'
			);
		}

		if (isset($this->request->post['product_bxgy'])) {
			$product_bxgy_rules = $this->request->post['product_bxgy'];
		} elseif (isset($this->request->get['product_id'])) {
			$product_bxgy_rules = $this->model_catalog_product->getProductBxgy($this->request->get['product_id']);
		} else {
			$product_bxgy_rules = array();
		}

		$data['product_bxgy_rules'] = array();

		foreach ($product_bxgy_rules as $product_bxgy) {
			$data['product_bxgy_rules'][] = array(
				'product_bxgy_id'    => $product_bxgy['product_bxgy_id'] ?? 0,
				'reward_product_id'  => $product_bxgy['reward_product_id'],
				'reward_product_name' => $product_bxgy['reward_product_name'] ?? '',
				'trigger_quantity'   => $product_bxgy['trigger_quantity'],
				'discount_type'      => $product_bxgy['discount_type'],
				'discount_value'     => $product_bxgy['discount_value'],
				'date_start'         => ($product_bxgy['date_start'] != '0000-00-00') ? $product_bxgy['date_start'] : '',
				'date_end'           => ($product_bxgy['date_end'] != '0000-00-00') ? $product_bxgy['date_end'] : '',
				'auto_renew'         => !empty($product_bxgy['auto_renew']),
				'date_added'         => $product_bxgy['date_added'] ?? '0000-00-00 00:00:00'
			);
		}

		if (isset($this->request->post['product_customer_group_price'])) {
			$product_customer_group_prices = $this->request->post['product_customer_group_price'];
		} elseif (isset($this->request->get['product_id'])) {
			$product_customer_group_prices = $this->model_catalog_product->getProductCustomerGroupPrices($this->request->get['product_id']);
		} else {
			$product_customer_group_prices = array();
		}

		$data['product_customer_group_prices'] = array();

		foreach ($product_customer_group_prices as $product_customer_group_price) {
			$data['product_customer_group_prices'][] = array(
				'customer_group_id' => $product_customer_group_price['customer_group_id'],
				'price'             => $product_customer_group_price['price']
			);
		}

		// Image
		if (isset($this->request->post['image'])) {
			$data['image'] = $this->request->post['image'];
		} elseif (!empty($product_info)) {
			$data['image'] = $product_info['image'];
		} else {
			$data['image'] = '';
		}

		$this->load->model('tool/image');

		if (isset($this->request->post['image']) && is_file(DIR_IMAGE . $this->request->post['image'])) {
			$data['thumb'] = $this->model_tool_image->resize($this->request->post['image'], 260, 200);
		} elseif (!empty($product_info) && is_file(DIR_IMAGE . $product_info['image'])) {
			$data['thumb'] = $this->model_tool_image->resize($product_info['image'], 260, 200);
		} else {
			$data['thumb'] = $this->model_tool_image->resize('no_image.png', 260, 200);
		}

		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', 260, 200);

		// Images
		if (isset($this->request->post['product_image'])) {
			$product_images = $this->request->post['product_image'];
		} elseif (isset($this->request->get['product_id'])) {
			$product_images = $this->model_catalog_product->getProductImages($this->request->get['product_id']);
		} else {
			$product_images = array();
		}

		$data['product_images_global'] = array();
		$data['product_images_by_lang'] = array();

		// Separate into global (language_id is NULL/empty) and per-language
		$images_global = array();
		$images_by_lang = array();

		foreach ($product_images as $pi) {
			$lang_id = isset($pi['language_id']) ? $pi['language_id'] : null;
			if (empty($lang_id)) {
				$images_global[] = $pi;
			} else {
				$lang = (int) $lang_id;
				if (!isset($images_by_lang[$lang])) {
					$images_by_lang[$lang] = array();
				}
				$images_by_lang[$lang][] = $pi;
			}
		}

		foreach ($images_global as $product_image) {
			if (is_file(DIR_IMAGE . $product_image['image'])) {
				$image = $product_image['image'];
				$thumb = $product_image['image'];
			} else {
				$image = '';
				$thumb = 'no_image.png';
			}

			$data['product_images_global'][] = array(
				'image'       => $image,
				'thumb'       => $this->model_tool_image->resize($thumb, 100, 100),
				'sort_order'  => $product_image['sort_order'],
				'language_id' => ''
			);
		}

		foreach ($images_by_lang as $lang_id => $lang_images) {
			$data['product_images_by_lang'][$lang_id] = array();
			foreach ($lang_images as $product_image) {
				if (is_file(DIR_IMAGE . $product_image['image'])) {
					$image = $product_image['image'];
					$thumb = $product_image['image'];
				} else {
					$image = '';
					$thumb = 'no_image.png';
				}

				$data['product_images_by_lang'][$lang_id][] = array(
					'image'       => $image,
					'thumb'       => $this->model_tool_image->resize($thumb, 100, 100),
					'sort_order'  => $product_image['sort_order'],
					'language_id' => $lang_id
				);
			}
		}

		// Video
		if (isset($this->request->post['video_type']) && isset($this->request->post['video'])) {
			$data['video_type'] = $this->request->post['video_type'];
			$data['video'] = $this->request->post['video'];
		} elseif (isset($this->request->get['product_id'])) {
			$product_videos = $this->model_catalog_product->getProductVideos($this->request->get['product_id']);
			if (!empty($product_videos)) {
				$first_video = $product_videos[0];
				$data['video_type'] = $first_video['video_type'];
				$data['video'] = $first_video['video'];
			} else {
				$data['video_type'] = '';
				$data['video'] = '';
			}
		} else {
			$data['video_type'] = '';
			$data['video'] = '';
		}

		$video_id = $this->extractYouTubeId((string)$data['video']);
		$data['video_youtube_id'] = preg_match('/^[A-Za-z0-9_-]{11}$/', $video_id) ? $video_id : '';

		$data['video_thumb'] = $this->model_tool_image->resize('video_placeholder.svg', 100, 100);
		$data['video_placeholder'] = $this->model_tool_image->resize('video_placeholder.svg', 100, 100);

		// 3D Model
		if (isset($this->request->post['model_3d'])) {
			$data['model_3d'] = $this->request->post['model_3d'];
		} elseif (!empty($product_info)) {
			$data['model_3d'] = $product_info['model_3d'];
		} else {
			$data['model_3d'] = '';
		}

		if (!empty($data['model_3d']) && is_file(DIR_IMAGE . $data['model_3d'])) {
			$data['model_3d_thumb'] = $this->model_tool_image->resize('model_3d_placeholder.svg', 100, 100);
		} else {
			$data['model_3d_thumb'] = $this->model_tool_image->resize('model_3d_placeholder.svg', 100, 100);
		}
		$data['model_3d_placeholder'] = $this->model_tool_image->resize('model_3d_placeholder.svg', 100, 100);

		// 360° Image
		if (isset($this->request->post['image_360'])) {
			$data['image_360'] = $this->request->post['image_360'];
		} elseif (!empty($product_info)) {
			$data['image_360'] = $product_info['image_360'];
		} else {
			$data['image_360'] = '';
		}

		$data['image_360_thumb'] = $this->model_tool_image->resize('image_360_placeholder.svg', 100, 100);
		$data['image_360_placeholder'] = $this->model_tool_image->resize('image_360_placeholder.svg', 100, 100);

		// Downloads
		$this->load->model('catalog/download');

		if (isset($this->request->post['product_download'])) {
			$product_downloads = $this->request->post['product_download'];
		} elseif (isset($this->request->get['product_id'])) {
			$product_downloads = $this->model_catalog_product->getProductDownloads($this->request->get['product_id']);
		} else {
			$product_downloads = array();
		}

		$data['product_downloads'] = array();

		foreach ($product_downloads as $download_id) {
			$download_info = $this->model_catalog_download->getDownload($download_id);

			if ($download_info) {
				$data['product_downloads'][] = array(
					'download_id' => $download_info['download_id'],
					'name'        => $download_info['name']
				);
			}
		}

		if (isset($this->request->post['product_related'])) {
			$products = $this->request->post['product_related'];
		} elseif (isset($this->request->get['product_id'])) {
			$products = $this->model_catalog_product->getProductRelated($this->request->get['product_id']);
		} else {
			$products = array();
		}

		$data['product_relateds'] = array();

		foreach ($products as $product_id) {
			$related_info = $this->model_catalog_product->getProduct($product_id);

			if ($related_info) {
				$data['product_relateds'][] = array(
					'product_id' => $related_info['product_id'],
					'name'       => $related_info['name']
				);
			}
		}

		if (isset($this->request->post['product_upsell'])) {
			$products = $this->request->post['product_upsell'];
		} elseif (isset($this->request->get['product_id'])) {
			$products = $this->model_catalog_product->getProductUpsell($this->request->get['product_id']);
		} else {
			$products = array();
		}

		$data['product_upsells'] = array();

		foreach ($products as $product_id) {
			$upsell_info = $this->model_catalog_product->getProduct($product_id);

			if ($upsell_info) {
				$data['product_upsells'][] = array(
					'product_id' => $upsell_info['product_id'],
					'name'       => $upsell_info['name']
				);
			}
		}

		if (isset($this->request->post['product_accessory'])) {
			$products = $this->request->post['product_accessory'];
		} elseif (isset($this->request->get['product_id'])) {
			$products = $this->model_catalog_product->getProductAccessory($this->request->get['product_id']);
		} else {
			$products = array();
		}

		$data['product_accessories'] = array();

		foreach ($products as $product_id) {
			$accessory_info = $this->model_catalog_product->getProduct($product_id);

			if ($accessory_info) {
				$data['product_accessories'][] = array(
					'product_id' => $accessory_info['product_id'],
					'name'       => $accessory_info['name']
				);
			}
		}

		if (isset($this->request->post['product_fbt'])) {
			$products = $this->request->post['product_fbt'];
		} elseif (isset($this->request->get['product_id'])) {
			$products = $this->model_catalog_product->getProductFbt($this->request->get['product_id']);
		} else {
			$products = array();
		}

		$data['product_fbt'] = array();

		foreach ($products as $product_id) {
			$fbt_info = $this->model_catalog_product->getProduct($product_id);

			if ($fbt_info) {
				$data['product_fbt'][] = array(
					'product_id' => $fbt_info['product_id'],
					'name'       => $fbt_info['name']
				);
			}
		}

		if (isset($this->request->post['product_similar'])) {
			$products = $this->request->post['product_similar'];
		} elseif (isset($this->request->get['product_id'])) {
			$products = $this->model_catalog_product->getProductSimilar($this->request->get['product_id']);
		} else {
			$products = array();
		}

		$data['product_similars'] = array();

		foreach ($products as $product_id) {
			$similar_info = $this->model_catalog_product->getProduct($product_id);

			if ($similar_info) {
				$data['product_similars'][] = array(
					'product_id' => $similar_info['product_id'],
					'name'       => $similar_info['name']
				);
			}
		}

		if (isset($this->request->post['points'])) {
			$data['points'] = $this->request->post['points'];
		} elseif (!empty($product_info)) {
			$data['points'] = $product_info['points'];
		} else {
			$data['points'] = '';
		}

		if (isset($this->request->post['product_reward'])) {
			$data['product_reward'] = $this->request->post['product_reward'];
		} elseif (isset($this->request->get['product_id'])) {
			$data['product_reward'] = $this->model_catalog_product->getProductRewards($this->request->get['product_id']);
		} else {
			$data['product_reward'] = array();
		}

		if (isset($this->request->post['product_seo_url'])) {
			$data['product_seo_url'] = $this->request->post['product_seo_url'];
		} elseif (isset($this->request->get['product_id'])) {
			$data['product_seo_url'] = $this->model_catalog_product->getProductSeoUrls($this->request->get['product_id']);
		} else {
			$data['product_seo_url'] = array();
		}

		if (isset($this->request->post['product_layout'])) {
			$data['product_layout'] = $this->request->post['product_layout'];
		} elseif (isset($this->request->get['product_id'])) {
			$data['product_layout'] = $this->model_catalog_product->getProductLayouts($this->request->get['product_id']);
		} else {
			$data['product_layout'] = array();
		}

		$this->load->model('design/layout');

		$data['layouts'] = $this->model_design_layout->getLayouts();

		$this->load->language('catalog/product_configurable');

		$this->load->model('catalog/product_bundle');

		$data['product_bundles'] = array();

		if (isset($this->request->get['product_id'])) {
			$bundle_results = $this->model_catalog_product_bundle->getBundlesByProduct((int) $this->request->get['product_id']);

			foreach ($bundle_results as $bundle) {
				$bundle_products = $this->model_catalog_product_bundle->getBundleProducts($bundle['bundle_id']);
				$products_for_template = array();

				foreach ($bundle_products as $product_id) {
					$product_info = $this->model_catalog_product->getProduct((int) $product_id);

					if ($product_info) {
						$products_for_template[] = array(
							'product_id' => $product_info['product_id'],
							'name'       => $product_info['name'],
							'price'      => $this->currency->format($product_info['price'], $this->config->get('config_currency'))
						);
					}
				}

				$data['product_bundles'][] = array(
					'bundle_id'       => $bundle['bundle_id'],
					'discount_type'   => $bundle['discount_type'],
					'discount_value'  => $bundle['discount_value'],
					'date_start'      => ($bundle['date_start'] != '0000-00-00') ? $bundle['date_start'] : '',
					'date_end'        => ($bundle['date_end'] != '0000-00-00') ? $bundle['date_end'] : '',
					'auto_renew'      => (int) $bundle['auto_renew'],
					'date_added'      => $bundle['date_added'] ?? '0000-00-00 00:00:00',
					'products'        => $products_for_template,
					'product_ids_csv' => implode(',', $bundle_products)
				);
			}
		}

		$data['all_promotions'] = array_merge(
			array_map(fn($v, $k) => ['promo_type' => 'discount', 'row_index' => $k] + $v,
				$data['product_discounts'], array_keys($data['product_discounts'])),
			array_map(fn($v, $k) => ['promo_type' => 'special', 'row_index' => $k] + $v,
				$data['product_specials'], array_keys($data['product_specials'])),
			array_map(fn($v, $k) => ['promo_type' => 'gift', 'row_index' => $k] + $v,
				$data['product_gifts'], array_keys($data['product_gifts'])),
			array_map(fn($v, $k) => ['promo_type' => 'bxgy', 'row_index' => $k] + $v,
				$data['product_bxgy_rules'], array_keys($data['product_bxgy_rules'])),
			array_map(fn($v, $k) => ['promo_type' => 'bundle', 'row_index' => $k] + $v,
				$data['product_bundles'], array_keys($data['product_bundles']))
		);

		$typeSort = ['discount' => 0, 'special' => 1, 'gift' => 2, 'bxgy' => 3, 'bundle' => 4];

		usort($data['all_promotions'], fn($a, $b) => (
			$cmp = strcmp($b['date_added'] ?? '0000-00-00 00:00:00', $a['date_added'] ?? '0000-00-00 00:00:00')
		) ? $cmp : (
			($a['row_index'] * 5 + ($typeSort[$a['promo_type']] ?? 0))
			<=>
			($b['row_index'] * 5 + ($typeSort[$b['promo_type']] ?? 0))
		));

		$data['discount_row'] = count($data['product_discounts']);
		$data['special_row']  = count($data['product_specials']);
		$data['gift_row']     = count($data['product_gifts']);
		$data['bxgy_row']     = count($data['product_bxgy_rules']);
		$data['bundle_row']   = count($data['product_bundles']);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/product_form', $data));
	}

	private function decodeDescriptionFields($descriptions, $fields = array()) {
		if (!is_array($descriptions)) {
			return array();
		}

		foreach ($descriptions as $language_id => $description) {
			if (!is_array($description)) {
				continue;
			}

			foreach ($fields as $field) {
				if (isset($description[$field])) {
					$descriptions[$language_id][$field] = $this->decodeHtmlEntitiesForDisplay($description[$field]);
				}
			}
		}

		return $descriptions;
	}

	private function decodeHtmlEntitiesForDisplay($value) {
		if (!is_scalar($value)) {
			return '';
		}

		$decoded = (string)$value;

		for ($i = 0; $i < 2; $i++) {
			$next = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');

			if ($next === $decoded) {
				break;
			}

			$decoded = $next;
		}

		return $decoded;
	}

	private function isValidDecimalValue($value) {
		$normalized = str_replace(',', '.', trim((string)$value));

		return preg_match('/^\d+(\.\d{1,2})?$/', $normalized) === 1;
	}

	private function normalizeDecimal($value, $default = 0.0) {
		$normalized = str_replace(',', '.', trim((string)$value));

		if (!is_numeric($normalized)) {
			return (float)$default;
		}

		return round((float)$normalized, 2);
	}

	private function formatQuantityForDisplay($value) {
		$formatted = number_format((float)$value, 2, '.', '');

		return rtrim(rtrim($formatted, '0'), '.');
	}

	private function isQuantityMultiple($quantity, $step) {
		$quantity_cents = (int)round((float)$quantity * 100);
		$step_cents = (int)round((float)$step * 100);

		if ($step_cents <= 0) {
			return false;
		}

		return ($quantity_cents % $step_cents) === 0;
	}

	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'catalog/product')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		foreach ($this->request->post['product_description'] as $language_id => $value) {
			if ((utf8_strlen($value['name']) < 1) || (utf8_strlen($value['name']) > 255)) {
				$this->error['name'][$language_id] = $this->language->get('error_name');
			}

			if ((utf8_strlen($value['meta_title']) < 1) || (utf8_strlen($value['meta_title']) > 255)) {
				$this->error['meta_title'][$language_id] = $this->language->get('error_meta_title');
			}
		}

		if ((utf8_strlen($this->request->post['model']) < 1) || (utf8_strlen($this->request->post['model']) > 64)) {
			$this->error['model'] = $this->language->get('error_model');
		}

		$quantity_step_raw = isset($this->request->post['quantity_step']) ? $this->request->post['quantity_step'] : '1';
		$minimum_raw = isset($this->request->post['minimum']) ? $this->request->post['minimum'] : '1';

		$quantity_step = $this->normalizeDecimal($quantity_step_raw, 1.0);
		$minimum = $this->normalizeDecimal($minimum_raw, 1.0);

		if (!$this->isValidDecimalValue($quantity_step_raw) || $quantity_step <= 0) {
			$this->error['quantity_step'] = $this->language->get('error_quantity_step');
		}

		if (!$this->isValidDecimalValue($minimum_raw) || $minimum <= 0) {
			$this->error['minimum'] = $this->language->get('error_minimum_value');
		}

		if (!isset($this->error['quantity_step']) && !isset($this->error['minimum']) && !$this->isQuantityMultiple($minimum, $quantity_step)) {
			$this->error['minimum_step'] = $this->language->get('error_minimum_step');
		}

		if ($this->request->post['product_seo_url']) {
			$this->load->model('design/seo_url');

			foreach ($this->request->post['product_seo_url'] as $store_id => $language) {
				foreach ($language as $language_id => $keyword) {
					if (!empty($keyword)) {
						$seo_urls = $this->model_design_seo_url->getSeoUrlsByKeyword($keyword, $language_id);

						foreach ($seo_urls as $seo_url) {
							if (($seo_url['store_id'] == $store_id) && (!isset($this->request->get['product_id']) || (($seo_url['query'] != 'product_id=' . $this->request->get['product_id'])))) {
								$this->error['keyword'][$store_id][$language_id] = $this->language->get('error_keyword');

								break;
							}
						}
					}
				}
			}
		}

		// A configurable product must have a default variant selected, otherwise
		// the storefront cannot determine which variant to show first.
		if (isset($this->request->get['product_id'])) {
			$configurable_query = $this->db->query("SELECT pc.default_variant_id, (SELECT COUNT(*) FROM " . DB_PREFIX . "product_variant pv WHERE pv.product_id = pc.product_id AND pv.status = '1') AS active_variants FROM " . DB_PREFIX . "product_configurable pc WHERE pc.product_id = '" . (int)$this->request->get['product_id'] . "' AND pc.is_configurable = '1'");

			if ($configurable_query->num_rows) {
				$configurable = $configurable_query->row;

				if ((int)$configurable['active_variants'] > 0) {
					$default_valid = !empty($configurable['default_variant_id']) && $this->db->query("SELECT variant_id FROM " . DB_PREFIX . "product_variant WHERE variant_id = '" . (int)$configurable['default_variant_id'] . "' AND product_id = '" . (int)$this->request->get['product_id'] . "' AND status = '1'")->num_rows;

					if (!$default_valid) {
						$this->error['default_variant'] = $this->language->get('error_default_variant');
					}
				}
			}
		}

		if ($this->error && !isset($this->error['warning'])) {
			$this->error['warning'] = $this->language->get('error_warning');
		}

		return !$this->error;
	}

	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'catalog/product')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	protected function validateCopy() {
		if (!$this->user->hasPermission('modify', 'catalog/product')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	public function updateImage() {
		$json = array();

		if (!$this->user->hasPermission('modify', 'catalog/product')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->post['product_id']) || !isset($this->request->post['image'])) {
			$json['error'] = 'Invalid request';
		}

		if (!isset($json['error'])) {
			$this->load->model('catalog/product');
			$this->model_catalog_product->updateProductImage((int)$this->request->post['product_id'], $this->request->post['image']);
			$json['success'] = 'Image updated';
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function updateField() {
		$json = array();

		if (!$this->user->hasPermission('modify', 'catalog/product')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->post['product_id']) || !isset($this->request->post['field']) || !isset($this->request->post['value'])) {
			$json['error'] = 'Invalid request';
		}

		if (!isset($json['error'])) {
			$product_id = (int)$this->request->post['product_id'];
			$field = $this->request->post['field'];
			$value = $this->request->post['value'];

			$this->load->model('catalog/product');

			if ($field === 'price') {
				$normalized = $this->normalizeDecimal($value);

				if (!is_numeric(str_replace(',', '.', trim((string)$value))) || $normalized < 0) {
					$json['error'] = $this->language->get('error_invalid_price');
				} else {
					$this->model_catalog_product->updateProductField($product_id, array('price' => $normalized));
					$json['success'] = true;

					$this->load->model('localisation/currency');
					$product_query = $this->db->query("SELECT currency_id FROM " . DB_PREFIX . "product WHERE product_id = '" . (int)$product_id . "'");

					if ($product_query->num_rows && $product_query->row['currency_id']) {
						$product_currency = $this->model_localisation_currency->getCurrency($product_query->row['currency_id']);

						if ($product_currency) {
							$badge = ($product_currency['code'] !== $this->config->get('config_currency'))
								? ' <span class="label label-info">' . $product_currency['code'] . '</span>'
								: '';
							$json['value_html'] = $this->currency->format($normalized, $product_currency['code'], 1.0) . $badge;
						} else {
							$json['value_html'] = $this->currency->format($normalized, $this->config->get('config_currency'));
						}
					} else {
						$json['value_html'] = $this->currency->format($normalized, $this->config->get('config_currency'));
					}
				}
			} elseif ($field === 'model') {
				$val = trim((string)$value);

				if (utf8_strlen($val) < 1 || utf8_strlen($val) > 64) {
					$json['error'] = $this->language->get('error_model');
				} else {
					$this->model_catalog_product->updateProductField($product_id, array('model' => $val));
					$json['success'] = true;
					$json['value_html'] = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
				}
			} elseif ($field === 'status') {
				$val = (int)$value;

				if ($val !== 0 && $val !== 1) {
					$json['error'] = 'Invalid status value';
				} else {
					$this->model_catalog_product->updateProductField($product_id, array('status' => $val));
					$json['success'] = true;

					$this->load->language('catalog/product');
					if ($val) {
						$json['value_html'] = '<span class="label label-success">' . $this->language->get('text_enabled') . '</span>';
					} else {
						$json['value_html'] = '<span class="label label-danger">' . $this->language->get('text_disabled') . '</span>';
					}
				}
			} elseif ($field === 'quantity') {
				$normalized = $this->normalizeDecimal($value);

				if (!is_numeric(str_replace(',', '.', trim((string)$value))) || $normalized < 0) {
					$json['error'] = $this->language->get('error_invalid_quantity');
				} else {
					$this->model_catalog_product->updateProductField($product_id, array('quantity' => $normalized));
					$json['success'] = true;
					$display = $this->formatQuantityForDisplay($normalized);

					if ($normalized <= 0) {
						$json['value_html'] = '<span style="color:#e74c3c;font-weight:600;">' . $display . '</span>';
					} elseif ($normalized <= 5) {
						$json['value_html'] = '<span style="color:#e67e22;font-weight:600;">' . $display . '</span>';
					} else {
						$json['value_html'] = '<span style="color:#27ae60;font-weight:600;">' . $display . '</span>';
					}
				}
			} elseif ($field === 'categories') {
				$raw = isset($this->request->post['value']) ? $this->request->post['value'] : '';
				$category_ids = $raw ? array_map('intval', explode(',', $raw)) : array();

				$this->load->model('catalog/product');
				$this->model_catalog_product->updateProductCategories($product_id, $category_ids);

				$json['success'] = true;

				$main_cat_id = 0;
				$main_q = $this->db->query("SELECT main_category_id FROM " . DB_PREFIX . "product WHERE product_id = '" . (int)$product_id . "'");
				if ($main_q->num_rows) {
					$main_cat_id = (int)$main_q->row['main_category_id'];
				}

				$cat_display_parts = array();
				if ($category_ids) {
					$cat_query = $this->db->query("SELECT cd.category_id, cd.name FROM " . DB_PREFIX . "category_description cd WHERE cd.category_id IN (" . implode(',', array_map('intval', $category_ids)) . ") AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "'");
					foreach ($cat_query->rows as $cat_row) {
						$name = htmlspecialchars($cat_row['name'], ENT_QUOTES, 'UTF-8');
						if ($main_cat_id && (int)$cat_row['category_id'] === $main_cat_id) {
							$cat_display_parts[] = '<strong>' . $name . '</strong>';
						} else {
							$cat_display_parts[] = $name;
						}
					}
				}

				$json['value_html'] = $cat_display_parts ? implode(', ', $cat_display_parts) : '';
			} else {
				$json['error'] = 'Invalid field';
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function getName() {
		$json = array();

		if (!$this->user->hasPermission('modify', 'catalog/product')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->get['product_id'])) {
			$json['error'] = 'Invalid request';
		}

		if (!isset($json['error'])) {
			$product_id = (int)$this->request->get['product_id'];

			$this->load->model('catalog/product');
			$this->load->model('localisation/language');

			$languages = $this->model_localisation_language->getLanguages();
			$descriptions = $this->model_catalog_product->getProductDescriptions($product_id);

			$names = array();

			foreach ($languages as $language) {
				$lid = $language['language_id'];
				$names[$lid] = isset($descriptions[$lid]) ? $descriptions[$lid]['name'] : '';
			}

			$json['success'] = true;
			$json['languages'] = array_values($languages);
			$json['names'] = $names;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function updateNames() {
		$json = array();

		if (!$this->user->hasPermission('modify', 'catalog/product')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->post['product_id']) || !isset($this->request->post['names'])) {
			$json['error'] = 'Invalid request';
		}

		if (!isset($json['error'])) {
			$product_id = (int)$this->request->post['product_id'];
			$names = $this->request->post['names'];

			$this->load->model('catalog/product');
			$this->load->model('localisation/language');

			$languages = $this->model_localisation_language->getLanguages();

			$error_names = array();

			foreach ($languages as $language) {
				$lid = $language['language_id'];

				if (isset($names[$lid])) {
					$name = trim((string)$names[$lid]);

					if (utf8_strlen($name) < 1 || utf8_strlen($name) > 255) {
						$error_names[$lid] = $this->language->get('error_name');
					}
				}
			}

			if (!empty($error_names)) {
				$json['error'] = $this->language->get('error_name');
				$json['error_names'] = $error_names;
			} else {
				$this->model_catalog_product->updateProductNames($product_id, $names);
				$json['success'] = true;
				$json['value_html'] = htmlspecialchars($names[$this->config->get('config_language_id')] ?? '', ENT_QUOTES, 'UTF-8');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function autocomplete() {
		$json = array();

		// Search-as-you-type for the product list toolbar (name / model / all SKUs)
		if (isset($this->request->get['filter_search'])) {
			$filter_search = trim((string)$this->request->get['filter_search']);

			if ($filter_search !== '') {
				$this->load->model('catalog/product');
				$this->load->model('common/admin_search');

				$results = $this->getHybridProductResults($filter_search, 8);

				if ($results === null) {
					// Fallback: Manticore unavailable → SQL LIKE path
					$filter_data = array(
						'filter_search' => $filter_search,
						'sort'          => 'pd.name',
						'order'         => 'ASC',
						'start'         => 0,
						'limit'         => 8
					);

					$results = $this->model_catalog_product->getProducts($filter_data);
				}

				foreach ($results as $result) {
					$json[] = array(
						'id'       => $result['product_id'],
						'name'     => $result['name'],
						'subtitle' => $result['model'],
						'href'     => $this->url->link('catalog/product/edit', 'user_token=' . $this->session->data['user_token'] . '&product_id=' . $result['product_id'], true)
					);
				}
			}
		} elseif (isset($this->request->get['filter_name']) || isset($this->request->get['filter_model']) || isset($this->request->get['filter_sku'])) {
			$this->load->model('catalog/product');
			$this->load->model('catalog/option');
			$this->load->model('tool/image');

			$filter_name = $this->request->get['filter_name'] ?? '';
			$filter_model = $this->request->get['filter_model'] ?? '';
			$filter_sku = $this->request->get['filter_sku'] ?? '';
			$filter_any = (bool)($this->request->get['filter_any'] ?? false);

			$limit = (int)($this->request->get['limit'] ?? 5);

			$currency_code = (string)($this->request->get['currency_code'] ?? '');
			$currency_value = (float)($this->request->get['currency_value'] ?? 1.0);
			$customer_group_id = (int)($this->request->get['customer_group_id'] ?? 0);

			$format_currency = $currency_code ? $currency_code : $this->config->get('config_currency');

			$filter_data = array(
				'filter_name'  => $filter_name,
				'filter_model' => $filter_model,
				'filter_sku'   => $filter_sku,
				'start'        => 0,
				'limit'        => $limit
			);

			$this->load->model('common/admin_search');

			// Manticore path: single MATCH over name/model/SKU/variant codes (OR semantics for filter_any,
			// per-field MATCH otherwise). Falls back to the SQL LIKE path when Manticore is unavailable.
			$manticore_search = '';

			if ($filter_any) {
				$manticore_search = trim(implode(' ', array_filter(array($filter_name, $filter_model, $filter_sku))));
			} elseif ($filter_name !== '') {
				$manticore_search = $filter_name;
			} elseif ($filter_model !== '') {
				$manticore_search = $filter_model;
			} elseif ($filter_sku !== '') {
				$manticore_search = $filter_sku;
			}

			$results = null;

			if ($manticore_search !== '') {
				$results = $this->getHybridProductResults($manticore_search, $limit);
			}

			if ($results === null) {
				// Fallback: Manticore unavailable → SQL LIKE path (original behaviour)
				if ($filter_any) {
					// OR-search across name/model/SKU: run each filter separately and merge
					$results = array();
					$seen = array();

					foreach (array('filter_name' => $filter_name, 'filter_model' => $filter_model, 'filter_sku' => $filter_sku) as $filter_key => $filter_value) {
						if ($filter_value === '') {
							continue;
						}

						$single_filter = array('start' => 0, 'limit' => $limit * 3);
						$single_filter[$filter_key] = $filter_value;

						$rows = $this->model_catalog_product->getProducts($single_filter);

						foreach ($rows as $row) {
							if (!isset($seen[(int)$row['product_id']])) {
								$seen[(int)$row['product_id']] = true;
								$results[] = $row;
							}
						}
					}

					$results = array_slice($results, 0, $limit);
				} else {
					$results = $this->model_catalog_product->getProducts($filter_data);
				}
			}

			if ($results) {
				$product_ids = array_map('intval', array_column($results, 'product_id'));

				$configurable_map = array();
				$config_query = $this->db->query("SELECT product_id, is_configurable FROM " . DB_PREFIX . "product_configurable WHERE product_id IN (" . implode(',', $product_ids) . ")");

				foreach ($config_query->rows as $row) {
					$configurable_map[(int)$row['product_id']] = (bool)$row['is_configurable'];
				}

				$manufacturer_map = array();
				$manufacturer_query = $this->db->query("SELECT manufacturer_id, name FROM " . DB_PREFIX . "manufacturer WHERE manufacturer_id IN (" . implode(',', array_filter(array_map('intval', array_column($results, 'manufacturer_id')))) . ")");

				foreach ($manufacturer_query->rows as $row) {
					$manufacturer_map[(int)$row['manufacturer_id']] = $row['name'];
				}

				$pc = new \ProductConfigurable($this->registry);

				foreach ($results as $result) {
					$product_id = (int)$result['product_id'];
					$is_configurable = !empty($configurable_map[$product_id]);

					// The search results are normalized for the admin's default
					// customer group; when the picker targets a specific order
					// customer group, re-apply the storefront catalog pricing for
					// that group so the displayed price matches what will be
					// charged (mirrors sale/order calculateProductPricing()).
					$display_price = (float)$result['price'];

					if (!$is_configurable && $customer_group_id > 0) {
						$this->load->model('sale/order');

						$raw_price_query = $this->db->query("SELECT price FROM `" . DB_PREFIX . "product` WHERE product_id = '" . (int)$product_id . "'");
						$raw_price = $raw_price_query->num_rows ? (float)$raw_price_query->row['price'] : $display_price;

						$catalog_pricing = $this->model_sale_order->applyCatalogPricingToPrice($product_id, 0, 1, $customer_group_id, $raw_price);

						if ($catalog_pricing['applied']) {
							$display_price = $catalog_pricing['price'];
						}
					}

					$price_text = $this->currency->format($display_price, $format_currency, $currency_value);

					$price_min = 0.0;
					$price_max = 0.0;
					$price_range_text = '';

					if ($is_configurable) {
						$range = $pc->getAggregatedPriceRange($product_id, $customer_group_id ? $customer_group_id : null);

						if ($range['min'] > 0 || $range['max'] > 0) {
							$price_min = $range['min'];
							$price_max = $range['max'];
							$price_range_text = $this->currency->format($price_min, $format_currency, $currency_value);

							if ($price_max > $price_min) {
								$price_range_text .= ' – ' . $this->currency->format($price_max, $format_currency, $currency_value);
							}
						}
					}

					$stock = array(
						'total'  => (float)$result['quantity'],
						'not_tracked' => !(bool)$result['subtract']
					);

					if ($is_configurable) {
						$aggregated = $pc->getAggregatedStock($product_id);
						$stock['total'] = $aggregated['total_stock'];
						$stock['variants_in_stock'] = $aggregated['variants_in_stock'];
						$stock['total_variants'] = $aggregated['total_variants'];
					}

					if ($result['image'] && is_file(DIR_IMAGE . $result['image'])) {
						$thumb = $this->model_tool_image->resize($result['image'], 40, 40);
					} else {
						$thumb = $this->model_tool_image->resize('no_image.png', 40, 40);
					}

					$option_data = array();

					$product_options = $this->model_catalog_product->getProductOptions($product_id);

					foreach ($product_options as $product_option) {
						$option_info = $this->model_catalog_option->getOption($product_option['option_id']);

						if ($option_info) {
							$product_option_value_data = array();

							foreach ($product_option['product_option_value'] as $product_option_value) {
								$option_value_info = $this->model_catalog_option->getOptionValue($product_option_value['option_value_id']);

								if ($option_value_info) {
									$option_price = (float)$product_option_value['price'];

									if ($option_price != 0) {
										$price_text_value = $this->currency->format($option_price, $format_currency, $currency_value);
									} else {
										$price_text_value = '';
									}

									$product_option_value_data[] = array(
										'product_option_value_id' => $product_option_value['product_option_value_id'],
										'option_value_id'         => $product_option_value['option_value_id'],
										'name'                    => $option_value_info['name'],
										'price'                   => (float)$product_option_value['price'] ? $this->currency->format($product_option_value['price'], $this->config->get('config_currency')) : false,
										'price_text'              => $price_text_value,
										'price_prefix'            => isset($product_option_value['price_prefix']) ? $product_option_value['price_prefix'] : '+'
									);
								}
							}

							$option_data[] = array(
								'product_option_id'    => $product_option['product_option_id'],
								'product_option_value' => $product_option_value_data,
								'option_id'            => $product_option['option_id'],
								'name'                 => $option_info['name'],
								'type'                 => $option_info['type'],
								'value'                => $product_option['value'],
								'required'             => $product_option['required']
							);
						}
					}

					$json[] = array(
						'product_id'       => $product_id,
						'name'             => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8')),
						'model'            => $result['model'],
						'sku'              => (string)($result['sku'] ?? ''),
						'manufacturer'     => $manufacturer_map[(int)$result['manufacturer_id']] ?? '',
						'quantity'         => $result['quantity'],
						'subtract'         => (bool)$result['subtract'],
						'status'           => (bool)$result['status'],
						'is_configurable'  => $is_configurable,
						'price'            => $result['price'],
						'price_text'       => $price_text,
						'price_min'        => $price_min,
						'price_max'        => $price_max,
						'price_range_text' => $price_range_text,
						'stock'            => $stock,
						'thumb'            => $thumb,
						'option'           => $option_data
					);
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Hybrid product search: Manticore finds & ranks, MySQL enriches.
	 *
	 * Returns full product rows (from the catalog product model) in Manticore
	 * relevance order, or null when Manticore is unavailable (caller falls
	 * back to the SQL LIKE path).
	 *
	 * @param string $search
	 * @param int    $limit
	 * @return array|null
	 */
	private function getHybridProductResults($search, $limit) {
		$search = trim((string)$search);

		if ($search === '') {
			return array();
		}

		$manticore = $this->model_common_admin_search->searchEntity('product', $search, array('limit' => $limit));

		if ($manticore === false) {
			return null;
		}

		$product_ids = array_column($manticore['results'], 'id');

		if (!$product_ids) {
			return array();
		}

		$rows = $this->model_catalog_product->getProducts(array(
			'filter_product_ids' => $product_ids,
			'start'              => 0,
			'limit'              => count($product_ids)
		));

		// Restore Manticore relevance order (getProducts sorts by name by default)
		$order = array_flip(array_map('intval', $product_ids));
		$ordered = array();

		foreach ($rows as $row) {
			$ordered[$order[(int)$row['product_id']]] = $row;
		}

		ksort($ordered);

		return array_values($ordered);
	}

	private function getPriceDisplayInfo($amount, $currency_id, array $currency_map): array {
		$currency_id = (int)$currency_id;

		if ($currency_id && isset($currency_map[$currency_id])) {
			$code = $currency_map[$currency_id];

			return [
				'formatted' => $this->currency->format($amount, $code, 1.0),
				'code'      => ($code !== $this->config->get('config_currency')) ? $code : '',
			];
		}

		return [
			'formatted' => $this->currency->format($amount, $this->config->get('config_currency')),
			'code'      => '',
		];
	}

	private function extractYouTubeId($value): string {
		$value = trim($value);
		if (preg_match('/^[A-Za-z0-9_-]{11}$/', $value)) {
			return $value;
		}
		$patterns = [
			'/(?:youtube\.com|youtu\.be|youtube-nocookie\.com)\/(?:watch\?v=|embed\/|shorts\/)?([A-Za-z0-9_-]{11})/',
			'/^[A-Za-z0-9_-]{11}$/'
		];
		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $value, $matches)) {
				return $matches[1];
			}
		}
		return $value;
	}

	public function getBundles() {
		$json = array();

		if (!$this->user->hasPermission('access', 'catalog/product')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->get['product_id'])) {
			$json['error'] = 'Invalid request';
		}

		if (!isset($json['error'])) {
			$product_id = (int) $this->request->get['product_id'];

			$this->load->model('catalog/product_bundle');

			$bundle_results = $this->model_catalog_product_bundle->getBundlesByProduct($product_id);

			$json['bundles'] = array();

			foreach ($bundle_results as $bundle) {
				$bundle_products = $this->model_catalog_product_bundle->getBundleProducts($bundle['bundle_id']);

				$other_products = array();

				foreach ($bundle_products as $bp) {
					if ((int) $bp['product_id'] !== $product_id) {
						$product_info = $this->model_catalog_product->getProduct($bp['product_id']);

						if ($product_info) {
							$other_products[] = array(
								'product_id' => $product_info['product_id'],
								'name'       => $product_info['name'],
								'model'      => $product_info['model'],
								'price'      => $this->currency->format($product_info['price'], $this->config->get('config_currency'))
							);
						}
					}
				}

				$discount_text = '';

				if ($bundle['discount_type'] == 'percentage') {
					$discount_text = '-' . $this->currency->format($bundle['discount_value'], $this->config->get('config_currency'), 1) . '%';
				} else {
					$discount_text = '-' . $this->currency->format($bundle['discount_value'], $this->session->data['currency']);
				}

				$json['bundles'][] = array(
					'bundle_id'       => $bundle['bundle_id'],
					'name'            => $bundle['name'] ?: '',
					'discount_type'   => $bundle['discount_type'],
					'discount_value'  => $bundle['discount_value'],
					'discount_text'   => $discount_text,
					'date_start'      => ($bundle['date_start'] != '0000-00-00') ? $bundle['date_start'] : '',
					'date_end'        => ($bundle['date_end'] != '0000-00-00') ? $bundle['date_end'] : '',
					'status'          => (int) $bundle['status'],
					'sort_order'      => (int) $bundle['sort_order'],
					'auto_renew'      => (int) $bundle['auto_renew'],
					'other_products'  => $other_products,
					'product_count'   => count($bundle_products)
				);
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function getBundleProducts() {
		$json = array();

		if (!$this->user->hasPermission('access', 'catalog/product')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->get['bundle_id'])) {
			$json['error'] = 'Invalid request';
		}

		if (!isset($json['error'])) {
			$bundle_id = (int) $this->request->get['bundle_id'];

			$this->load->model('catalog/product_bundle');
			$this->load->model('catalog/product');

			$bundle_info = $this->model_catalog_product_bundle->getBundle($bundle_id);

			if ($bundle_info) {
				$bundle_products = $this->model_catalog_product_bundle->getBundleProducts($bundle_id);

				$json['bundle'] = array(
					'bundle_id'      => $bundle_info['bundle_id'],
					'name'           => $bundle_info['name'],
					'discount_type'  => $bundle_info['discount_type'],
					'discount_value' => $bundle_info['discount_value'],
					'date_start'     => ($bundle_info['date_start'] != '0000-00-00') ? $bundle_info['date_start'] : '',
					'date_end'       => ($bundle_info['date_end'] != '0000-00-00') ? $bundle_info['date_end'] : '',
					'status'         => (int) $bundle_info['status'],
					'sort_order'     => (int) $bundle_info['sort_order'],
					'auto_renew'     => (int) $bundle_info['auto_renew'],
					'products'       => array()
				);

				$json['bundle']['stores'] = $this->model_catalog_product_bundle->getBundleStores($bundle_id);

				foreach ($bundle_products as $bp) {
					$product_info = $this->model_catalog_product->getProduct($bp['product_id']);

					if ($product_info) {
						$json['bundle']['products'][] = array(
							'product_id' => $product_info['product_id'],
							'name'       => $product_info['name'],
							'model'      => $product_info['model'],
							'price'      => $this->currency->format($product_info['price'], $this->config->get('config_currency'))
						);
					}
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function saveBundle() {
		$json = array();

		if (!$this->user->hasPermission('modify', 'catalog/product_bundle')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->post['product_id']) || !isset($this->request->post['bundle_products']) || !is_array($this->request->post['bundle_products']) || count($this->request->post['bundle_products']) < 1) {
			$json['error'] = $this->language->get('error_bundle_products');
		}

		if (!isset($this->request->post['discount_value']) || (float) $this->request->post['discount_value'] <= 0) {
			$json['error'] = $this->language->get('error_bundle_discount');
		}

		if (!isset($json['error'])) {
			$this->load->model('catalog/product_bundle');

			$current_product_id = (int) $this->request->post['product_id'];
			$bundle_id = isset($this->request->post['bundle_id']) ? (int) $this->request->post['bundle_id'] : 0;

			$all_products = array_merge(array($current_product_id), array_map('intval', $this->request->post['bundle_products']));
			$all_products = array_unique($all_products);

			$data = array(
				'name'            => isset($this->request->post['name']) ? trim($this->request->post['name']) : '',
				'discount_type'   => isset($this->request->post['discount_type']) ? $this->request->post['discount_type'] : 'percentage',
				'discount_value'  => (float) $this->request->post['discount_value'],
				'date_start'      => isset($this->request->post['date_start']) ? $this->request->post['date_start'] : '0000-00-00',
				'date_end'        => isset($this->request->post['date_end']) ? $this->request->post['date_end'] : '0000-00-00',
				'status'          => isset($this->request->post['status']) ? (int) $this->request->post['status'] : 1,
				'sort_order'      => isset($this->request->post['sort_order']) ? (int) $this->request->post['sort_order'] : 0,
				'auto_renew'      => !empty($this->request->post['auto_renew']),
				'bundle_product'  => $all_products,
				'bundle_store'    => isset($this->request->post['bundle_store']) ? array_map('intval', $this->request->post['bundle_store']) : array(0)
			);

			if (empty($data['date_start'])) {
				$data['date_start'] = '0000-00-00';
			}

			if (empty($data['date_end'])) {
				$data['date_end'] = '0000-00-00';
			}

			if ($bundle_id) {
				$this->model_catalog_product_bundle->editBundle($bundle_id, $data);
			} else {
				$bundle_id = $this->model_catalog_product_bundle->addBundle($data);
			}

			$json['success'] = true;
			$json['bundle_id'] = $bundle_id;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function removeProductFromBundle() {
		$json = array();

		if (!$this->user->hasPermission('modify', 'catalog/product_bundle')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->post['bundle_id']) || !isset($this->request->post['product_id'])) {
			$json['error'] = 'Invalid request';
		}

		if (!isset($json['error'])) {
			$bundle_id = (int) $this->request->post['bundle_id'];
			$product_id = (int) $this->request->post['product_id'];

			$this->load->model('catalog/product_bundle');

			$this->db->query("DELETE FROM " . DB_PREFIX . "product_bundle_product WHERE bundle_id = '" . (int) $bundle_id . "' AND product_id = '" . (int) $product_id . "'");

			$remaining = $this->model_catalog_product_bundle->getBundleProducts($bundle_id);

			if (count($remaining) < 2) {
				$this->model_catalog_product_bundle->deleteBundle($bundle_id);

				$json['bundle_deleted'] = true;
			}

			$json['success'] = true;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function deleteBundle() {
		$json = array();

		if (!$this->user->hasPermission('modify', 'catalog/product_bundle')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->post['bundle_id'])) {
			$json['error'] = 'Invalid request';
		}

		if (!isset($json['error'])) {
			$bundle_id = (int) $this->request->post['bundle_id'];

			$this->load->model('catalog/product_bundle');

			$this->model_catalog_product_bundle->deleteBundle($bundle_id);

			$json['success'] = true;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Convert saved filter conditions into product model filter_data.
	 */
	private function buildProductFilterData(array $conditions): array {
		$data = array();

		foreach ($conditions as $condition) {
			$field = (string)($condition['field'] ?? '');
			$value = $condition['value'] ?? '';

			switch ($field) {
				case 'name':
					$data['filter_name'] = (string)$value;
					break;
				case 'model':
					$data['filter_model'] = (string)$value;
					break;
				case 'sku':
					$data['filter_sku'] = (string)$value;
					break;
				case 'price':
					$data['filter_price'] = (string)$value;
					break;
				case 'quantity':
					$data['filter_quantity_min'] = (string)$value;
					break;
				case 'status':
					$data['filter_status'] = (string)$value;
					break;
				case 'manufacturer':
					$data['filter_manufacturer'] = (string)$value;
					break;
			}
		}

		return $data;
	}
}
