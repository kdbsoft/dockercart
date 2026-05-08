<?php
class ControllerExtensionModuleDockercartBrandCarousel extends Controller {
	public function index($setting) {
		if (!isset($setting['status']) || !(int)$setting['status']) {
			return '';
		}

		$this->load->language('extension/module/dockercart_brand_carousel');
		$this->load->model('catalog/manufacturer');
		$this->load->model('tool/image');

		$limit = isset($setting['limit']) ? (int)$setting['limit'] : 10;

		$results = $this->model_catalog_manufacturer->getManufacturers(array(
			'sort'  => 'sort_order',
			'order' => 'ASC',
			'start' => 0,
			'limit' => $limit
		));

		$data['brands'] = array();

		foreach ($results as $result) {
			if (empty($result['image'])) {
				continue;
			}

			if (is_file(DIR_IMAGE . $result['image'])) {
				$image = HTTP_SERVER . 'image/' . ltrim($result['image'], '/');
			} else {
				$image = '';
			}

			if ($image) {
				$data['brands'][] = array(
					'name'  => $result['name'],
					'image' => $image,
					'href'  => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $result['manufacturer_id'])
				);
			}
		}

		if (!$data['brands']) {
			return '';
		}

		$data['text_brands_we_carry'] = $this->language->get('text_brands_we_carry');

		return $this->load->view('extension/module/dockercart_brand_carousel', $data);
	}
}
