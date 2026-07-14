<?php
class ControllerCommonFooter extends Controller {
	public function index() {
		$this->load->language('common/footer');

		$this->load->model('catalog/information');

		$data['informations'] = array();

		foreach ($this->model_catalog_information->getInformations() as $result) {
			if ($result['bottom']) {
				$data['informations'][] = array(
					'title' => $result['title'],
					'href'  => $this->url->link('information/information', 'information_id=' . $result['information_id'])
				);
			}
		}

		// Add blog link to Information section (DockerCart Blog)
		// Use language string 'text_blog' if available, otherwise default to 'Blog'
		$blog_title = $this->language->get('text_blog') ? $this->language->get('text_blog') : 'Blog';
		$data['informations'][] = array(
			'title' => $blog_title,
			'href'  => $this->url->link('blog/category')
		);

		// Custom footer links from theme settings (multilingual, JSON)
		$footer_links_raw = $this->config->get('dockercart_theme_footer_links');
		$footer_links = $footer_links_raw ? json_decode($footer_links_raw, true) : array();
		if (is_array($footer_links)) {
			$language_id = (int)$this->config->get('config_language_id');
			foreach ($footer_links as $link) {
				$title = '';
				if (isset($link['title']) && is_array($link['title']) && isset($link['title'][$language_id])) {
					$title = trim((string)$link['title'][$language_id]);
				}
				$url = '';
				if (isset($link['url']) && is_array($link['url']) && isset($link['url'][$language_id])) {
					$url = trim((string)$link['url'][$language_id]);
				}
				if ($title === '' || $url === '') {
					if ($title === '' && isset($link['title']) && is_array($link['title'])) {
						foreach ($link['title'] as $t) {
							if ($t) { $title = trim((string)$t); break; }
						}
					}
					if ($url === '' && isset($link['url']) && is_array($link['url'])) {
						foreach ($link['url'] as $u) {
							if ($u) { $url = trim((string)$u); break; }
						}
					}
				}
				if ($title !== '' && $url !== '') {
					$data['informations'][] = array(
						'title' => $title,
						'href'  => $url
					);
				}
			}
		}

		$data['contact'] = $this->url->link('information/contact');
		$data['return'] = $this->url->link('account/return/add', '', true);
		$data['sitemap'] = $this->url->link('information/sitemap');
		$data['tracking'] = $this->url->link('information/tracking');
		$data['manufacturer'] = $this->url->link('product/manufacturer');
		$data['voucher'] = $this->url->link('account/voucher', '', true);
		$data['affiliate'] = $this->config->get('config_affiliate_status') ? $this->url->link('affiliate/login', '', true) : '';
		$data['special'] = $this->url->link('product/special');
		$data['account'] = $this->url->link('account/account', '', true);
		$data['order'] = $this->url->link('account/order', '', true);
		$data['wishlist'] = $this->url->link('account/wishlist', '', true);
		$data['viewed'] = $this->url->link('account/viewed', '', true);
		$data['newsletter'] = $this->url->link('account/newsletter', '', true);
		$data['compare'] = $this->url->link('product/compare');

		$data['powered'] = sprintf($this->language->get('text_powered'), $this->config->get('config_name'), date('Y', time()));

		$data['store_name']      = $this->config->get('config_name');
		$data['name']            = $this->config->get('config_name');
		$data['home']            = '/';
		$data['store_address']   = $this->config->get('config_address');
		$data['store_telephone'] = $this->config->get('config_telephone');
		$data['store_fax']       = $this->config->get('config_fax');
		$data['store_email']     = $this->config->get('config_email');
		$data['store_geocode']   = $this->config->get('config_geocode');

		$this->load->model('catalog/manufacturer');
		$data['manufacturers'] = array();
		foreach ($this->model_catalog_manufacturer->getManufacturers() as $result) {
			$data['manufacturers'][] = array(
				'name' => $result['name'],
				'href' => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $result['manufacturer_id'])
			);
		}

		$this->load->model('catalog/category');
		$data['categories'] = array();
		$categories = $this->model_catalog_category->getCategories(0);
		foreach ($categories as $category) {
			$data['categories'][] = array(
				'name' => $category['name'],
				'href' => $this->url->link('product/category', 'path=' . $category['category_id'])
			);
		}

		// If there are more than 7 top-level categories, we won't render the full list in footer.
		// Instead the template will show links to the catalog listing and manufacturers.
		$data['show_categories_list'] = count($data['categories']) <= 7;

		// Catalog column title (for footer)
		$data['text_catalog'] = $this->language->get('text_catalog');

		// Add new arrivals / sale labels and links (used in footer)
		$data['text_new_arrivals'] = $this->language->get('text_new_arrivals');
		$data['text_sale'] = $this->language->get('text_sale');
		// Use url->link so language prefix / SEO url rules are applied
		$data['new_arrivals'] = $this->url->link('product/new_arrivals');

		// Footer labels
		$data['text_manufacturer'] = $this->language->get('text_manufacturer');

		// Compare label for footer links
		$data['text_compare'] = $this->language->get('text_compare');

		// Use url->link to generate the categories listing URL so language prefixes / SEO URLs are applied
		// This uses the custom listing route `product/categories` which maps to the SEO keyword 'product-categories'
		$data['categories_link'] = $this->url->link('product/categories');

		$server = ($this->request->server['HTTPS'] ?? '') ? HTTPS_SERVER : HTTP_SERVER;
		if (is_file(DIR_IMAGE . $this->config->get('config_logo'))) {
			$data['logo'] = $server . 'image/' . $this->config->get('config_logo');
		} else {
			$data['logo'] = '';
		}

		// Dark logo (for footer / dark backgrounds) — set via DockerCart Theme Settings module
		$logo_dark_path = $this->config->get('dockercart_theme_logo_dark');
		if ($logo_dark_path && is_file(DIR_IMAGE . $logo_dark_path)) {
			$data['logo_dark'] = $server . 'image/' . $logo_dark_path;
		} else {
			$data['logo_dark'] = '';
		}

		$data['social_links'] = array();
		for ($i = 1; $i <= 10; $i++) {
			$image = (string)$this->config->get('dockercart_theme_social_' . $i . '_image');
			$link  = trim((string)$this->config->get('dockercart_theme_social_' . $i . '_link'));
			$image_path = ltrim($image, '/');

			if ($image_path !== '') {
				$data['social_links'][] = array(
					'image' => $server . 'image/' . $image_path,
					'link'  => $link,
				);
			}
		}

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

		$data['payment_icons'] = array();
		for ($i = 1; $i <= 10; $i++) {
			$image = (string)$this->config->get('dockercart_theme_payment_' . $i . '_image');
			$link = (string)$this->config->get('dockercart_theme_payment_' . $i . '_link');

			if ($image && is_file(DIR_IMAGE . $image)) {
				$data['payment_icons'][] = array(
					'image' => $server . 'image/' . $image,
					'link' => $link,
				);
			}
		}

		$data['scripts'] = $this->document->getScripts('footer');
		$data['styles'] = $this->document->getStyles('footer');
		$data['dockercart_version'] = DOCKERCART_VERSION;

		$data['custom_js'] = (string)$this->config->get('dockercart_theme_custom_js');


		return $this->load->view('common/footer', $data);
	}
}
