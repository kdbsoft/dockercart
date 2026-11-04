<?php
/**
 * Landing page builder (admin).
 *
 *   index          page list           add        create page (name + slug)
 *   builder        standalone builder  save       AJAX save (page + blocks)
 *   copy           duplicate page      delete     delete pages
 *   status         publish toggle      previewToken  fresh HMAC preview token
 *   templates      JSON template list  saveTemplate / deleteTemplate
 *   autocomplete   product/category search for the properties panel
 *
 * Read routes need `access` on design/landing, writes additionally need
 * `modify` (see startup/permission.php and Controller::userCanModify()).
 *
 * @package    DockerCart
 * @subpackage Controller
 */

declare(strict_types=1);

class ControllerDesignLanding extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('design/landing');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('design/landing');

		$this->getList();
	}

	public function add() {
		$this->load->language('design/landing');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('design/landing');

		if (($this->request->server['REQUEST_METHOD'] === 'POST') && $this->validateForm()) {
			$data = $this->request->post;

			$data['descriptions'] = [
				(int)$this->config->get('config_language_id') => [
					'slug'             => (string)($this->request->post['slug'] ?? ''),
					'meta_title'       => (string)($this->request->post['meta_title'] ?? ''),
					'meta_description' => (string)($this->request->post['meta_description'] ?? ''),
					'meta_keyword'     => (string)($this->request->post['meta_keyword'] ?? ''),
				],
			];

			$landing_id = $this->model_design_landing->addLanding($data);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('design/landing/builder', 'user_token=' . $this->session->data['user_token'] . '&landing_id=' . (int)$landing_id, true));
		}

		$this->getForm();
	}

	public function builder() {
		$this->load->language('design/landing');

		if (!isset($this->request->get['landing_id'])) {
			$this->response->redirect($this->url->link('design/landing', 'user_token=' . $this->session->data['user_token'], true));
		}

		$landing_id = (int)$this->request->get['landing_id'];

		$this->load->model('design/landing');

		$landing = $this->model_design_landing->getLanding($landing_id);

		if (!$landing) {
			$this->response->redirect($this->url->link('design/landing', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->document->setTitle($this->language->get('heading_builder'));

		$this->load->model('localisation/language');
		$this->load->model('design/banner');

		$languages = $this->model_localisation_language->getLanguages();

		$data['heading_title_builder'] = $this->language->get('heading_builder');
		$data['landing_id'] = $landing_id;
		$data['user_token'] = $this->session->data['user_token'];

		$data['back'] = $this->url->link('design/landing', 'user_token=' . $this->session->data['user_token'], true);
		$data['save_url'] = $this->url->link('design/landing/save', 'user_token=' . $this->session->data['user_token'] . '&landing_id=' . $landing_id, true);
		$data['token_url'] = $this->url->link('design/landing/previewToken', 'user_token=' . $this->session->data['user_token'] . '&landing_id=' . $landing_id, true);
		$data['template_url'] = $this->url->link('design/landing/saveTemplate', 'user_token=' . $this->session->data['user_token'] . '&landing_id=' . $landing_id, true);
		$data['templates_url'] = $this->url->link('design/landing/templates', 'user_token=' . $this->session->data['user_token'], true);
		$data['template_delete_url'] = $this->url->link('design/landing/deleteTemplate', 'user_token=' . $this->session->data['user_token'], true);
		$data['autocomplete_url'] = $this->url->link('design/landing/autocomplete', 'user_token=' . $this->session->data['user_token'], true);
		$data['filemanager_url'] = $this->url->link('common/filemanager', 'user_token=' . $this->session->data['user_token'], true);
		$data['list_url'] = $this->url->link('design/landing', 'user_token=' . $this->session->data['user_token'], true);

		$preview_token = $this->makePreviewToken($landing_id);
		$descriptions = $this->model_design_landing->getLandingDescriptions($landing_id);

		// Preview URLs use the page's own keyword path (no ?route= form: the SEO
		// startup redirects those to the pretty URL, which would drop the
		// builder parameters on some hosts). A draft still has its keyword row.
		$preview_bases = [];
		$fallback_base = HTTP_CATALOG . 'index.php?route=landing/page&landing_id=' . $landing_id;

		foreach ($languages as $language) {
			$language_id = (int)$language['language_id'];
			$slug = trim((string)($descriptions[$language_id]['slug'] ?? ''));
			$query = ['preview_token' => $preview_token, 'lp_builder' => 1];

			if ($slug !== '') {
				$preview_bases[$language_id] = HTTP_CATALOG . $slug . '?' . http_build_query($query);
			} else {
				$preview_bases[$language_id] = $fallback_base . '&' . http_build_query($query);
			}
		}

		$data['preview_bases'] = $preview_bases;
		$data['preview_base'] = $preview_bases[(int)$this->config->get('config_language_id')] ?? (string)reset($preview_bases);
		$data['image_url'] = HTTP_CATALOG . 'image/';

		$data['landing'] = [
			'landing_id'     => (int)$landing['landing_id'],
			'name'           => (string)$landing['name'],
			'status'         => (int)$landing['status'],
			'chrome'         => (string)$landing['chrome'],
			'is_home'        => (int)$landing['is_home'],
			'noindex'        => (int)$landing['noindex'],
			'custom_css'     => (string)$landing['custom_css'],
			'viewed'         => (int)$landing['viewed'],
			'version'        => (int)$landing['version'],
			'date_publish'   => $this->dateForInput($landing['date_publish']),
			'date_unpublish' => $this->dateForInput($landing['date_unpublish']),
			'descriptions'   => $descriptions,
		];

		$data['blocks'] = $this->model_design_landing->getBlocks($landing_id);

		$data['languages'] = array_values($languages);
		$data['default_language_id'] = (int)$this->config->get('config_language_id');

		$data['schema'] = Dockercart\Landing\Blocks::schema();
		$data['groups'] = Dockercart\Landing\Blocks::GROUPS;

		$data['templates'] = $this->model_design_landing->getTemplates();

		$banners = [];

		foreach ($this->model_design_banner->getBanners() as $banner) {
			$banners[] = [
				'banner_id' => (int)$banner['banner_id'],
				'name'      => (string)$banner['name'],
			];
		}

		$data['banners'] = $banners;

		$data['i18n'] = $this->buildI18n($languages);

		// Do NOT load admin chrome — standalone builder page (same as design/layout/builder).
		$this->response->setOutput($this->load->view('design/landing_builder', $data));
	}

	/**
	 * AJAX save: page settings + the complete ordered block list.
	 */
	public function save() {
		$this->load->language('design/landing');

		$this->load->model('design/landing');

		$json = [];

		if (!$this->user->hasPermission('modify', 'design/landing')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$landing_id = isset($this->request->get['landing_id']) ? (int)$this->request->get['landing_id'] : 0;

		if (!$landing_id) {
			$json['error'] = $this->language->get('error_landing');
		}

		$payload = [];

		if (!isset($json['error'])) {
			$payload = $this->jsonBody();

			if (!$payload) {
				$json['error'] = $this->language->get('error_payload');
			}
		}

		if (!isset($json['error'])) {
			$errors = $this->model_design_landing->validateLanding($payload, $landing_id);

			if ($errors) {
				$json['fields'] = $errors;
				$json['error'] = reset($errors);
			}
		}

		if (!isset($json['error'])) {
			$result = $this->model_design_landing->saveLanding($landing_id, $payload);

			if (empty($result['success'])) {
				if (!empty($result['conflict'])) {
					$json['conflict'] = true;
					$json['version'] = (int)($result['version'] ?? 0);
					$json['error'] = $this->language->get('error_conflict');
				} else {
					$json['error'] = $this->language->get('error_save');
				}
			} else {
				$json['success'] = $this->language->get('text_success_save');
				$json['version'] = (int)$result['version'];
				$json['blocks'] = $this->model_design_landing->getBlocks($landing_id);
				$json['landing'] = $this->model_design_landing->getLanding($landing_id);
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function previewToken() {
		$this->load->model('design/landing');

		$json = [];

		if (!$this->user->hasPermission('access', 'design/landing')) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode(['error' => 'permission']));

			return;
		}

		$landing_id = isset($this->request->get['landing_id']) ? (int)$this->request->get['landing_id'] : 0;
		$landing = $this->model_design_landing->getLanding($landing_id);

		if (!$landing) {
			$json['error'] = 'not_found';
		} else {
			$json['token'] = $this->makePreviewToken($landing_id);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function templates() {
		$this->load->model('design/landing');

		$json = [
			'templates' => $this->model_design_landing->getTemplates(),
		];

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function saveTemplate() {
		$this->load->language('design/landing');

		$this->load->model('design/landing');

		$json = [];

		if (!$this->user->hasPermission('modify', 'design/landing')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$request = $this->jsonBody();
		$name = trim((string)($request['name'] ?? ''));
		$blocks = $request['blocks'] ?? null;

		if (!isset($json['error']) && ($this->strlen($name) < 2 || $this->strlen($name) > 128)) {
			$json['error'] = $this->language->get('error_template_name');
		}

		if (!isset($json['error']) && (!is_array($blocks) || !$blocks)) {
			$json['error'] = $this->language->get('error_template_blocks');
		}

		if (!isset($json['error'])) {
			$template_id = $this->model_design_landing->addTemplate($name, $blocks);

			$json['success'] = $this->language->get('text_success_template');
			$json['template_id'] = $template_id;
			$json['templates'] = $this->model_design_landing->getTemplates();
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function deleteTemplate() {
		$this->load->language('design/landing');

		$this->load->model('design/landing');

		$json = [];

		if (!$this->user->hasPermission('modify', 'design/landing')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$template_id = isset($this->request->post['template_id']) ? (int)$this->request->post['template_id'] : 0;

		if (!isset($json['error']) && $template_id > 0) {
			$this->model_design_landing->deleteTemplate($template_id);

			$json['success'] = $this->language->get('text_success');
			$json['templates'] = $this->model_design_landing->getTemplates();
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Product / category search for the properties panel (names + ids only).
	 */
	public function autocomplete() {
		$json = [];

		if (!$this->user->hasPermission('access', 'design/landing')) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode([]));

			return;
		}

		$type = isset($this->request->get['type']) ? (string)$this->request->get['type'] : 'product';
		$filter = isset($this->request->get['filter_name']) ? trim((string)$this->request->get['filter_name']) : '';
		$limit = isset($this->request->get['limit']) ? max(1, min(30, (int)$this->request->get['limit'])) : 10;

		if ($type === 'category') {
			$this->load->model('catalog/category');

			foreach ($this->model_catalog_category->getCategories(['filter_name' => $filter, 'sort' => 'name', 'order' => 'ASC', 'start' => 0, 'limit' => $limit]) as $category) {
				$json[] = [
					'id'   => (int)$category['category_id'],
					'name' => html_entity_decode((string)$category['name'], ENT_QUOTES, 'UTF-8'),
				];
			}
		} else {
			$this->load->model('catalog/product');

			foreach ($this->model_catalog_product->getProducts(['filter_search' => $filter, 'sort' => 'pd.name', 'order' => 'ASC', 'start' => 0, 'limit' => $limit]) as $product) {
				$json[] = [
					'id'    => (int)$product['product_id'],
					'name'  => html_entity_decode((string)$product['name'], ENT_QUOTES, 'UTF-8'),
					'model' => (string)$product['model'],
				];
			}

			// Keep the selection order working when ids are requested directly.
			if (!$json && $filter === '' && isset($this->request->get['ids'])) {
				$ids = array_filter(array_map('intval', explode(',', (string)$this->request->get['ids'])));

				if ($ids) {
					foreach ($this->model_catalog_product->getProductsByIds($ids) as $id => $product) {
						$json[] = [
							'id'    => (int)$id,
							'name'  => html_entity_decode((string)$product['name'], ENT_QUOTES, 'UTF-8'),
							'model' => (string)($product['model'] ?? ''),
						];
					}
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function delete() {
		$this->load->language('design/landing');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('design/landing');

		if (isset($this->request->post['selected']) && $this->validateDelete()) {
			foreach ((array)$this->request->post['selected'] as $landing_id) {
				$this->model_design_landing->deleteLanding((int)$landing_id);
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('design/landing', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->getList();
	}

	public function copy() {
		$this->load->language('design/landing');

		$this->load->model('design/landing');

		if (!$this->user->hasPermission('modify', 'design/landing')) {
			$this->session->data['error_warning'] = $this->language->get('error_permission');
		} elseif (isset($this->request->get['landing_id'])) {
			$landing_id = $this->model_design_landing->duplicateLanding((int)$this->request->get['landing_id']);

			if ($landing_id) {
				$this->session->data['success'] = $this->language->get('text_success_copy');
			}
		}

		$this->response->redirect($this->url->link('design/landing', 'user_token=' . $this->session->data['user_token'], true));
	}

	public function status() {
		$this->load->language('design/landing');

		$this->load->model('design/landing');

		$json = [];

		if (!$this->user->hasPermission('modify', 'design/landing')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$landing_id = isset($this->request->get['landing_id']) ? (int)$this->request->get['landing_id'] : 0;
		$status = isset($this->request->get['status']) ? (int)$this->request->get['status'] : 0;

		if (!isset($json['error']) && $landing_id > 0) {
			$this->db->query("UPDATE `" . DB_PREFIX . "landing` SET status = '" . ($status ? 1 : 0) . "', version = version + 1, date_modified = '" . date('Y-m-d H:i:s') . "' WHERE landing_id = '" . $landing_id . "'");

			$json['success'] = $this->language->get('text_success');
			$json['status'] = $status ? 1 : 0;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	// ------------------------------------------------------------- internals

	protected function getList() {
		$this->load->language('design/landing');

		$data = [];

		$sort = isset($this->request->get['sort']) ? (string)$this->request->get['sort'] : 'date_modified';
		$order = isset($this->request->get['order']) ? (string)$this->request->get['order'] : 'DESC';
		$page = isset($this->request->get['page']) ? max(1, (int)$this->request->get['page']) : 1;

		$filter = [
			'filter_name'   => $this->request->get['filter_name'] ?? '',
			'filter_status' => $this->request->get['filter_status'] ?? '',
			'sort'          => $sort,
			'order'         => $order,
			'start'         => ($page - 1) * (int)$this->config->get('config_limit_admin'),
			'limit'         => (int)$this->config->get('config_limit_admin'),
		];

		$url = '';

		foreach (['filter_name', 'filter_status', 'sort', 'order', 'page'] as $key) {
			if (isset($this->request->get[$key]) && $this->request->get[$key] !== '') {
				$url .= '&' . $key . '=' . urlencode((string)$this->request->get[$key]);
			}
		}

		$data['landings'] = [];

		$language_id = (int)$this->config->get('config_language_id');

		foreach ($this->model_design_landing->getLandings($filter) as $landing) {
			$slug = (string)$landing['slug'];

			$data['landings'][] = [
				'landing_id'    => (int)$landing['landing_id'],
				'name'          => (string)$landing['name'],
				'slug'          => $slug,
				'url'           => $slug !== '' ? HTTP_CATALOG . $slug : '',
				'status'        => (int)$landing['status'],
				'is_home'       => (int)$landing['is_home'],
				'chrome'        => (string)$landing['chrome'],
				'blocks'        => (int)$landing['blocks'],
				'viewed'        => (int)$landing['viewed'],
				'date_modified' => date($this->language->get('date_format_short') . ' ' . $this->language->get('time_format'), strtotime((string)$landing['date_modified'])),
				'edit'          => $this->url->link('design/landing/builder', 'user_token=' . $this->session->data['user_token'] . '&landing_id=' . (int)$landing['landing_id'], true),
				'copy'          => $this->url->link('design/landing/copy', 'user_token=' . $this->session->data['user_token'] . '&landing_id=' . (int)$landing['landing_id'], true),
				'status_url'    => $this->url->link('design/landing/status', 'user_token=' . $this->session->data['user_token'] . '&landing_id=' . (int)$landing['landing_id'], true),
				'delete'        => $this->url->link('design/landing/delete', 'user_token=' . $this->session->data['user_token'] . $url, true),
			];
		}

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_list_subtitle'] = $this->language->get('text_list_subtitle');

		$data['error_warning'] = $this->error['warning'] ?? ($this->session->data['error_warning'] ?? '');
		unset($this->session->data['error_warning']);

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['selected'] = (array)($this->request->post['selected'] ?? []);

		$data['add'] = $this->url->link('design/landing/add', 'user_token=' . $this->session->data['user_token'], true);
		$data['delete'] = $this->url->link('design/landing/delete', 'user_token=' . $this->session->data['user_token'] . $url, true);

		$data['filter_name'] = $filter['filter_name'];
		$data['filter_status'] = $filter['filter_status'];
		$data['user_token'] = $this->session->data['user_token'];

		$sort_url = '';

		foreach (['filter_name', 'filter_status', 'page'] as $key) {
			if ($url !== '' && isset($this->request->get[$key]) && $this->request->get[$key] !== '') {
				$sort_url .= '&' . $key . '=' . urlencode((string)$this->request->get[$key]);
			}
		}

		$data['sort_name'] = $this->url->link('design/landing', 'user_token=' . $this->session->data['user_token'] . '&sort=name' . ($order === 'ASC' ? '&order=DESC' : '&order=ASC') . $sort_url, true);
		$data['sort_status'] = $this->url->link('design/landing', 'user_token=' . $this->session->data['user_token'] . '&sort=status' . ($order === 'ASC' ? '&order=DESC' : '&order=ASC') . $sort_url, true);
		$data['sort_viewed'] = $this->url->link('design/landing', 'user_token=' . $this->session->data['user_token'] . '&sort=viewed' . ($order === 'ASC' ? '&order=DESC' : '&order=ASC') . $sort_url, true);
		$data['sort_date_modified'] = $this->url->link('design/landing', 'user_token=' . $this->session->data['user_token'] . '&sort=date_modified' . ($order === 'ASC' ? '&order=DESC' : '&order=ASC') . $sort_url, true);
		$data['sort'] = $sort;
		$data['order'] = $order;

		$total = $this->model_design_landing->getTotalLandings($filter);

		$pagination = new Pagination();
		$pagination->total = $total;
		$pagination->page = $page;
		$pagination->limit = (int)$this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('design/landing', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();
		$data['results'] = $pagination->renderResults($this->language->get('text_pagination'));

		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();
		$data['language_id'] = $language_id;

		$data['can_modify'] = $this->user->hasPermission('modify', 'design/landing');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('design/landing_list', $data));
	}

	protected function getForm() {
		$data = [];

		$data['text_form'] = $this->language->get('text_add');
		$data['text_form_subtitle'] = $this->language->get('text_add_subtitle');

		$data['error_warning'] = $this->error['warning'] ?? '';
		$data['error_name'] = $this->error['name'] ?? '';
		$data['error_slug'] = $this->error['slug'] ?? '';
		$data['error_slug_global'] = $this->error['slug_global'] ?? '';

		$data['action'] = $this->url->link('design/landing/add', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('design/landing', 'user_token=' . $this->session->data['user_token'], true);

		$data['name'] = $this->request->post['name'] ?? '';
		$data['slug'] = $this->request->post['slug'] ?? '';
		$data['status'] = (int)($this->request->post['status'] ?? 0);
		$data['chrome'] = (string)($this->request->post['chrome'] ?? 'full');

		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();
		$data['default_language_id'] = (int)$this->config->get('config_language_id');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('design/landing_form', $data));
	}

	protected function validateForm(): bool {
		if (!$this->user->hasPermission('modify', 'design/landing')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$default_language_id = (int)$this->config->get('config_language_id');

		$slug = Dockercart\Landing\Url::normalizeSlug((string)($this->request->post['slug'] ?? ''));

		$this->request->post['slug'] = $slug;

		$errors = $this->model_design_landing->validateLanding([
			'name'         => (string)($this->request->post['name'] ?? ''),
			'status'       => !empty($this->request->post['status']),
			'descriptions' => [$default_language_id => ['slug' => $slug]],
		]);

		if (isset($errors['name'])) {
			$this->error['name'] = $errors['name'];
		}

		if (isset($errors['slug'][$default_language_id])) {
			$this->error['slug_global'] = $this->language->get('error_slug');
			$this->error['slug'] = $errors['slug'][$default_language_id];
		}

		return !$this->error;
	}

	protected function validateDelete(): bool {
		if (!$this->user->hasPermission('modify', 'design/landing')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	/**
	 * Decodes the JSON body sent by the builder.
	 *
	 * The payload is a whole page (page settings + every block), which contains
	 * quotes and ampersands, so it is sent as `application/json` and read from
	 * php://input — POST values go through Request::clean() (htmlspecialchars)
	 * and would corrupt the JSON.
	 *
	 * @return array<string, mixed>
	 */
	private function jsonBody(): array {
		$raw = file_get_contents('php://input');

		if (!is_string($raw) || trim($raw) === '') {
			return [];
		}

		$decoded = json_decode($raw, true);

		return is_array($decoded) ? $decoded : [];
	}

	/**
	 * Signed preview token for a landing page.
	 */
	private function makePreviewToken(int $landing_id): string {
		return Dockercart\Landing\Url::createPreviewToken(
			$landing_id,
			Dockercart\Landing\Url::PREVIEW_TOKEN_LIFETIME,
			(string)$this->config->get('config_encryption')
		);
	}

	/**
	 * Label/UI translation map handed to the builder JS.
	 *
	 * @param array<int, array<string, mixed>> $languages
	 * @return array<string, string>
	 */
	private function buildI18n(array $languages): array {
		$keys = [
			'text_save',
			'text_saved',
			'text_saving',
			'text_preview',
			'text_page_settings',
			'text_block_settings',
			'text_select_block',
			'text_no_blocks',
			'text_palette',
			'text_templates',
			'text_no_templates',
			'text_save_template',
			'text_insert_template',
			'text_delete_template',
			'text_template_name',
			'text_duplicate',
			'text_remove',
			'text_undo',
			'text_redo',
			'text_autosave',
			'text_unsaved',
			'text_language',
			'text_view_desktop',
			'text_view_tablet',
			'text_view_mobile',
			'text_add_section',
			'text_hidden_block',
			'text_hidden_device',
			'text_search_blocks',
			'text_section_settings',
			'text_no_banners',
			'text_success',
			'text_choose_image',
			'text_clear',
			'text_add_row',
			'text_pick_products',
			'text_pick_category',
			'text_pick_banner',
			'text_error_save',
			'text_conflict',
			'text_back',
			'text_status_draft',
			'text_status_published',
			'text_created',
			'text_copy',
			'entry_name',
			'entry_slug',
			'entry_meta_title',
			'entry_meta_description',
			'entry_meta_keyword',
			'entry_status',
			'entry_chrome',
			'entry_chrome_full',
			'entry_chrome_bare',
			'entry_noindex',
			'entry_is_home',
			'entry_date_publish',
			'entry_date_unpublish',
			'entry_custom_css',
			'entry_viewed',
			'entry_revision',
			'button_save',
			'button_cancel',
			'error_name',
			'error_slug',
			'error_slug_used',
			'error_slug_invalid',
			'error_dates',
			'error_home_status',
			'text_clone_hint',
		];

		foreach (Dockercart\Landing\Blocks::GROUPS as $group_key) {
			$keys[] = $group_key;
		}

		foreach (Dockercart\Landing\Blocks::all() as $block) {
			$keys[] = $block['name'];
		}

		foreach (Dockercart\Landing\Blocks::codes() as $code) {
			foreach ($this->fieldLabelKeys($code) as $label_key) {
				$keys[] = $label_key;
			}
		}

		$i18n = [];

		foreach (array_unique($keys) as $key) {
			$value = (string)$this->language->get($key);
			$i18n[$key] = $value === $key ? $key : $value;
		}

		return $i18n;
	}

	/**
	 * @return array<int, string>
	 */
	private function fieldLabelKeys(string $code): array {
		$keys = [];

		foreach (Dockercart\Landing\Blocks::fields($code) as $field) {
			if (!empty($field['label'])) {
				$keys[] = (string)$field['label'];
			}

			if (!empty($field['options']) && is_array($field['options'])) {
				foreach ($field['options'] as $option_label) {
					$keys[] = (string)$option_label;
				}
			}

			if (!empty($field['fields']) && is_array($field['fields'])) {
				foreach ($field['fields'] as $sub) {
					if (!empty($sub['label'])) {
						$keys[] = (string)$sub['label'];
					}

					if (!empty($sub['options']) && is_array($sub['options'])) {
						foreach ($sub['options'] as $option_label) {
							$keys[] = (string)$option_label;
						}
					}
				}
			}
		}

		return $keys;
	}

	/**
	 * @param mixed $value
	 */
	private function dateForInput($value): string {
		$value = (string)$value;

		if ($value === '' || substr($value, 0, 10) === '0000-00-00') {
			return '';
		}

		$timestamp = strtotime($value);

		return $timestamp ? date('Y-m-d\TH:i', $timestamp) : '';
	}

	private function strlen(string $value): int {
		return function_exists('utf8_strlen') ? utf8_strlen($value) : strlen($value);
	}
}
