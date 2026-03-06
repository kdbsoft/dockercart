<?php
class ControllerExtensionModuleCarousel extends Controller {
	public function index($setting) {
		static $module = 0;

		$this->load->language('extension/module/carousel');
		$this->load->model('design/banner');

		$data['text_brands_we_carry'] = $this->language->get('text_brands_we_carry');
		$this->load->model('tool/image');



		$data['banners'] = array();

		$results = $this->model_design_banner->getBanner($setting['banner_id']);



		foreach ($results as $result) {
			// Use external URLs directly or serve original local images (no resize)
			if (filter_var($result['image'], FILTER_VALIDATE_URL)) {
				$image_landscape = $result['image'];
			} elseif (is_file(DIR_IMAGE . $result['image'])) {
				$image_landscape = HTTP_SERVER . 'image/' . ltrim($result['image'], '/');
			} else {
				$image_landscape = '';
			}

			// Process portrait image
			if (!empty($result['image_portrait'])) {
				if (filter_var($result['image_portrait'], FILTER_VALIDATE_URL)) {
					$image_portrait = $result['image_portrait'];
				} elseif (is_file(DIR_IMAGE . $result['image_portrait'])) {
					$image_portrait = HTTP_SERVER . 'image/' . ltrim($result['image_portrait'], '/');
				} else {
					$image_portrait = '';
				}
			} else {
				$image_portrait = '';
			}

			$data['banners'][] = array(
				'title'          => $result['title'],
				'link'           => $result['link'],
				'image'          => $image_landscape,
				'image_portrait' => $image_portrait
			);
		}

		$data['module'] = $module++;

		return $this->load->view('extension/module/carousel', $data);
	}
}