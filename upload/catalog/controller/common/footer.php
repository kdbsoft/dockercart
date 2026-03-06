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

		$data['contact'] = $this->url->link('information/contact');
		$data['return'] = $this->url->link('account/return/add', '', true);
		$data['sitemap'] = $this->url->link('information/sitemap');
		$data['tracking'] = $this->url->link('information/tracking');
		$data['manufacturer'] = $this->url->link('product/manufacturer');
		$data['voucher'] = $this->url->link('account/voucher', '', true);
		$data['affiliate'] = $this->url->link('affiliate/login', '', true);
		$data['special'] = $this->url->link('product/special');
		$data['account'] = $this->url->link('account/account', '', true);
		$data['order'] = $this->url->link('account/order', '', true);
		$data['wishlist'] = $this->url->link('account/wishlist', '', true);
		$data['newsletter'] = $this->url->link('account/newsletter', '', true);
		$data['compare'] = $this->url->link('product/compare');

		$data['powered'] = sprintf($this->language->get('text_powered'), $this->config->get('config_name'), date('Y', time()));

		$data['store_name']      = $this->config->get('config_name');
		$data['name']            = $this->config->get('config_name');
		$data['home']            = '/';
		$data['store_address']   = $this->config->get('config_address');
		$data['store_telephone'] = $this->config->get('config_telephone');
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

		// Catalog column title (for footer)
		$data['text_catalog'] = $this->language->get('text_catalog');

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
			$data['logo_dark'] = $data['logo']; // fall back to main logo
		}

		$data['social_links'] = array();
		for ($i = 1; $i <= 10; $i++) {
			$image = (string)$this->config->get('dockercart_theme_social_' . $i . '_image');
			$link  = (string)$this->config->get('dockercart_theme_social_' . $i . '_link');
			if ($image !== '') {
				$data['social_links'][] = array(
					'image' => $server . 'image/' . $image,
					'link'  => $link,
				);
			}
		}

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

		// Whos Online
		if ($this->config->get('config_customer_online')) {
			$this->load->model('tool/online');

			if (isset($this->request->server['REMOTE_ADDR'])) {
				$ip = $this->request->server['REMOTE_ADDR'];
			} else {
				$ip = '';
			}

			if (isset($this->request->server['HTTP_HOST']) && isset($this->request->server['REQUEST_URI'])) {
				$url = ($this->request->server['HTTPS'] ? 'https://' : 'http://') . $this->request->server['HTTP_HOST'] . $this->request->server['REQUEST_URI'];
			} else {
				$url = '';
			}

			if (isset($this->request->server['HTTP_REFERER'])) {
				$referer = $this->request->server['HTTP_REFERER'];
			} else {
				$referer = '';
			}

			$this->model_tool_online->addOnline($ip, $this->customer->getId(), $url, $referer);
		}

		$data['scripts'] = $this->document->getScripts('footer');
		$data['styles'] = $this->document->getStyles('footer');
		
		return $this->load->view('common/footer', $data);
	}
}
