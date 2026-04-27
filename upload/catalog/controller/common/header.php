<?php
class ControllerCommonHeader extends Controller {
	public function index() {
		// Analytics
		$this->load->model('setting/extension');

		$data['analytics'] = array();

		$analytics = $this->model_setting_extension->getExtensions('analytics');

		foreach ($analytics as $analytic) {
			if ($this->config->get('analytics_' . $analytic['code'] . '_status')) {
				$data['analytics'][] = $this->load->controller('extension/analytics/' . $analytic['code'], $this->config->get('analytics_' . $analytic['code'] . '_status'));
			}
		}

		if ($this->request->server['HTTPS']) {
			$server = $this->config->get('config_ssl');
		} else {
			$server = $this->config->get('config_url');
		}

		$data['favicon_links'] = array();

		$favicon_master = (string)$this->config->get('dockercart_theme_favicon_master');

		if ($favicon_master === '') {
			// Backward compatibility for previously stored value in theme settings.
			$favicon_master = (string)$this->config->get('theme_dockercart_favicon_master');
		}
		$favicon_source = '';

		if ($favicon_master && is_file(DIR_IMAGE . $favicon_master)) {
			$favicon_source = $favicon_master;
		} else {
			$config_icon = (string)$this->config->get('config_icon');

			if ($config_icon && is_file(DIR_IMAGE . $config_icon)) {
				$favicon_source = $config_icon;
			}
		}

		if ($favicon_source) {
			$this->load->model('tool/image');

			$favicon_sizes = array(16, 32, 48, 64, 96, 128);

			foreach ($favicon_sizes as $size) {
				$favicon_href = $this->model_tool_image->resize($favicon_source, $size, $size, 'cover');

				if ($favicon_href) {
					$data['favicon_links'][] = array(
						'rel' => 'icon',
						'type' => 'image/png',
						'sizes' => $size . 'x' . $size,
						'href' => $favicon_href
					);
				}
			}

			$apple_touch = $this->model_tool_image->resize($favicon_source, 120, 120, 'cover');

			if ($apple_touch) {
				$data['favicon_links'][] = array(
					'rel' => 'apple-touch-icon',
					'type' => 'image/png',
					'sizes' => '120x120',
					'href' => $apple_touch
				);
			}
		} elseif (is_file(DIR_IMAGE . $this->config->get('config_icon'))) {
			$this->document->addLink($server . 'image/' . $this->config->get('config_icon'), 'icon');
		}

		$data['title'] = $this->decodeHtmlEntities($this->document->getTitle());

		$data['base'] = $server;
		$data['description'] = $this->decodeHtmlEntities($this->document->getDescription());
		$data['keywords'] = $this->decodeHtmlEntities($this->document->getKeywords());
		$data['links'] = $this->document->getLinks();
		$data['styles'] = $this->document->getStyles();
		$data['scripts'] = $this->document->getScripts('header');
		$data['lang'] = $this->language->get('code');
		$data['direction'] = $this->language->get('direction');

		$data['name'] = $this->decodeHtmlEntities($this->config->get('config_name'));

		$logo_light = (string)$this->config->get('dockercart_theme_logo_light');
		if ($logo_light && is_file(DIR_IMAGE . $logo_light)) {
			$data['logo'] = $server . 'image/' . $logo_light;
		} elseif (is_file(DIR_IMAGE . $this->config->get('config_logo'))) {
			$data['logo'] = $server . 'image/' . $this->config->get('config_logo');
		} else {
			$data['logo'] = '';
		}

		$this->load->language('common/header');

		// Localized home text for client-side breadcrumb insertion
		$data['text_home'] = $this->language->get('text_home');

		$data['menu_type'] = (string)$this->config->get('dockercart_theme_menu_type');
		if ($data['menu_type'] !== 'vertical') {
			$data['menu_type'] = 'horizontal';
		}
		$data['text_catalog'] = $this->language->get('text_catalog');

		// UI strings for header mobile menu
		$data['text_new_arrivals'] = $this->language->get('text_new_arrivals');
		$data['text_sale'] = $this->language->get('text_sale');
		// Accent links (language-aware)
		$data['new_arrivals'] = $this->url->link('product/new_arrivals');
		$data['special'] = $this->url->link('product/special');

		// Quick view / product UI (global)
		$data['text_model_prefix'] = $this->language->get('text_model_prefix');
		$data['text_qv_feature_delivery'] = $this->language->get('text_qv_feature_delivery');
		$data['text_qv_feature_warranty'] = $this->language->get('text_qv_feature_warranty');
		$data['text_qv_feature_returns'] = $this->language->get('text_qv_feature_returns');

		$quickview_feature_defaults = array(
			array(
				'icon' => 'truck',
				'title' => '',
				'text' => $this->language->get('text_qv_feature_delivery'),
				'sort_order' => 0
			),
			array(
				'icon' => 'shield-check',
				'title' => '',
				'text' => $this->language->get('text_qv_feature_warranty'),
				'sort_order' => 1
			),
			array(
				'icon' => 'refresh-ccw',
				'title' => '',
				'text' => $this->language->get('text_qv_feature_returns'),
				'sort_order' => 2
			)
		);
		$data['quickview_features'] = $this->resolveThemeFeatures('dockercart_theme_quickview_features', $quickview_feature_defaults);
		$data['quickview_features_json'] = json_encode($data['quickview_features'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

		// Button text for quick view
		$data['button_cart'] = $this->language->get('button_cart');

		// Wishlist
		if ($this->customer->isLogged()) {
			$this->load->model('account/wishlist');

			$data['text_wishlist'] = sprintf($this->language->get('text_wishlist'), $this->model_account_wishlist->getTotalWishlist());
		} else {
			$data['text_wishlist'] = sprintf($this->language->get('text_wishlist'), (isset($this->session->data['wishlist']) ? count($this->session->data['wishlist']) : 0));
		}

		// Compare
		$data['text_compare'] = sprintf($this->language->get('text_compare'), (isset($this->session->data['compare']) ? count($this->session->data['compare']) : 0));
		$data['compare_total'] = isset($this->session->data['compare']) ? count($this->session->data['compare']) : 0;
		$data['compare_product_ids'] = array();

		if (isset($this->session->data['compare']) && is_array($this->session->data['compare'])) {
			$data['compare_product_ids'] = array_values(array_unique(array_map('intval', $this->session->data['compare'])));
		}

		$data['text_logged'] = sprintf($this->language->get('text_logged'), $this->url->link('account/account', '', true), $this->customer->getFirstName(), $this->url->link('account/logout', '', true));
		
		$data['home'] = '/';
		$data['schema_organization_url'] = $this->url->link('common/home');
		$data['schema_website_url'] = $this->url->link('common/home');

		$schema_search_target = $this->url->link('product/search', 'search={search_term_string}');
		$schema_search_target = str_replace(array('%7B', '%7D', '&amp;'), array('{', '}', '&'), $schema_search_target);
		$data['schema_search_target'] = $schema_search_target;

		$data['wishlist'] = $this->url->link('account/wishlist', '', true);
		$data['viewed'] = $this->url->link('account/viewed', '', true);
		$data['compare'] = $this->url->link('product/compare');
		$data['logged'] = $this->customer->isLogged();
		$data['account_download_status'] = (int)$this->config->get('config_account_download_status');
		$data['account'] = $this->url->link('account/account', '', true);
		$data['register'] = $this->url->link('account/register', '', true);
		$data['login'] = $this->url->link('account/login', '', true);
		$data['order'] = $this->url->link('account/order', '', true);
		$data['transaction'] = $this->url->link('account/transaction', '', true);
		$data['download'] = $this->url->link('account/download', '', true);
		$data['logout'] = $this->url->link('account/logout', '', true);
		$data['shopping_cart'] = $this->url->link('checkout/cart');
		$data['checkout'] = $this->url->link('checkout/checkout', '', true);
		$data['contact'] = $this->url->link('information/contact');
		$data['telephone'] = $this->config->get('config_telephone');
		$data['fax'] = $this->config->get('config_fax');
		$data['store_email'] = $this->config->get('config_email');
		$data['store_address'] = $this->config->get('config_address');
		$data['store_geocode'] = $this->config->get('config_geocode');

		// Build social links for schema sameAs
		$data['schema_sameAs'] = array();
		for ($i = 1; $i <= 10; $i++) {
			$link = (string)$this->config->get('dockercart_theme_social_' . $i . '_link');
			if ($link !== '') {
				$data['schema_sameAs'][] = $link;
			}
		}

		// Expose dedicated schema fields
		$data['schema_organization_telephone'] = $data['telephone'];
		$data['schema_organization_email'] = $data['store_email'];
		$data['schema_organization_address'] = $data['store_address'];

		// Build Organization schema JSON to avoid template comma issues
		$schema_org = array(
			'@context' => 'https://schema.org',
			'@type' => 'Organization',
			'name' => $data['name'],
			'url' => $this->url->link('common/home')
		);

		if ($data['logo']) {
			$schema_org['logo'] = $data['logo'];
		}

		if ($data['schema_organization_telephone']) {
			$schema_org['telephone'] = $data['schema_organization_telephone'];
			$schema_org['contactPoint'] = array(array(
				'@type' => 'ContactPoint',
				'telephone' => $data['schema_organization_telephone'],
				'contactType' => 'customer service'
			));
		}

		if ($data['schema_organization_email']) {
			$schema_org['email'] = $data['schema_organization_email'];
		}

		if ($data['schema_organization_address']) {
			$schema_org['address'] = array(
				'@type' => 'PostalAddress',
				'streetAddress' => preg_replace('/\s+/u', ' ', strip_tags($data['schema_organization_address']))
			);
		}

		if (!empty($data['schema_sameAs'])) {
			$schema_org['sameAs'] = array_values($data['schema_sameAs']);
		}

		$data['schema_organization_json'] = json_encode($schema_org, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		$open_i18n = $this->config->get('config_open_i18n');
		$data['open_hours'] = '';

		if (is_array($open_i18n) && $open_i18n) {
			$language_id = (int)$this->config->get('config_language_id');

			if (!empty($open_i18n[$language_id])) {
				$data['open_hours'] = $open_i18n[$language_id];
			} else {
				$first_value = reset($open_i18n);

				if (!empty($first_value)) {
					$data['open_hours'] = $first_value;
				}
			}
		} elseif (is_string($open_i18n) && $open_i18n !== '') {
			$data['open_hours'] = $open_i18n;
		}
		
		$data['language'] = $this->load->controller('common/language');
		$data['currency'] = $this->load->controller('common/currency');
		$data['search'] = $this->load->controller('common/search');
		$data['cart'] = $this->load->controller('common/cart');
		$data['menu'] = $this->load->controller('common/menu');

		// Top information links (shown in header navigation bar)
		$this->load->model('catalog/information');
		$data['top_informations'] = array();
		foreach ($this->model_catalog_information->getInformations() as $result) {
			if (!empty($result['top'])) {
				$data['top_informations'][] = array(
					'title' => $result['title'],
					'href'  => $this->url->link('information/information', 'information_id=' . $result['information_id'])
				);
			}
		}
		
		// Add Contacts link after information links
		$data['top_informations'][] = array(
			'title' => $this->language->get('text_contact'),
			'href'  => $this->url->link('information/contact')
		);

		// Mobile categories for slide-in menu (simple two-level tree)
		$this->load->model('catalog/category');
		$data['mobile_categories'] = array();
		$show_product_count = (bool)$this->config->get('config_product_count');
		$mobile_menu_cache_key = 'category.mobile.tree.v1.'
			. (int)$this->config->get('config_store_id')
			. '.' . (int)$this->config->get('config_language_id')
			. '.' . (int)$show_product_count;

		$mobile_categories = $this->cache->get($mobile_menu_cache_key);

		if (!is_array($mobile_categories)) {
			$mobile_categories = array();
			$top_categories = $this->model_catalog_category->getCategories(0);

			$top_with_children = array();
			$child_category_ids = array();

			foreach ($top_categories as $category) {
				if (empty($category['top'])) {
					continue;
				}

				$children = $this->model_catalog_category->getCategories((int)$category['category_id']);

				$top_with_children[] = array(
					'category' => $category,
					'children' => $children
				);

				foreach ($children as $child) {
					$child_category_ids[] = (int)$child['category_id'];
				}
			}

			$child_totals = array();

			if ($show_product_count && $child_category_ids) {
				$child_totals = $this->getCategoryProductTotals($child_category_ids);
			}

			foreach ($top_with_children as $item) {
				$category = $item['category'];
				$children_data = array();

				foreach ($item['children'] as $child) {
					$child_id = (int)$child['category_id'];
					$total = isset($child_totals[$child_id]) ? (int)$child_totals[$child_id] : 0;

					$children_data[] = array(
						'name' => $child['name'] . ($show_product_count ? ' (' . $total . ')' : ''),
						'href' => $this->url->link('product/category', 'path=' . (int)$category['category_id'] . '_' . $child_id)
					);
				}

				$mobile_categories[] = array(
					'category_id' => (int)$category['category_id'],
					'name' => $category['name'],
					'children' => $children_data,
					'href' => $this->url->link('product/category', 'path=' . (int)$category['category_id'])
				);
			}

			$this->cache->set($mobile_menu_cache_key, $mobile_categories, 900);
		}

		$data['mobile_categories'] = $mobile_categories;

		return $this->load->view('common/header', $data);
	}

	private function getCategoryProductTotals(array $category_ids) {
		static $request_cache = array();

		$store_id = (int)$this->config->get('config_store_id');
		$totals = array();
		$missing = array();

		$category_ids = array_values(array_unique(array_map('intval', $category_ids)));

		foreach ($category_ids as $category_id) {
			if ($category_id <= 0) {
				continue;
			}

			$cache_key = 'category.mobile.count.' . $store_id . '.' . $category_id;

			if (isset($request_cache[$cache_key])) {
				$totals[$category_id] = (int)$request_cache[$cache_key];
				continue;
			}

			$cached_total = $this->cache->get($cache_key);

			if ($cached_total !== false && $cached_total !== null) {
				$request_cache[$cache_key] = (int)$cached_total;
				$totals[$category_id] = (int)$cached_total;
			} else {
				$missing[$category_id] = $cache_key;
			}
		}

		if ($missing) {
			$ids = array_keys($missing);
			$db_totals = array_fill_keys($ids, 0);

			$query = $this->db->query("SELECT cp.path_id AS category_id, COUNT(DISTINCT p.product_id) AS total
				FROM " . DB_PREFIX . "category_path cp
				LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (cp.category_id = p2c.category_id)
				LEFT JOIN " . DB_PREFIX . "product p ON (p2c.product_id = p.product_id)
				LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)
				WHERE cp.path_id IN (" . implode(',', array_map('intval', $ids)) . ")
				AND p.status = '1'
				AND p.date_available <= NOW()
				AND p2s.store_id = '" . $store_id . "'
				GROUP BY cp.path_id");

			foreach ($query->rows as $row) {
				$category_id = (int)$row['category_id'];

				if (isset($db_totals[$category_id])) {
					$db_totals[$category_id] = isset($row['total']) ? (int)$row['total'] : 0;
				}
			}

			foreach ($db_totals as $category_id => $total) {
				$cache_key = $missing[$category_id];
				$this->cache->set($cache_key, (int)$total, 900);
				$request_cache[$cache_key] = (int)$total;
				$totals[$category_id] = (int)$total;
			}
		}

		return $totals;
	}

	private function decodeHtmlEntities($value) {
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
