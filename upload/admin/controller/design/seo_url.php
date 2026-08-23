<?php
class ControllerDesignSeoUrl extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('design/seo_url');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('design/seo_url');

		$this->getList();
	}

	public function add() {
		$this->load->language('design/seo_url');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('design/seo_url');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$query = $this->request->post['query'];
			$store_id = isset($this->request->post['store_id']) ? (int)$this->request->post['store_id'] : 0;
			$this->model_design_seo_url->deleteSeoUrlGroup($query, $store_id);

			if (isset($this->request->post['seo_url'][$store_id])) {
				foreach ($this->request->post['seo_url'][$store_id] as $language_id => $keyword) {
					if ($keyword !== '') {
						$this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = '" . $this->db->escape($query) . "', keyword = '" . $this->db->escape($keyword) . "'");
					}
				}
				$this->model_design_seo_url->invalidateSeoUrlCache();
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['filter_query'])) {
				$url .= '&filter_query=' . urlencode(html_entity_decode($this->request->get['filter_query'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_keyword'])) {
				$url .= '&filter_keyword=' . urlencode(html_entity_decode($this->request->get['filter_keyword'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_store_id'])) {
				$url .= '&filter_store_id=' . $this->request->get['filter_store_id'];
			}

			if (isset($this->request->get['filter_language_id'])) {
				$url .= '&filter_language_id=' . $this->request->get['filter_language_id'];
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

			$this->response->redirect($this->url->link('design/seo_url', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function edit() {
		$this->load->language('design/seo_url');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('design/seo_url');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$query = $this->request->post['query'];
			$store_id = isset($this->request->post['store_id']) ? (int)$this->request->post['store_id'] : 0;
			$this->model_design_seo_url->deleteSeoUrlGroup($query, $store_id);

			if (isset($this->request->post['seo_url'][$store_id])) {
				foreach ($this->request->post['seo_url'][$store_id] as $language_id => $keyword) {
					if ($keyword !== '') {
						$this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = '" . $this->db->escape($query) . "', keyword = '" . $this->db->escape($keyword) . "'");
					}
				}
				$this->model_design_seo_url->invalidateSeoUrlCache();
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['filter_query'])) {
				$url .= '&filter_query=' . urlencode(html_entity_decode($this->request->get['filter_query'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_keyword'])) {
				$url .= '&filter_keyword=' . urlencode(html_entity_decode($this->request->get['filter_keyword'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_store_id'])) {
				$url .= '&filter_store_id=' . $this->request->get['filter_store_id'];
			}

			if (isset($this->request->get['filter_language_id'])) {
				$url .= '&filter_language_id=' . $this->request->get['filter_language_id'];
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

			$this->response->redirect($this->url->link('design/seo_url', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function delete() {
		$this->load->language('design/seo_url');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('design/seo_url');

		if (isset($this->request->post['selected']) && $this->validateDelete()) {
			foreach ($this->request->post['selected'] as $key) {
				$parts = explode('|', $key, 2);
				$query = $parts[0];
				$store_id = isset($parts[1]) ? (int)$parts[1] : 0;

				$this->model_design_seo_url->deleteSeoUrlGroup($query, $store_id);
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['filter_query'])) {
				$url .= '&filter_query=' . urlencode(html_entity_decode($this->request->get['filter_query'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_keyword'])) {
				$url .= '&filter_keyword=' . urlencode(html_entity_decode($this->request->get['filter_keyword'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_store_id'])) {
				$url .= '&filter_store_id=' . $this->request->get['filter_store_id'];
			}

			if (isset($this->request->get['filter_language_id'])) {
				$url .= '&filter_language_id=' . $this->request->get['filter_language_id'];
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

			$this->response->redirect($this->url->link('design/seo_url', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	protected function getList() {
		if (isset($this->request->get['filter_query'])) {
			$filter_query = $this->request->get['filter_query'];
		} else {
			$filter_query = '';
		}

		if (isset($this->request->get['filter_keyword'])) {
			$filter_keyword = $this->request->get['filter_keyword'];
		} else {
			$filter_keyword = '';
		}

		if (isset($this->request->get['filter_store_id'])) {
			$filter_store_id = $this->request->get['filter_store_id'];
		} else {
			$filter_store_id = '';
		}

		if (isset($this->request->get['filter_language_id'])) {
			$filter_language_id = $this->request->get['filter_language_id'];
		} else {
			$filter_language_id = '';
		}

		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'keyword';
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

		if (isset($this->request->get['filter_query'])) {
			$url .= '&filter_query=' . urlencode(html_entity_decode($this->request->get['filter_query'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_keyword'])) {
			$url .= '&filter_keyword=' . urlencode(html_entity_decode($this->request->get['filter_keyword'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_store_id'])) {
			$url .= '&filter_store_id=' . $this->request->get['filter_store_id'];
		}

		if (isset($this->request->get['filter_language_id'])) {
			$url .= '&filter_language_id=' . $this->request->get['filter_language_id'];
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

		$data['add'] = $this->url->link('design/seo_url/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['delete'] = $this->url->link('design/seo_url/delete', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['export'] = $this->url->link('design/seo_url/export', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['import'] = $this->url->link('design/seo_url/import', 'user_token=' . $this->session->data['user_token'], true);
		$data['text_list_subtitle'] = $this->language->get('text_list_subtitle');

		// Search-only toolbar (no saved filter tabs)
		$data['user_filter'] = $this->renderUserFilter('seo_url', 'design/seo_url', array(), array(), '', array(), array(
			'placeholder' => $this->language->get('text_search_seo_url'),
			'url'         => $this->url->link('design/seo_url/autocomplete', 'user_token=' . $this->session->data['user_token'], true)
		), false);

		$data['seo_urls'] = array();

		$filter_data = array(
			'filter_query'	     => $filter_query,
			'filter_keyword'	 => $filter_keyword,
			'filter_store_id'	 => $filter_store_id,
			'filter_language_id' => $filter_language_id,
			'sort'               => $sort,
			'order'              => $order,
			'start'              => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit'              => $this->config->get('config_limit_admin')
		);

		$seo_url_total = $this->model_design_seo_url->getTotalSeoUrlGroups($filter_data);

		$results = $this->model_design_seo_url->getSeoUrlGroups($filter_data);

		foreach ($results as $result) {
			// Pick a concrete row of this group to point the edit form at; the
			// edit form loads all language variants for the query anyway.
			$primary_id = 0;
			$store = '';

			if ($result['store_id']) {
				$store = $result['store'];
			} else {
				$store = $this->language->get('text_default');
			}

			foreach ($result['keywords'] as $language_keyword) {
				$primary_id = $language_keyword['seo_url_id'];
				break;
			}

			$data['seo_urls'][] = array(
				'query'    => $result['query'],
				'store_id' => $result['store_id'],
				'store'    => $store,
				'keywords' => $result['keywords'],
				'edit'     => $primary_id ? $this->url->link('design/seo_url/edit', 'user_token=' . $this->session->data['user_token'] . '&seo_url_id=' . $primary_id . $url, true) : ''
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

		if (isset($this->request->get['filter_query'])) {
			$url .= '&filter_query=' . urlencode(html_entity_decode($this->request->get['filter_query'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_keyword'])) {
			$url .= '&filter_keyword=' . urlencode(html_entity_decode($this->request->get['filter_keyword'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_store_id'])) {
			$url .= '&filter_store_id=' . $this->request->get['filter_store_id'];
		}

		if (isset($this->request->get['filter_language_id'])) {
			$url .= '&filter_language_id=' . $this->request->get['filter_language_id'];
		}

		if ($order == 'ASC') {
			$url .= '&order=DESC';
		} else {
			$url .= '&order=ASC';
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['sort_query'] = $this->url->link('design/seo_url', 'user_token=' . $this->session->data['user_token'] . '&sort=query' . $url, true);
		$data['sort_store'] = $this->url->link('design/seo_url', 'user_token=' . $this->session->data['user_token'] . '&sort=store_id' . $url, true);

		$url = '';

		if (isset($this->request->get['filter_query'])) {
			$url .= '&filter_query=' . urlencode(html_entity_decode($this->request->get['filter_query'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_keyword'])) {
			$url .= '&filter_keyword=' . urlencode(html_entity_decode($this->request->get['filter_keyword'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_store_id'])) {
			$url .= '&filter_store_id=' . $this->request->get['filter_store_id'];
		}

		if (isset($this->request->get['filter_language_id'])) {
			$url .= '&filter_language_id=' . $this->request->get['filter_language_id'];
		}

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		$pagination = new Pagination();
		$pagination->total = $seo_url_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('design/seo_url', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = $pagination->renderResults($this->language->get('text_pagination'));

		$data['filter_query'] = $filter_query;
		$data['filter_keyword'] = $filter_keyword;
		$data['filter_store_id'] = $filter_store_id;
		$data['filter_language_id'] = $filter_language_id;

		$data['sort'] = $sort;
		$data['order'] = $order;

		$this->load->model('setting/store');

		$data['stores'] = $this->model_setting_store->getStores();

		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('design/seo_url_list', $data));
	}

	protected function getForm() {
		$data['text_form'] = !isset($this->request->get['seo_url_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');
		$data['text_form_subtitle'] = !isset($this->request->get['seo_url_id'])
		    ? $this->language->get('text_add_seo_url_subtitle')
		    : $this->language->get('text_edit_seo_url_subtitle');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['query'])) {
			$data['error_query'] = $this->error['query'];
		} else {
			$data['error_query'] = '';
		}

		if (isset($this->error['keyword'])) {
			$data['error_keyword'] = $this->error['keyword'];
		} else {
			$data['error_keyword'] = array();
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

		if (!isset($this->request->get['seo_url_id'])) {
			$data['action'] = $this->url->link('design/seo_url/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		} else {
			$data['action'] = $this->url->link('design/seo_url/edit', 'user_token=' . $this->session->data['user_token'] . '&seo_url_id=' . $this->request->get['seo_url_id'] . $url, true);
		}

		$data['cancel'] = $this->url->link('design/seo_url', 'user_token=' . $this->session->data['user_token'] . $url, true);

		if (isset($this->request->get['seo_url_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$seo_url_info = $this->model_design_seo_url->getSeoUrl($this->request->get['seo_url_id']);
		}

		if (isset($this->request->post['query'])) {
			$data['query'] = $this->request->post['query'];
		} elseif (!empty($seo_url_info)) {
			$data['query'] = $seo_url_info['query'];
		} else {
			$data['query'] = '';
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

		// A form edits exactly one (query, store_id) alias group — match the
		// list view, where each row is one store's variant of a query.
		if (isset($this->request->post['store_id'])) {
			$data['store_id'] = (int)$this->request->post['store_id'];
		} elseif (!empty($seo_url_info)) {
			$data['store_id'] = (int)$seo_url_info['store_id'];
		} else {
			$data['store_id'] = 0;
		}

		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();

		if (isset($this->request->post['seo_url'])) {
			$data['seo_url'] = $this->request->post['seo_url'];
		} elseif (!empty($seo_url_info)) {
			$data['seo_url'] = $this->model_design_seo_url->getSeoUrlsArray($seo_url_info['query']);
		} else {
			$data['seo_url'] = array();
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('design/seo_url_form', $data));
	}

	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'design/seo_url')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->post['query']) || !$this->request->post['query']) {
			$this->error['query'] = $this->language->get('error_query');
		}

		if (isset($this->request->post['seo_url'])) {
			$store_id = isset($this->request->post['store_id']) ? (int)$this->request->post['store_id'] : 0;

			if (isset($this->request->post['seo_url'][$store_id])) {
				foreach ($this->request->post['seo_url'][$store_id] as $language_id => $keyword) {
					if ($keyword !== '') {
						$existing = $this->model_design_seo_url->getSeoUrlsByKeyword($keyword, $language_id);
						foreach ($existing as $seo_url) {
							if ($seo_url['store_id'] == $store_id && $seo_url['query'] != $this->request->post['query']) {
								$this->error['keyword'][$store_id][$language_id] = $this->language->get('error_exists');
								break;
							}
						}
					}
				}
			}
		}

		return !$this->error;
	}

	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'design/seo_url')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	public function autocomplete(): void {
		$json = array();

		if (isset($this->request->get['filter_search'])) {
			$filter_search = trim((string)$this->request->get['filter_search']);

			if ($filter_search !== '') {
				$this->load->model('design/seo_url');

				$filter_data = array(
					'filter_keyword' => '%' . $filter_search . '%',
					'sort'  => 'keyword',
					'order' => 'ASC',
					'start' => 0,
					'limit' => 8
				);

				$results = $this->model_design_seo_url->getSeoUrls($filter_data);

				foreach ($results as $result) {
					$json[] = array(
						'id'       => $result['seo_url_id'],
						'name'     => $result['keyword'],
						'subtitle' => $result['query'] . ($result['store_id'] ? ' · ' . $result['store'] : ''),
						'href'     => $this->url->link('design/seo_url/edit', 'user_token=' . $this->session->data['user_token'] . '&seo_url_id=' . $result['seo_url_id'], true)
					);
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Inline keyword editing: update (or remove) a single language alias of a
	 * (query, store_id) group. Expects a POST with query, store_id, language_id
	 * and value (empty value removes the record for that language).
	 */
	public function updateKeyword() {
		$this->load->language('design/seo_url');

		$json = array();

		if (!$this->user->hasPermission('modify', 'design/seo_url')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->post['query']) || !isset($this->request->post['store_id']) || !isset($this->request->post['language_id']) || !isset($this->request->post['value'])) {
			$json['error'] = 'Invalid request';
		}

		if (!isset($json['error'])) {
			$query = trim((string)$this->request->post['query']);
			$store_id = (int)$this->request->post['store_id'];
			$language_id = (int)$this->request->post['language_id'];
			$keyword = trim((string)$this->request->post['value']);

			$this->load->model('design/seo_url');

			if ($query === '') {
				$json['error'] = $this->language->get('error_query');
			} elseif ($keyword === '') {
				// Empty keyword removes this language variant entirely.
				$this->model_design_seo_url->deleteSeoUrlRecord($query, $store_id, $language_id);
				$json['success'] = true;
				$json['value'] = '';
			} else {
				if (utf8_strlen($keyword) < 3 || utf8_strlen($keyword) > 64) {
					$json['error'] = $this->language->get('error_keyword');
				} else {
					$existing = $this->model_design_seo_url->getSeoUrlsByKeyword($keyword, $language_id);

					foreach ($existing as $seo_url) {
						if ($seo_url['store_id'] == $store_id && $seo_url['query'] != $query) {
							$json['error'] = $this->language->get('error_exists');
							break;
						}
					}
				}

				if (!isset($json['error'])) {
					$seo_url_id = $this->model_design_seo_url->getSeoUrlIdByGroup($query, $store_id, $language_id);

					if ($seo_url_id) {
						$this->model_design_seo_url->editSeoUrl($seo_url_id, array(
							'store_id'    => $store_id,
							'language_id' => $language_id,
							'query'       => $query,
							'keyword'     => $keyword
						));
					} else {
						$this->model_design_seo_url->addSeoUrl(array(
							'store_id'    => $store_id,
							'language_id' => $language_id,
							'query'       => $query,
							'keyword'     => $keyword
						));
					}

					$json['success'] = true;
					$json['value'] = $keyword;
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Export SEO URLs as CSV (UTF-8 with BOM for Excel compatibility).
	 * Respects the current list filters. Columns: store_id, language, query, keyword.
	 */
	public function export(): void {
		$this->load->language('design/seo_url');

		if (!$this->user->hasPermission('access', 'design/seo_url')) {
			$this->session->data['error_warning'] = $this->language->get('error_permission');

			$this->response->redirect($this->url->link('design/seo_url', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->load->model('design/seo_url');
		$this->load->model('localisation/language');

		$language_codes = array();

		foreach ($this->model_localisation_language->getLanguages() as $language) {
			$language_codes[(int)$language['language_id']] = $language['code'];
		}

		$filter_data = array(
			'filter_query'       => isset($this->request->get['filter_query']) ? $this->request->get['filter_query'] : '',
			'filter_keyword'     => isset($this->request->get['filter_keyword']) ? $this->request->get['filter_keyword'] : '',
			'filter_store_id'    => isset($this->request->get['filter_store_id']) ? $this->request->get['filter_store_id'] : '',
			'filter_language_id' => isset($this->request->get['filter_language_id']) ? $this->request->get['filter_language_id'] : '',
			'sort'               => 'query',
			'order'              => 'ASC'
		);

		$csv = "\xEF\xBB\xBF" . 'store_id,language,query,keyword' . "\n";

		foreach ($this->model_design_seo_url->getSeoUrls($filter_data) as $result) {
			if (isset($language_codes[(int)$result['language_id']])) {
				$language = $language_codes[(int)$result['language_id']];
			} else {
				$language = $result['language_id'];
			}

			$csv .= $this->csvField($result['store_id']) . ',' . $this->csvField($language) . ',' . $this->csvField($result['query']) . ',' . $this->csvField($result['keyword']) . "\n";
		}

		$this->response->addHeader('Content-Type: text/csv; charset=utf-8');
		$this->response->addHeader('Content-Disposition: attachment; filename="seo_urls_' . date('Ymd_Hi') . '.csv"');
		$this->response->addHeader('Content-Length: ' . strlen($csv));
		$this->response->setOutput($csv);
	}

	/**
	 * Import SEO URLs from an uploaded CSV file (all-or-nothing).
	 *
	 * Expected columns: store_id, language (code), query, keyword. The whole
	 * file is validated first; on any error nothing is written and every
	 * offending line is reported. Existing (query, store_id, language_id)
	 * records are updated, missing ones are added.
	 */
	public function import(): void {
		$this->load->language('design/seo_url');
		$this->load->model('design/seo_url');

		$json = array();

		if ($this->request->server['REQUEST_METHOD'] != 'POST') {
			$json['error'] = 'Invalid request';
		} elseif (!$this->user->hasPermission('modify', 'design/seo_url')) {
			$json['error'] = $this->language->get('error_permission');
		} elseif (!isset($_FILES['file']) || (int)$_FILES['file']['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['file']['tmp_name'])) {
			$json['error'] = $this->language->get('error_upload');
		} else {
			$json = $this->processImportCsv($_FILES['file']['tmp_name']);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function processImportCsv($filename) {
		$handle = fopen($filename, 'r');

		if (!$handle) {
			return array('error' => $this->language->get('error_upload'));
		}

		// Reference data used for validation.
		$languages = array();

		foreach ($this->model_localisation_language->getLanguages() as $language) {
			$languages[mb_strtolower($language['code'], 'UTF-8')] = (int)$language['language_id'];
		}

		$stores = array(0);

		$this->load->model('setting/store');

		foreach ($this->model_setting_store->getStores() as $store) {
			$stores[] = (int)$store['store_id'];
		}

		// Existing rows indexed for uniqueness checks. Keys are lowercased to
		// mirror the case-insensitive DB collation.
		$existing_groups = array();
		$existing_keywords = array();

		$seo_urls = $this->db->query("SELECT `query`, `store_id`, `language_id`, `keyword` FROM `" . DB_PREFIX . "seo_url`");

		foreach ($seo_urls->rows as $row) {
			$existing_groups[$this->groupKey($row['query'], (int)$row['store_id'], (int)$row['language_id'])] = $row['keyword'];
			$existing_keywords[(int)$row['store_id'] . '|' . (int)$row['language_id'] . '|' . mb_strtolower($row['keyword'], 'UTF-8')] = $row['query'];
		}

		$rows = array();
		$errors = array();
		$file_groups = array();
		$file_keywords = array();
		$first_row = true;
		$line_no = 0;

		while (($data = fgetcsv($handle, 0, ',')) !== false) {
			$line_no++;

			$data = array_map('trim', (array)$data);

			// Skip blank lines.
			if (count($data) === 1 && $data[0] === '') {
				continue;
			}

			if ($first_row) {
				$data[0] = ltrim((string)$data[0], "\xEF\xBB\xBF");

				// Optional header row.
				if (count($data) >= 4 && array_map('strtolower', array_slice($data, 0, 4)) === array('store_id', 'language', 'query', 'keyword')) {
					$first_row = false;

					continue;
				}
			}

			$first_row = false;

			// Tolerate trailing separators.
			while (count($data) > 4 && $data[count($data) - 1] === '') {
				array_pop($data);
			}

			if (count($data) !== 4) {
				$errors[] = sprintf($this->language->get('error_csv_columns'), $line_no, count($data));

				continue;
			}

			list($store_field, $language_field, $query, $keyword) = $data;

			$store_id = 0;
			$language_id = 0;
			$valid = true;

			if (preg_match('/^\d+$/', (string)$store_field) && in_array((int)$store_field, $stores, true)) {
				$store_id = (int)$store_field;
			} else {
				$errors[] = sprintf($this->language->get('error_csv_store'), $line_no, (string)$store_field);

				$valid = false;
			}

			$language_key = mb_strtolower((string)$language_field, 'UTF-8');

			if (isset($languages[$language_key])) {
				$language_id = $languages[$language_key];
			} else {
				$errors[] = sprintf($this->language->get('error_csv_language'), $line_no, (string)$language_field);

				$valid = false;
			}

			if ((string)$query === '') {
				$errors[] = sprintf($this->language->get('error_csv_query'), $line_no);

				$valid = false;
			} elseif (utf8_strlen((string)$query) > 255) {
				$errors[] = sprintf($this->language->get('error_csv_query_length'), $line_no);

				$valid = false;
			}

			if ((string)$keyword === '') {
				$errors[] = sprintf($this->language->get('error_csv_keyword'), $line_no);

				$valid = false;
			} elseif (utf8_strlen((string)$keyword) > 255) {
				$errors[] = sprintf($this->language->get('error_csv_keyword_length'), $line_no);

				$valid = false;
			}

			if (!$valid) {
				continue;
			}

			$query = (string)$query;
			$keyword = (string)$keyword;
			$query_key = mb_strtolower($query, 'UTF-8');
			$group_key = $this->groupKey($query, $store_id, $language_id);

			// Duplicate row for the same alias inside this file?
			if (isset($file_groups[$group_key])) {
				$errors[] = sprintf($this->language->get('error_csv_duplicate'), $line_no, $query, $store_id, (string)$language_field, $file_groups[$group_key]);

				continue;
			}

			// Keyword already claimed by another query in this file?
			$keyword_key = $store_id . '|' . $language_id . '|' . mb_strtolower($keyword, 'UTF-8');

			if (isset($file_keywords[$keyword_key]) && $file_keywords[$keyword_key]['query_key'] !== $query_key) {
				$errors[] = sprintf($this->language->get('error_csv_keyword_conflict'), $line_no, $keyword, $file_keywords[$keyword_key]['query'], $store_id, (string)$language_field);

				continue;
			}

			// Keyword held by a different query in the DB?
			if (isset($existing_keywords[$keyword_key]) && mb_strtolower($existing_keywords[$keyword_key], 'UTF-8') !== $query_key) {
				$errors[] = sprintf($this->language->get('error_csv_keyword_conflict'), $line_no, $keyword, $existing_keywords[$keyword_key], $store_id, (string)$language_field);

				continue;
			}

			$file_groups[$group_key] = $line_no;
			$file_keywords[$keyword_key] = array('query' => $query, 'query_key' => $query_key);

			$rows[] = array(
				'store_id'    => $store_id,
				'language_id' => $language_id,
				'query'       => $query,
				'keyword'     => $keyword
			);
		}

		fclose($handle);

		if ($errors) {
			return array('error' => implode('<br>', $errors));
		}

		if (!$rows) {
			return array('error' => $this->language->get('error_csv_empty'));
		}

		$added = 0;
		$updated = 0;

		try {
			$this->db->query("START TRANSACTION");

			foreach ($rows as $row) {
				$seo_url_id = $this->model_design_seo_url->getSeoUrlIdByGroup($row['query'], $row['store_id'], $row['language_id']);

				if ($seo_url_id) {
					$this->model_design_seo_url->editSeoUrl($seo_url_id, $row);

					$updated++;
				} else {
					$this->model_design_seo_url->addSeoUrl($row);

					$added++;
				}
			}

			$this->db->query("COMMIT");
		} catch (Exception $e) {
			$this->db->query("ROLLBACK");

			return array('error' => 'Import failed: ' . $e->getMessage());
		}

		return array('success' => sprintf($this->language->get('text_import_success'), count($rows), $added, $updated));
	}

	/**
	 * Case-insensitive identity of one alias row: (query, store_id, language_id).
	 */
	private function groupKey($query, $store_id, $language_id) {
		return mb_strtolower($query, 'UTF-8') . '|' . $store_id . '|' . $language_id;
	}

	/**
	 * Escape a value for CSV output (always quoted, quotes doubled).
	 */
	private function csvField($value) {
		return '"' . str_replace('"', '""', (string)$value) . '"';
	}
}
