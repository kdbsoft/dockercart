<?php

declare(strict_types=1);

class ControllerExtensionStore extends Controller {
	public function index() {
		$this->load->language('extension/store');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->document->addStyle('view/stylesheet/dockercart_store.css');

		if (!is_file(DIR_SYSTEM . 'library/dockercart/extension_store.php')) {
			$data['error'] = 'Extension Store library not found.';
			$data['categories_tree'] = array();
			$data['offers'] = array();
			$data['selected_category'] = '';
			$data['breadcrumbs'] = array();
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
			);
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/store', 'user_token=' . $this->session->data['user_token'], true),
			);
			$data['user_token'] = $this->session->data['user_token'];
			$data['header'] = $this->load->controller('common/header');
			$data['column_left'] = $this->load->controller('common/column_left');
			$data['footer'] = $this->load->controller('common/footer');

			$this->response->setOutput($this->load->view('extension/store', $data));

			return;
		}

		require_once DIR_SYSTEM . 'library/dockercart/extension_store.php';
		$store = new DockercartExtensionStore($this->registry);

		$yml = null;

		try {
			$lang = $store->resolveLanguage();
			$yml = $store->getYml($lang);
		} catch (Exception $e) {
			$data['error'] = $this->language->get('error_fetch');
		}

		$categories_tree = array();

		if ($yml !== null) {
			$categories_tree = $store->buildCategoriesTree($yml);
		}

		$selected_category = $this->request->get['category_id'] ?? '';
		$selected_category = (string) $selected_category;
		$offer_category_ids = null;

		if ($yml !== null && $selected_category !== '') {
			$offer_category_ids = $store->getCategoryDescendants($yml, $selected_category);
		}

		$offers = array();

		if ($yml !== null) {
			$offers = $store->getOffers($yml, $offer_category_ids);
		}

		$data['categories_tree'] = $categories_tree;
		$data['offers'] = $offers;
		$data['selected_category'] = $selected_category;
		$data['user_token'] = $this->session->data['user_token'];
		$data['detail_url'] = $this->url->link('extension/store/detail', 'user_token=' . $this->session->data['user_token'], true);
		$data['refresh_url'] = $this->url->link('extension/store/refresh', 'user_token=' . $this->session->data['user_token'], true);
		$data['base_url'] = $this->url->link('extension/store', 'user_token=' . $this->session->data['user_token'], true);

		$data['total_offers'] = count($offers);

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'], true),
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/store', 'user_token=' . $this->session->data['user_token'], true),
		);

		if (isset($this->session->data['error'])) {
			$data['error_warning'] = $this->session->data['error'];
			unset($this->session->data['error']);
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/store', $data));
	}

	public function detail() {
		$this->load->language('extension/store');
		$this->response->addHeader('Content-Type: application/json');

		$json = array();

		if ($this->request->server['REQUEST_METHOD'] !== 'GET') {
			$json['error'] = 'Invalid request method';
			$this->response->setOutput(json_encode($json));

			return;
		}

		$offer_id = $this->request->get['offer_id'] ?? '';

		if (empty($offer_id)) {
			$json['error'] = 'Missing offer ID';
			$this->response->setOutput(json_encode($json));

			return;
		}

		if (!is_file(DIR_SYSTEM . 'library/dockercart/extension_store.php')) {
			$json['error'] = 'Library not found';
			$this->response->setOutput(json_encode($json));

			return;
		}

		try {
			require_once DIR_SYSTEM . 'library/dockercart/extension_store.php';
			$store = new DockercartExtensionStore($this->registry);
			$lang = $store->resolveLanguage();
			$yml = $store->getYml($lang);

			foreach ($yml->shop->offers->offer as $offer) {
				if ((string) $offer['id'] === $offer_id) {
					$pictures = array();

					foreach ($offer->picture as $pic) {
						$pictures[] = (string) $pic;
					}

					$params = array();
					$changelog = '';

					foreach ($offer->param as $param) {
						$code = (string) $param['code'];

						if ($code === 'changelog') {
							$changelog = (string) $param;
							continue;
						}

						$params[] = array(
							'name' => (string) $param['name'],
							'code' => $code,
							'value' => (string) $param,
						);
					}

					$name = (string) $offer->name;

					if (empty($name)) {
						$name = (string) $offer->model;
					}

					if (empty($name)) {
						$name = 'Extension #' . $offer_id;
					}

					$description = '';

					if (isset($offer->description)) {
						$description = (string) $offer->description;
					}

					$buy_url = '';

					if (!empty((string) $offer->url)) {
						$raw_url = (string) $offer->url;

						if (preg_match('#^https?://#', $raw_url)) {
							$buy_url = $raw_url;
						} else {
							$buy_url = DockercartExtensionStore::STORE_DOMAIN . '/' . ltrim($raw_url, '/');
						}
					}

					if (empty($buy_url)) {
						$buy_url = DockercartExtensionStore::STORE_DOMAIN;
					}

					$json['id'] = $offer_id;
					$json['name'] = $name;
					$json['description'] = $description;
					$json['price'] = (float) $offer->price;
					$json['currency'] = (string) $offer->currencyId;
					$json['pictures'] = $pictures;
					$json['params'] = $params;
					$json['changelog'] = $changelog;
					$json['buy_url'] = $buy_url;
					$json['available'] = ((string) $offer['available']) === 'true';

					break;
				}
			}

			if (!isset($json['id'])) {
				$json['error'] = 'Offer not found';
			}
		} catch (Exception $e) {
			$json['error'] = $e->getMessage();
		}

		$this->response->setOutput(json_encode($json));
	}

	public function refresh() {
		$this->load->language('extension/store');

		if (!$this->user->hasPermission('modify', 'extension/store')) {
			$this->session->data['error'] = $this->language->get('error_permission');
			$this->response->redirect($this->url->link('extension/store', 'user_token=' . $this->session->data['user_token'], true));

			return;
		}

		if (!is_file(DIR_SYSTEM . 'library/dockercart/extension_store.php')) {
			$this->response->redirect($this->url->link('extension/store', 'user_token=' . $this->session->data['user_token'], true));

			return;
		}

		require_once DIR_SYSTEM . 'library/dockercart/extension_store.php';
		$store = new DockercartExtensionStore($this->registry);
		$store->clearCache();

		$this->session->data['success'] = $this->language->get('text_cache_cleared');
		$this->response->redirect($this->url->link('extension/store', 'user_token=' . $this->session->data['user_token'], true));
	}
}
