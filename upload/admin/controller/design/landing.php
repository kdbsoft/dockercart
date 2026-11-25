<?php
/**
 * Landing page builder (admin).
 *
 *   index          page list           add        create page (name + slug)
 *   builder        standalone builder  save       AJAX save (page + blocks)
 *   copy           duplicate page      delete     delete pages
 *   status         publish toggle      previewToken  fresh HMAC preview token
 *   templates      JSON template list  saveTemplate / deleteTemplate
 *   autocomplete   entity search for pickers and link fields
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

		// An information-owned landing is that page's composed body: back
		// to the information module, the canvas shows the page's own URL and
		// the settings slide-over drops slug/status/schedule.
		$owner_information_id = (int)($landing['information_id'] ?? 0);

		$data['owner_information_id'] = $owner_information_id;
		$data['owner_id'] = $owner_information_id;
		$data['owner_id_field'] = 'information_id';
		$data['back'] = $owner_information_id
			? $this->url->link('catalog/information', 'user_token=' . $this->session->data['user_token'], true)
			: $this->url->link('design/landing', 'user_token=' . $this->session->data['user_token'], true);
		$data['save_url'] = $this->url->link('design/landing/save', 'user_token=' . $this->session->data['user_token'] . '&landing_id=' . $landing_id, true);
		$data['token_url'] = $this->url->link('design/landing/previewToken', 'user_token=' . $this->session->data['user_token'] . '&landing_id=' . $landing_id, true);
		$data['template_url'] = $this->url->link('design/landing/saveTemplate', 'user_token=' . $this->session->data['user_token'] . '&landing_id=' . $landing_id, true);
		$data['templates_url'] = $this->url->link('design/landing/templates', 'user_token=' . $this->session->data['user_token'], true);
		$data['template_delete_url'] = $this->url->link('design/landing/deleteTemplate', 'user_token=' . $this->session->data['user_token'], true);
		$data['autocomplete_url'] = $this->url->link('design/landing/autocomplete', 'user_token=' . $this->session->data['user_token'], true);
		$data['filemanager_url'] = $this->url->link('common/filemanager', 'user_token=' . $this->session->data['user_token'], true);
		$data['list_url'] = $this->url->link('design/landing', 'user_token=' . $this->session->data['user_token'], true);
		$data['site_design'] = $this->model_design_landing->getSiteDesign();
		$data['site_design_schema'] = \Dockercart\Landing\Design::schema();
		$data['site_design_url'] = $this->url->link('design/landing/saveSiteDesign', 'user_token=' . $this->session->data['user_token'], true);

		$preview_token = $this->makePreviewToken($landing_id);
		$descriptions = $this->model_design_landing->getLandingDescriptions($landing_id);

		// The external Preview tab opens without lp_builder=1 (no fake editing
		// toolbar). It carries an encrypted link back to this builder, so the
		// storefront visitor never sees the admin user token.
		$builder_back_url = $this->url->link(
			'design/landing/builder',
			'user_token=' . $this->session->data['user_token'] . '&landing_id=' . $landing_id,
			true
		);

		$encrypted_back = (new \Encryption())->encrypt((string)$this->config->get('config_encryption'), $builder_back_url);

		// Display values are stored html-encoded (classic form / JSON save both
		// encode at the boundary): decode them for the builder inputs, the save
		// re-encodes. Same flow as the owner titles below.
		foreach ($descriptions as $language_id => $row) {
			foreach (['meta_title', 'meta_description', 'meta_keyword'] as $key) {
				$descriptions[$language_id][$key] = html_entity_decode((string)($row[$key] ?? ''), ENT_QUOTES, 'UTF-8');
			}
		}

		// Preview URLs use the page's own keyword path (no ?route= form: the SEO
		// startup redirects those to the pretty URL, which would drop the
		// builder parameters on some hosts). A draft still has its keyword row.
		$preview_bases = [];
		$fallback_base = HTTP_CATALOG . 'index.php?route=landing/page&landing_id=' . $landing_id;

		$information_seo_urls = [];

		if ($owner_information_id) {
			$this->load->model('catalog/information');

			$information_seo_urls = $this->model_catalog_information->getInformationSeoUrls($owner_information_id);

			$fallback_base = HTTP_CATALOG . 'index.php?route=information/information&information_id=' . $owner_information_id;
		}

		$preview_public_bases = [];

		foreach ($languages as $language) {
			$language_id = (int)$language['language_id'];
			$query = ['preview_token' => $preview_token, 'lp_builder' => 1];
			$public_query = ['preview_token' => $preview_token, 'builder_back' => $encrypted_back];

			if ($owner_information_id) {
				// The owned page renders at the information page's own keyword.
				$keyword = trim((string)($information_seo_urls[0][$language_id] ?? ''));
			} else {
				$keyword = trim((string)($descriptions[$language_id]['slug'] ?? ''));
			}

			if ($keyword !== '') {
				// Language-prefixed: the prefix is the explicit language (the
				// startup's first priority), so the canvas follows the selected
				// tab even when several languages share one keyword - the
				// keyword-inferred language never gets a say here.
				$path = HTTP_CATALOG . $language['code'] . '/' . $keyword . '?';
			} else {
				// No keyword for this language: the route form + switch_lang
				// (enforceCleanSeoUrls leaves preview_token URLs alone, so the
				// session language survives).
				$path = $fallback_base . '&';
			}

			$preview_bases[$language_id] = $path . http_build_query($query);
			$preview_public_bases[$language_id] = $path . http_build_query($public_query);
		}

		$data['preview_bases'] = $preview_bases;
		$data['preview_base'] = $preview_bases[(int)$this->config->get('config_language_id')] ?? (string)reset($preview_bases);
		$data['preview_public_bases'] = $preview_public_bases;
		$data['preview_public_base'] = $preview_public_bases[(int)$this->config->get('config_language_id')] ?? (string)reset($preview_public_bases);
		$data['image_url'] = HTTP_CATALOG . 'image/';

		$data['landing'] = [
			'landing_id'     => (int)$landing['landing_id'],
			'name'           => html_entity_decode((string)$landing['name'], ENT_QUOTES, 'UTF-8'),
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

		// An owned landing shows the information page title per language in
		// the top bar (the landing's own name is a default-language snapshot).
		// Edits write through to oc_information_description.title for the
		// selected language (catalog/information/updateNames), which is what
		// the storefront H1/meta use.
		$data['owner_titles'] = [];
		$data['can_edit_owner_titles'] = false;
		$data['owner_names_url'] = '';
		$data['owner'] = ['status' => 0, 'descriptions' => []];
		$data['can_edit_owner'] = false;

		if ($owner_information_id) {
			$this->load->model('catalog/information');

			$owner_descriptions = $this->model_catalog_information->getInformationDescriptions($owner_information_id);
			$owner_information = $this->model_catalog_information->getInformation($owner_information_id);
			$owner_seo_urls = $this->model_catalog_information->getInformationSeoUrls($owner_information_id);

			foreach ($languages as $language) {
				$language_id = (int)$language['language_id'];

				// Titles are stored html-escaped (Request::clean over POST, same
				// as the information form) - decode for the input display.
				$data['owner_titles'][$language_id] = html_entity_decode((string)($owner_descriptions[$language_id]['title'] ?? ''), ENT_QUOTES, 'UTF-8');
			}

			$data['can_edit_owner_titles'] = $this->user->hasPermission('modify', 'catalog/information');
			$data['can_edit_owner'] = $data['can_edit_owner_titles'];

			if ($data['can_edit_owner_titles']) {
				$data['owner_names_url'] = $this->url->link('catalog/information/updateNames', 'user_token=' . $this->session->data['user_token'], true);
			}

			// Status, meta tags and the default-store SEO keyword are edited
			// straight from the builder Page Settings. The top bar works off
			// the same status, so the badge never disagrees with the page.
			$data['landing']['status'] = (int)($owner_information['status'] ?? $data['landing']['status']);

			$data['owner'] = [
				'status'       => (int)($owner_information['status'] ?? 0),
				'descriptions' => [],
			];

			foreach ($languages as $language) {
				$language_id = (int)$language['language_id'];
				$description = $owner_descriptions[$language_id] ?? [];

				$data['owner']['descriptions'][$language_id] = [
					'slug'             => html_entity_decode(trim((string)(($owner_seo_urls[0] ?? [])[$language_id] ?? '')), ENT_QUOTES, 'UTF-8'),
					'meta_title'       => html_entity_decode((string)($description['meta_title'] ?? ''), ENT_QUOTES, 'UTF-8'),
					'meta_description' => html_entity_decode((string)($description['meta_description'] ?? ''), ENT_QUOTES, 'UTF-8'),
					'meta_keyword'     => html_entity_decode((string)($description['meta_keyword'] ?? ''), ENT_QUOTES, 'UTF-8'),
				];
			}
		}

		$blocks = $this->model_design_landing->getBlocks($landing_id);

		// The builder edits the same normalised shape the renderer uses. This
		// also folds legacy hero/CTA image settings into bg_image, so a picture
		// already on the page is visible in the Style tab before the next save.
		foreach ($blocks as &$block) {
			$code = (string)$block['code'];

			$block['settings'] = Dockercart\Landing\Blocks::normalizeSettings($code, (array)$block['settings']);

			foreach (array_keys((array)$block['content']) as $language_id) {
				$block['content'][(int)$language_id] = Dockercart\Landing\Blocks::normalizeContent($code, (array)$block['content'][$language_id]);
			}
		}

		unset($block);

		$data['blocks'] = $blocks;

		$data['languages'] = array_values($languages);
		$data['default_language_id'] = (int)$this->config->get('config_language_id');

		// Deep link from the information form opens the builder on the tab
		// language (each information language tab carries its own button).
		$initial_language_id = (int)($this->request->get['language_id'] ?? 0);
		$known_language_ids = array_map(static fn($language): int => (int)$language['language_id'], $languages);

		if (!in_array($initial_language_id, $known_language_ids, true)) {
			$initial_language_id = $data['default_language_id'];
		}

		$data['initial_language_id'] = $initial_language_id;

		$data['schema'] = Dockercart\Landing\Blocks::schema();
		$data['groups'] = Dockercart\Landing\Blocks::GROUPS;

		// Column count per section structure preset (the navigator needs it to
		// render one drop list per column).
		$data['structures'] = [];

		foreach (Dockercart\Landing\Blocks::all() as $code => $block) {
			if (empty($block['container'])) {
				continue;
			}

			foreach ($block['fields'] as $field) {
				if (($field['key'] ?? '') === 'structure' && !empty($field['options'])) {
					foreach (array_keys($field['options']) as $structure) {
						$data['structures'][(string)$structure] = Dockercart\Landing\Blocks::columnCount((string)$structure);
					}
				}
			}
		}

		$data['templates'] = $this->model_design_landing->getTemplates();

		$banners = [];

		foreach ($this->model_design_banner->getBanners() as $banner) {
			$banners[] = [
				'banner_id' => (int)$banner['banner_id'],
				'name'      => (string)$banner['name'],
			];
		}

		$data['banners'] = $banners;

		// Standalone page, no admin chrome: link the store favicon here so
		// the browser tab shows it (same source as the storefront header).
		$data['favicon_links'] = $this->faviconLinks();

		$data['i18n'] = $this->buildI18n($languages);

		// Cache-bust the builder script by mtime: nginx serves static assets
		// with long-lived immutable headers, so a plain F5 would keep running
		// yesterday's JS against today's page.
		$data['builder_js_version'] = (string)@filemtime(DIR_APPLICATION . 'view/javascript/dockercart_landing_builder.js');
		$data['admin_css_version'] = (string)@filemtime(DIR_APPLICATION . 'view/stylesheet/dockercart_tokens.css');
		$data['admin_stylesheet_version'] = (string)@filemtime(DIR_APPLICATION . 'view/stylesheet/stylesheet.css');

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
			$landing = $this->model_design_landing->getLanding($landing_id);

			$owner_type = $this->model_design_landing->ownerType($landing);
			$owned = $landing && $owner_type !== '';

			$errors = $this->model_design_landing->validateLanding($payload, $landing_id, $owned);

			if ($errors) {
				$json['fields'] = $errors;
				$json['error'] = reset($errors);
			}

			// Owned landings edit their owner (status, meta tags, default-store
			// SEO keyword) straight from the builder Page Settings. Validated up
			// front; written after the landing save.
			$owner = null;

			if (!isset($json['error']) && $owned && isset($payload['owner']) && is_array($payload['owner'])) {
				if (!$this->user->hasPermission('modify', 'catalog/information')) {
					$json['error'] = $this->language->get('error_permission');
				} else {
					$owner_errors = $this->model_design_landing->validateOwner($payload['owner'], (int)$landing['information_id']);

					if ($owner_errors) {
						$json['fields'] = $owner_errors;
						$json['error'] = reset($owner_errors);
					} else {
						$owner = [
							'information_id' => (int)$landing['information_id'],
							'status'         => !empty($payload['owner']['status']) ? 1 : 0,
							'descriptions'   => (array)($payload['owner']['descriptions'] ?? []),
						];
					}
				}
			}
		}

		if (!isset($json['error'])) {
			$payload = $this->encodeDisplayPayload($payload);

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

				if (isset($owner) && !empty($owner['information_id'])) {
					$this->load->model('catalog/information');

					$this->model_catalog_information->updateBuilderSettings($owner['information_id'], $owner['status'], $owner['descriptions']);

					$keywords = [];

					foreach ((array)$owner['descriptions'] as $language_id => $row) {
						$keywords[(int)$language_id] = is_array($row) ? (string)($row['slug'] ?? '') : '';
					}

					$this->model_catalog_information->updateBuilderSeoUrls($owner['information_id'], 0, $keywords);
				}

				// Payload-order list with client_id -> block_id, so the builder syncs
				// new/temp ids (and parent refs) without guessing by index.
				$json['blocks'] = $result['blocks'] ?? $this->model_design_landing->getBlocks($landing_id);
				$json['landing'] = $this->model_design_landing->getLanding($landing_id);
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * AJAX save for the builder's Site Settings panel: global landing tokens.
	 */
	public function saveSiteDesign() {
		$this->load->language('design/landing');
		$this->load->model('design/landing');

		$json = [];

		if (!$this->user->hasPermission('modify', 'design/landing')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$payload = $this->jsonBody();

		if (!$payload) {
			$json['error'] = $this->language->get('error_payload');
		}

		if (!isset($json['error'])) {
			$this->model_design_landing->saveSiteDesign($payload);

			$json['site_design'] = $this->model_design_landing->getSiteDesign();
			$json['success'] = $this->language->get('text_site_style_saved');
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
	 * Entity search for the properties panel (names + ids only).
	 *
	 * The product/category pickers use this endpoint as before; the link field
	 * uses the same endpoint for products, categories, manufacturers,
	 * information pages and blog posts. `ids` resolves the labels of links
	 * already stored in a block (the URL itself is the only stored value).
	 */
	public function autocomplete() {
		if (!$this->user->hasPermission('access', 'design/landing')) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode([]));

			return;
		}

		$types = ['product', 'category', 'manufacturer', 'information', 'blog'];

		$type = isset($this->request->get['type']) ? (string)$this->request->get['type'] : 'product';

		if (!in_array($type, $types, true)) {
			$type = 'product';
		}

		$filter = isset($this->request->get['filter_name']) ? trim((string)$this->request->get['filter_name']) : '';
		$limit = isset($this->request->get['limit']) ? max(1, min(30, (int)$this->request->get['limit'])) : 10;
		$language_id = isset($this->request->get['language_id']) ? max(1, (int)$this->request->get['language_id']) : (int)$this->config->get('config_language_id');

		$ids = [];

		if (isset($this->request->get['ids'])) {
			foreach (explode(',', (string)$this->request->get['ids']) as $part) {
				$id = (int)$part;

				if ($id > 0 && !in_array($id, $ids, true)) {
					$ids[] = $id;
				}

				if (count($ids) >= 30) {
					break;
				}
			}
		}

		$json = $ids ? $this->linkEntityItems($type, $ids, $language_id) : $this->searchLinkEntities($type, $filter, $limit, $language_id);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function searchLinkEntities(string $type, string $filter, int $limit, int $language_id): array {
		$items = [];

		switch ($type) {
			case 'category':
				$this->load->model('catalog/category');

				foreach ($this->model_catalog_category->getCategories(['filter_name' => $filter, 'sort' => 'name', 'order' => 'ASC', 'start' => 0, 'limit' => $limit]) as $category) {
					$items[] = [
						'id'   => (int)$category['category_id'],
						'name' => html_entity_decode((string)$category['name'], ENT_QUOTES, 'UTF-8'),
					];
				}

				break;

			case 'manufacturer':
				$this->load->model('catalog/manufacturer');

				foreach ($this->model_catalog_manufacturer->getManufacturers(['filter_name' => $filter, 'filter_status' => 1, 'start' => 0, 'limit' => $limit]) as $manufacturer) {
					$items[] = [
						'id'   => (int)$manufacturer['manufacturer_id'],
						'name' => strip_tags(html_entity_decode((string)$manufacturer['name'], ENT_QUOTES, 'UTF-8')),
					];
				}

				break;

			case 'information':
				$this->load->model('catalog/information');

				foreach ($this->model_catalog_information->getInformations(['filter_name' => $filter, 'start' => 0, 'limit' => $limit]) as $information) {
					$items[] = [
						'id'   => (int)$information['information_id'],
						'name' => strip_tags(html_entity_decode((string)($information['title'] ?? $information['name'] ?? ''), ENT_QUOTES, 'UTF-8')),
					];
				}

				break;

			case 'blog':
				$this->load->model('extension/module/dockercart_blog_post');

				foreach ($this->model_extension_module_dockercart_blog_post->getPosts(['filter_name' => $filter, 'start' => 0, 'limit' => $limit]) as $post) {
					$items[] = [
						'id'   => (int)$post['post_id'],
						'name' => strip_tags(html_entity_decode((string)($post['name'] ?? ''), ENT_QUOTES, 'UTF-8')),
					];
				}

				break;

			case 'product':
			default:
				$this->load->model('catalog/product');

				foreach ($this->model_catalog_product->getProducts(['filter_search' => $filter, 'sort' => 'pd.name', 'order' => 'ASC', 'start' => 0, 'limit' => $limit]) as $product) {
					$items[] = [
						'id'    => (int)$product['product_id'],
						'name'  => html_entity_decode((string)$product['name'], ENT_QUOTES, 'UTF-8'),
						'model' => (string)($product['model'] ?? ''),
					];
				}

				break;
		}

		foreach ($items as &$item) {
			$item['url'] = $this->linkSeoUrl($type, (string)$item['id'], $language_id);
		}

		unset($item);

		return $items;
	}

	/**
	 * Resolves stored link ids back to entity names for the properties panel.
	 *
	 * @param array<int, int> $ids
	 * @return array<int, array<string, mixed>>
	 */
	private function linkEntityItems(string $type, array $ids, int $language_id): array {
		$items = [];

		switch ($type) {
			case 'category':
				$this->load->model('catalog/category');

				foreach ($ids as $id) {
					$category = $this->model_catalog_category->getCategory($id);

					if (!$category) {
						continue;
					}

					$items[] = ['id' => $id, 'name' => strip_tags(html_entity_decode((string)$category['name'], ENT_QUOTES, 'UTF-8'))];
				}

				break;

			case 'manufacturer':
				$this->load->model('catalog/manufacturer');

				foreach ($ids as $id) {
					$manufacturer = $this->model_catalog_manufacturer->getManufacturer($id);

					if (!$manufacturer) {
						continue;
					}

					$items[] = ['id' => $id, 'name' => strip_tags(html_entity_decode((string)$manufacturer['name'], ENT_QUOTES, 'UTF-8'))];
				}

				break;

			case 'information':
				$this->load->model('catalog/information');

				foreach ($ids as $id) {
					$descriptions = $this->model_catalog_information->getInformationDescriptions($id);
					$description = $descriptions[$language_id] ?? ($descriptions ? reset($descriptions) : []);

					$items[] = ['id' => $id, 'name' => strip_tags(html_entity_decode((string)($description['title'] ?? ''), ENT_QUOTES, 'UTF-8'))];
				}

				break;

			case 'blog':
				$this->load->model('extension/module/dockercart_blog_post');

				foreach ($ids as $id) {
					$descriptions = $this->model_extension_module_dockercart_blog_post->getPostDescriptions($id);
					$description = $descriptions[$language_id] ?? ($descriptions ? reset($descriptions) : []);

					$items[] = ['id' => $id, 'name' => strip_tags(html_entity_decode((string)($description['title'] ?? ''), ENT_QUOTES, 'UTF-8'))];
				}

				break;

			case 'product':
			default:
				$this->load->model('catalog/product');
				$products = $this->model_catalog_product->getProductsByIds($ids);

				foreach ($ids as $id) {
					if (!isset($products[$id])) {
						continue;
					}

					$items[] = [
						'id'    => $id,
						'name'  => html_entity_decode((string)$products[$id]['name'], ENT_QUOTES, 'UTF-8'),
						'model' => (string)($products[$id]['model'] ?? ''),
					];
				}

				break;
		}

		foreach ($items as &$item) {
			$item['url'] = $this->linkSeoUrl($type, (string)$item['id'], $language_id);
		}

		unset($item);

		return $items;
	}

	/**
	 * Storefront URL shown for an entity in the builder.
	 *
	 * The block keeps the canonical route (so the catalog can apply the SEO
	 * keyword of the visitor's language at render time); this absolute URL is
	 * only what the operator sees after picking an entity.
	 */
	private function linkSeoUrl(string $type, string $value, int $language_id): string {
		$routes = [
			'product'      => ['product/product', 'product_id'],
			'category'     => ['product/category', 'path'],
			'manufacturer' => ['product/manufacturer/info', 'manufacturer_id'],
			'information'  => ['information/information', 'information_id'],
			'blog'         => ['blog/post', 'blog_post_id'],
			'contact'      => ['information/contact', ''],
		];

		if (!isset($routes[$type])) {
			return '';
		}

		$route = $routes[$type][0];
		$param = $routes[$type][1];
		// Link fields store the banner-style route without index.php; the
		// storefront resolver applies the SEO keyword at render time.
		$fallback = $param !== ''
			? 'route=' . $route . '&' . $param . '=' . rawurlencode($value)
			: 'route=' . $route;

		if (!$this->config->get('config_seo_url')) {
			return $fallback;
		}

		$keyword = $this->linkSeoKeyword($type, $value, $language_id);

		if ($keyword === '') {
			return $fallback;
		}

		$prefix = $this->linkLanguagePrefix($language_id);

		// Show the SEO path only: the domain/port belong to the storefront
		// request, not to the saved block or the properties panel.
		return '/' . $prefix . $keyword;
	}

	/**
	 * Builds a keyword from the same oc_seo_url / blog_seo_url rows the
	 * storefront rewriter uses. A category path needs one keyword per segment.
	 */
	private function linkSeoKeyword(string $type, string $value, int $language_id): string {
		// The built-in contact form is a route, not an entity: its keyword is
		// registered for the query "information/contact" in oc_seo_url.
		if ($type === 'contact') {
			$this->load->model('design/seo_url');
			$urls = $this->model_design_seo_url->getSeoUrlsByQuery('information/contact', $language_id);

			return isset($urls[0]['keyword']) ? (string)$urls[0]['keyword'] : '';
		}

		$ids = array_filter(array_map('intval', explode('_', $value)));

		if (!$ids) {
			return '';
		}

		$keywords = [];

		foreach ($ids as $id) {
			switch ($type) {
				case 'product':
					$this->load->model('catalog/product');
					$urls = $this->model_catalog_product->getProductSeoUrls($id);
					break;

				case 'category':
					$this->load->model('catalog/category');
					$urls = $this->model_catalog_category->getCategorySeoUrls($id);
					break;

				case 'manufacturer':
					$this->load->model('catalog/manufacturer');
					$urls = $this->model_catalog_manufacturer->getManufacturerSeoUrls($id);
					break;

				case 'information':
					$this->load->model('catalog/information');
					$urls = $this->model_catalog_information->getInformationSeoUrls($id);
					break;

				case 'blog':
					$this->load->model('extension/module/dockercart_blog_post');
					$urls = $this->model_extension_module_dockercart_blog_post->getPostSeoUrls($id);
					break;

				default:
					return '';
			}

			$keyword = isset($urls[0][$language_id]) ? (string)$urls[0][$language_id] : '';

			if ($keyword === '') {
				return '';
			}

			$keywords[] = $keyword;
		}

		return implode('/', $keywords);
	}

	private function linkLanguagePrefix(int $language_id): string {
		$default = (string)$this->config->get('config_language');

		$this->load->model('localisation/language');
		$language = $this->model_localisation_language->getLanguage($language_id);
		$code = $language ? (string)$language['code'] : $default;

		return ($code === '' || $code === $default) ? '' : $code . '/';
	}

	public function delete() {
		$this->load->language('design/landing');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('design/landing');

		if (isset($this->request->post['selected']) && $this->validateDelete()) {
			foreach ((array)$this->request->post['selected'] as $landing_id) {
				$landing = $this->model_design_landing->getLanding((int)$landing_id);

				// Owned landings are deleted with their owner (the information
				// page / the blog article), never from here.
				if ($landing && $this->model_design_landing->ownerType($landing) !== '') {
					continue;
				}

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
			$landing = $this->model_design_landing->getLanding((int)$this->request->get['landing_id']);

			if ($landing && $this->model_design_landing->ownerType($landing) !== '') {
				$this->session->data['error_warning'] = $this->language->get('error_owner');
			} else {
				$landing_id = $this->model_design_landing->duplicateLanding((int)$this->request->get['landing_id']);

				if ($landing_id) {
					$this->session->data['success'] = $this->language->get('text_success_copy');
				}
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
			$landing = $this->model_design_landing->getLanding($landing_id);

			if ($landing && $this->model_design_landing->ownerType($landing) !== '') {
				$json['error'] = $this->language->get('error_owner');
			} else {
				$this->model_design_landing->setStatus($landing_id, (bool)$status);

				$json['success'] = $this->language->get('text_success');
				$json['status'] = $status ? 1 : 0;
			}
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
	 * The builder payload arrives as JSON (php://input) and therefore skips
	 * Request::clean(), which html-encodes every classic form POST. The page
	 * display fields are encoded here at the boundary instead: stored encoded,
	 * rendered raw - the same convention the theme templates rely on and the
	 * same rule validateOwner() applies to owned pages. (Block content is NOT
	 * touched: Blocks::normalize*() owns it.)
	 *
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function encodeDisplayPayload(array $payload): array {
		if (isset($payload['name'])) {
			$payload['name'] = $this->encodeDisplayValue((string)$payload['name']);
		}

		$descriptions = isset($payload['descriptions']) && is_array($payload['descriptions']) ? $payload['descriptions'] : [];

		foreach ($descriptions as $language_id => $row) {
			if (!is_array($row)) {
				continue;
			}

			foreach (['meta_title', 'meta_description', 'meta_keyword'] as $key) {
				$descriptions[$language_id][$key] = $this->encodeDisplayValue((string)($row[$key] ?? ''));
			}
		}

		$payload['descriptions'] = $descriptions;

		return $payload;
	}

	private function encodeDisplayValue(string $value): string {
		return trim(htmlspecialchars($value, ENT_COMPAT, 'UTF-8'));
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
	 * Store favicon links for the standalone builder page (same source as
	 * the storefront header: theme master icon, config_icon fallback).
	 *
	 * @return array<int, array<string, string>>
	 */
	private function faviconLinks(): array {
		$this->load->model('tool/image');

		$source = (string)$this->config->get('dockercart_theme_favicon_master');

		if ($source === '') {
			$source = (string)$this->config->get('theme_dockercart_favicon_master');
		}

		if ($source === '' || !is_file(DIR_IMAGE . $source)) {
			$source = (string)$this->config->get('config_icon');
		}

		$links = [];

		if ($source === '' || !is_file(DIR_IMAGE . $source)) {
			return $links;
		}

		foreach ([16, 32] as $size) {
			$href = $this->model_tool_image->resize($source, $size, $size);

			if (is_string($href) && $href !== '') {
				$links[] = [
					'rel'   => 'icon',
					'type'  => 'image/png',
					'sizes' => $size . 'x' . $size,
					'href'  => $href,
				];
			}
		}

		$apple = $this->model_tool_image->resize($source, 120, 120);

		if (is_string($apple) && $apple !== '') {
			$links[] = [
				'rel'   => 'apple-touch-icon',
				'type'  => 'image/png',
				'sizes' => '120x120',
				'href'  => $apple,
			];
		}

		return $links;
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
			'text_site_settings',
			'text_site_settings_hint',
			'text_site_style_saved',
			'button_save_site_style',
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
			'text_content',
			'text_style',
			'text_advanced',
			'text_shortcuts',
			'text_empty_hint',
			'text_hidden_block',
			'text_hidden_device',
			'text_search_blocks',
			'text_section_settings',
			'text_navigator',
			'text_column',
			'text_drop_here',
			'text_confirm_unsaved',
			'text_no_banners',
			'text_success',
			'text_choose_image',
			'text_clear',
			'text_add_row',
			'text_pick_products',
			'text_pick_category',
			'text_pick_banner',
			'text_link_custom',
			'text_link_product',
			'text_link_category',
			'text_link_manufacturer',
			'text_link_information',
			'text_link_blog',
			'text_link_contact',
			'text_link_search',
			'text_error_save',
			'text_conflict',
			'text_back',
			'text_status_draft',
			'text_status_published',
			'text_created',
			'text_copy',
			'text_owner_information',
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

		foreach (Dockercart\Landing\Design::labelKeys() as $key) {
			$keys[] = $key;
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
