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

		$data['title'] = $this->document->getTitle();

		$data['base'] = $server;
		$data['description'] = $this->document->getDescription();
		$data['keywords'] = $this->document->getKeywords();
		$data['links'] = $this->document->getLinks();
		$data['styles'] = $this->document->getStyles();
		$data['scripts'] = $this->document->getScripts('header');
		$data['lang'] = $this->language->get('code');
		$data['direction'] = $this->language->get('direction');

		$data['name'] = $this->config->get('config_name');

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

		$data['text_logged'] = sprintf($this->language->get('text_logged'), $this->url->link('account/account', '', true), $this->customer->getFirstName(), $this->url->link('account/logout', '', true));
		
		$data['home'] = '/';
		$data['schema_organization_url'] = $this->url->link('common/home');
		$data['schema_website_url'] = $this->url->link('common/home');

		$schema_search_target = $this->url->link('product/search', 'search={search_term_string}');
		$schema_search_target = str_replace(array('%7B', '%7D', '&amp;'), array('{', '}', '&'), $schema_search_target);
		$data['schema_search_target'] = $schema_search_target;

		$data['wishlist'] = $this->url->link('account/wishlist', '', true);
		$data['compare'] = $this->url->link('product/compare');
		$data['logged'] = $this->customer->isLogged();
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

		// Mobile categories for slide-in menu (simple two-level tree)
		$this->load->model('catalog/category');
		$this->load->model('catalog/product');
		$data['mobile_categories'] = array();

		$top_categories = $this->model_catalog_category->getCategories(0);
		foreach ($top_categories as $category) {
			if ($category['top']) {
				$children_data = array();
				$children = $this->model_catalog_category->getCategories((int)$category['category_id']);
				foreach ($children as $child) {
					$filter_data = array('filter_category_id' => (int)$child['category_id'], 'filter_sub_category' => true);
					$children_data[] = array(
						'name' => $child['name'] . ($this->config->get('config_product_count') ? ' (' . $this->model_catalog_product->getTotalProducts($filter_data) . ')' : ''),
						'href' => $this->url->link('product/category', 'path=' . (int)$category['category_id'] . '_' . (int)$child['category_id'])
					);
				}

				$data['mobile_categories'][] = array(
					'category_id' => (int)$category['category_id'],
					'name' => $category['name'],
					'children' => $children_data,
					'href' => $this->url->link('product/category', 'path=' . (int)$category['category_id'])
				);
			}
		}

		return $this->load->view('common/header', $data);
	}
}
