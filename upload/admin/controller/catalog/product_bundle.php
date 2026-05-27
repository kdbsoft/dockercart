<?php
class ControllerCatalogProductBundle extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('catalog/product_bundle');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/product_bundle');

		$this->getList();
	}

	public function add() {
		$this->load->language('catalog/product_bundle');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/product_bundle');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_catalog_product_bundle->addBundle($this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

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

			$this->response->redirect($this->url->link('catalog/product_bundle', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function edit() {
		$this->load->language('catalog/product_bundle');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/product_bundle');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_catalog_product_bundle->editBundle($this->request->get['bundle_id'], $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

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

			$this->response->redirect($this->url->link('catalog/product_bundle', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function delete() {
		$this->load->language('catalog/product_bundle');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/product_bundle');

		if (isset($this->request->post['selected']) && $this->validateDelete()) {
			foreach ($this->request->post['selected'] as $bundle_id) {
				$this->model_catalog_product_bundle->deleteBundle($bundle_id);
			}

			$this->session->data['success'] = $this->language->get('text_success');

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

			$this->response->redirect($this->url->link('catalog/product_bundle', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	protected function getList() {
		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'b.sort_order';
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

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('catalog/product_bundle', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		$data['add'] = $this->url->link('catalog/product_bundle/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['delete'] = $this->url->link('catalog/product_bundle/delete', 'user_token=' . $this->session->data['user_token'] . $url, true);

		$data['bundles'] = array();

		$filter_data = array(
			'sort'  => $sort,
			'order' => $order,
			'start' => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' => $this->config->get('config_limit_admin')
		);

		$bundle_total = $this->model_catalog_product_bundle->getTotalBundles();

		$results = $this->model_catalog_product_bundle->getBundles($filter_data);

		foreach ($results as $result) {
			$discount_text = '';

			if ($result['discount_type'] == 'percentage') {
				$discount_text = '-' . $this->currency->format($result['discount_value'], $this->config->get('config_currency'), 1) . '%';
			} else {
				$discount_text = '-' . $this->currency->format($result['discount_value'], $this->session->data['currency']);
			}

			$data['bundles'][] = array(
				'bundle_id'    => $result['bundle_id'],
				'name'         => $result['name'] ? $result['name'] : $this->language->get('text_no_name'),
				'product_count' => $result['product_count'],
				'discount'     => $discount_text,
				'status'       => $result['status'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
				'date_start'   => ($result['date_start'] != '0000-00-00') ? date($this->language->get('date_format_short'), strtotime($result['date_start'])) : '',
				'date_end'     => ($result['date_end'] != '0000-00-00') ? date($this->language->get('date_format_short'), strtotime($result['date_end'])) : '',
				'sort_order'   => $result['sort_order'],
				'edit'         => $this->url->link('catalog/product_bundle/edit', 'user_token=' . $this->session->data['user_token'] . '&bundle_id=' . $result['bundle_id'] . $url, true)
			);
		}

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

		if ($order == 'ASC') {
			$url .= '&order=DESC';
		} else {
			$url .= '&order=ASC';
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['sort_name'] = $this->url->link('catalog/product_bundle', 'user_token=' . $this->session->data['user_token'] . '&sort=b.name' . $url, true);
		$data['sort_product_count'] = $this->url->link('catalog/product_bundle', 'user_token=' . $this->session->data['user_token'] . '&sort=product_count' . $url, true);
		$data['sort_discount'] = $this->url->link('catalog/product_bundle', 'user_token=' . $this->session->data['user_token'] . '&sort=b.discount_value' . $url, true);
		$data['sort_status'] = $this->url->link('catalog/product_bundle', 'user_token=' . $this->session->data['user_token'] . '&sort=b.status' . $url, true);
		$data['sort_date_start'] = $this->url->link('catalog/product_bundle', 'user_token=' . $this->session->data['user_token'] . '&sort=b.date_start' . $url, true);
		$data['sort_date_end'] = $this->url->link('catalog/product_bundle', 'user_token=' . $this->session->data['user_token'] . '&sort=b.date_end' . $url, true);
		$data['sort_sort_order'] = $this->url->link('catalog/product_bundle', 'user_token=' . $this->session->data['user_token'] . '&sort=b.sort_order' . $url, true);

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		$pagination = new Pagination();
		$pagination->total = $bundle_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('catalog/product_bundle', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();
		$data['results'] = sprintf($this->language->get('text_pagination'), ($bundle_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($bundle_total - $this->config->get('config_limit_admin'))) ? $bundle_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $bundle_total, ceil($bundle_total / $this->config->get('config_limit_admin')));

		$data['sort'] = $sort;
		$data['order'] = $order;

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/product_bundle_list', $data));
	}

	protected function getForm() {
		$data['text_form'] = !isset($this->request->get['bundle_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['name'])) {
			$data['error_name'] = $this->error['name'];
		} else {
			$data['error_name'] = '';
		}

		if (isset($this->error['products'])) {
			$data['error_products'] = $this->error['products'];
		} else {
			$data['error_products'] = '';
		}

		if (isset($this->error['discount_value'])) {
			$data['error_discount_value'] = $this->error['discount_value'];
		} else {
			$data['error_discount_value'] = '';
		}

		if (isset($this->error['date_end'])) {
			$data['error_date_end'] = $this->error['date_end'];
		} else {
			$data['error_date_end'] = '';
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

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('catalog/product_bundle', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		if (!isset($this->request->get['bundle_id'])) {
			$data['action'] = $this->url->link('catalog/product_bundle/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		} else {
			$data['action'] = $this->url->link('catalog/product_bundle/edit', 'user_token=' . $this->session->data['user_token'] . '&bundle_id=' . $this->request->get['bundle_id'] . $url, true);
		}

		$data['cancel'] = $this->url->link('catalog/product_bundle', 'user_token=' . $this->session->data['user_token'] . $url, true);

		if (isset($this->request->get['bundle_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$bundle_info = $this->model_catalog_product_bundle->getBundle($this->request->get['bundle_id']);
		}

		$data['user_token'] = $this->session->data['user_token'];

		if (!empty($bundle_info['name'])) {
			$data['name'] = $bundle_info['name'];
		} else {
			$data['name'] = '';
		}

		if (!empty($bundle_info['discount_type'])) {
			$data['discount_type'] = $bundle_info['discount_type'];
		} else {
			$data['discount_type'] = 'percentage';
		}

		if (!empty($bundle_info['discount_value'])) {
			$data['discount_value'] = $bundle_info['discount_value'];
		} else {
			$data['discount_value'] = '';
		}

		if (!empty($bundle_info['date_start'])) {
			$data['date_start'] = $bundle_info['date_start'];
		} else {
			$data['date_start'] = '';
		}

		if (!empty($bundle_info['date_end'])) {
			$data['date_end'] = $bundle_info['date_end'];
		} else {
			$data['date_end'] = '';
		}

		if (isset($bundle_info['status'])) {
			$data['status'] = $bundle_info['status'];
		} else {
			$data['status'] = 1;
		}

		if (isset($bundle_info['sort_order'])) {
			$data['sort_order'] = $bundle_info['sort_order'];
		} else {
			$data['sort_order'] = 0;
		}

		if (isset($this->request->post['auto_renew'])) {
			$data['auto_renew'] = $this->request->post['auto_renew'];
		} elseif (!empty($bundle_info)) {
			$data['auto_renew'] = $bundle_info['auto_renew'];
		} else {
			$data['auto_renew'] = 0;
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

		if (isset($this->request->post['bundle_store'])) {
			$data['bundle_store'] = $this->request->post['bundle_store'];
		} elseif (isset($this->request->get['bundle_id'])) {
			$data['bundle_store'] = $this->model_catalog_product_bundle->getBundleStores($this->request->get['bundle_id']);
		} else {
			$data['bundle_store'] = array(0);
		}

		if (isset($this->request->post['bundle_product'])) {
			$bundle_products = $this->request->post['bundle_product'];
		} elseif (isset($this->request->get['bundle_id'])) {
			$bundle_products = $this->model_catalog_product_bundle->getBundleProducts($this->request->get['bundle_id']);
		} else {
			$bundle_products = array();
		}

		$this->load->model('catalog/product');

		$data['bundle_products'] = array();

		foreach ($bundle_products as $product_id) {
			$product_info = $this->model_catalog_product->getProduct($product_id);

			if ($product_info) {
				$data['bundle_products'][] = array(
					'product_id' => $product_info['product_id'],
					'name'       => $product_info['name'],
					'model'      => $product_info['model'],
					'price'      => $this->currency->format($product_info['price'], $this->config->get('config_currency'))
				);
			}
		}

		if (isset($this->request->post['name'])) {
			$data['name'] = $this->request->post['name'];
		}

		if (isset($this->request->post['discount_type'])) {
			$data['discount_type'] = $this->request->post['discount_type'];
		}

		if (isset($this->request->post['discount_value'])) {
			$data['discount_value'] = $this->request->post['discount_value'];
		}

		if (isset($this->request->post['date_start'])) {
			$data['date_start'] = $this->request->post['date_start'];
		}

		if (isset($this->request->post['date_end'])) {
			$data['date_end'] = $this->request->post['date_end'];
		}

		if (isset($this->request->post['status'])) {
			$data['status'] = $this->request->post['status'];
		}

		if (isset($this->request->post['sort_order'])) {
			$data['sort_order'] = $this->request->post['sort_order'];
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/product_bundle_form', $data));
	}

	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'catalog/product_bundle')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (isset($this->request->post['name']) && (utf8_strlen($this->request->post['name']) > 255)) {
			$this->error['name'] = $this->language->get('error_name');
		}

		if (!isset($this->request->post['bundle_product']) || count($this->request->post['bundle_product']) < 2) {
			$this->error['products'] = $this->language->get('error_products');
		}

		if ((float)$this->request->post['discount_value'] <= 0) {
			$this->error['discount_value'] = $this->language->get('error_discount_value');
		}

		if ($this->request->post['date_start'] != '0000-00-00' && $this->request->post['date_end'] != '0000-00-00' && $this->request->post['date_start'] > $this->request->post['date_end']) {
			$this->error['date_end'] = $this->language->get('error_date');
		}

		return !$this->error;
	}

	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'catalog/product_bundle')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	public function autocomplete() {
		$json = array();

		if (isset($this->request->get['filter_name']) || isset($this->request->get['filter_model'])) {
			$this->load->model('catalog/product');

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

			if (isset($this->request->get['limit'])) {
				$limit = (int)$this->request->get['limit'];
			} else {
				$limit = 5;
			}

			$filter_data = array(
				'filter_name'  => $filter_name,
				'filter_model' => $filter_model,
				'start'        => 0,
				'limit'        => $limit
			);

			$results = $this->model_catalog_product->getProducts($filter_data);

			foreach ($results as $result) {
				$json[] = array(
					'product_id' => $result['product_id'],
					'name'       => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8')),
					'model'      => $result['model'],
					'price'      => $result['price']
				);
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
