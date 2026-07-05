<?php
declare(strict_types=1);

class ControllerExtensionModuleDockercartGallery extends Controller {
	public function index() {
		if (!(int)$this->config->get('module_dockercart_gallery_status')) {
			$this->response->redirect($this->url->link('common/home'));
		}

		if (is_file(DIR_SYSTEM . 'library/dockercart/licensing.php')) {
			require_once DIR_SYSTEM . 'library/dockercart/licensing.php';
			$licensing = new DockercartLicensing($this->registry);

			if (!$licensing->check('dockercart_gallery')) {
				$this->response->redirect($this->url->link('common/home'));

				return;
			}
		}

		$this->load->language('extension/module/dockercart_gallery');
		$this->load->model('extension/module/dockercart_gallery');
		$this->load->model('tool/image');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->document->addStyle('https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css');
		$this->document->addScript('https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js');
		$this->document->addScript('catalog/view/theme/dockercart/javascript/dockercart_gallery.js', 'footer');

		$limit = 12;
		$page = 1;

		$images = $this->model_extension_module_dockercart_gallery->getImages(array(
			'status' => 1,
			'sort' => 'sort_order',
			'order' => 'ASC',
			'start' => ($page - 1) * $limit,
			'limit' => $limit
		));

		$total = $this->model_extension_module_dockercart_gallery->getTotalImages();

		$data['images'] = array();
		foreach ($images as $image) {
			$data['images'][] = array(
				'html' => $this->load->view('extension/module/dockercart_gallery_item', array(
					'thumb' => 'image/' . $image['image'],
					'image' => 'image/' . $image['image']
				))
			);
		}

		$data['has_more'] = $total > count($images);
		$data['total_images'] = $total;
		$data['load_more_url'] = $this->url->link('extension/module/dockercart_gallery/loadmore');
		$data['text_load_more'] = $this->language->get('text_load_more');
		$data['text_no_images'] = $this->language->get('text_no_images');
		$data['text_images_loaded'] = $this->language->get('text_images_loaded');

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/dockercart_gallery')
		);

		$data['heading_title'] = $this->language->get('heading_title');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('extension/module/dockercart_gallery', $data));
	}

	public function loadmore() {
		$this->response->addHeader('Content-Type: application/json');

		if (is_file(DIR_SYSTEM . 'library/dockercart/licensing.php')) {
			require_once DIR_SYSTEM . 'library/dockercart/licensing.php';
			$licensing = new DockercartLicensing($this->registry);

			if (!$licensing->check('dockercart_gallery')) {
				$this->response->setOutput(json_encode(array('html' => '', 'count' => 0, 'total' => 0)));

				return;
			}
		}

		$this->load->language('extension/module/dockercart_gallery');
		$this->load->model('extension/module/dockercart_gallery');
		$this->load->model('tool/image');

		$page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 2;
		if ($page < 2) {
			$page = 2;
		}

		$limit = 12;

		$images = $this->model_extension_module_dockercart_gallery->getImages(array(
			'status' => 1,
			'sort' => 'sort_order',
			'order' => 'ASC',
			'start' => ($page - 1) * $limit,
			'limit' => $limit
		));

		$total = $this->model_extension_module_dockercart_gallery->getTotalImages();

		$html = '';
		foreach ($images as $image) {
			$html .= $this->load->view('extension/module/dockercart_gallery_item', array(
				'thumb' => 'image/' . $image['image'],
				'image' => 'image/' . $image['image']
			));
		}

		$this->response->setOutput(json_encode(array(
			'html' => $html,
			'count' => count($images),
			'total' => $total
		)));
	}

	public function eventFooterBefore(&$route, &$data, &$output) {
		if (!(int)$this->config->get('module_dockercart_gallery_status')) {
			return;
		}

		if (is_file(DIR_SYSTEM . 'library/dockercart/licensing.php')) {
			require_once DIR_SYSTEM . 'library/dockercart/licensing.php';
			$licensing = new DockercartLicensing($this->registry);

			if (!$licensing->check('dockercart_gallery')) {
				return;
			}
		}

		$this->load->language('extension/module/dockercart_gallery');

		if (!isset($data['informations']) || !is_array($data['informations'])) {
			$data['informations'] = array();
		}

		$data['informations'][] = array(
			'title' => $this->language->get('text_gallery'),
			'href'  => $this->url->link('extension/module/dockercart_gallery')
		);
	}

	public function eventHeaderBefore(&$route, &$data, &$output) {
		if (!(int)$this->config->get('module_dockercart_gallery_status')) {
			return;
		}

		if (!(int)$this->config->get('module_dockercart_gallery_show_header')) {
			return;
		}

		if (is_file(DIR_SYSTEM . 'library/dockercart/licensing.php')) {
			require_once DIR_SYSTEM . 'library/dockercart/licensing.php';
			$licensing = new DockercartLicensing($this->registry);

			if (!$licensing->check('dockercart_gallery')) {
				return;
			}
		}

		$this->load->language('extension/module/dockercart_gallery');

		if (!isset($data['top_informations']) || !is_array($data['top_informations'])) {
			$data['top_informations'] = array();
		}

		$data['top_informations'][] = array(
			'title' => $this->language->get('text_gallery'),
			'href'  => $this->url->link('extension/module/dockercart_gallery')
		);
	}
}
