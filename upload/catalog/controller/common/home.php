<?php
class ControllerCommonHome extends Controller {
	public function index() {
		// A landing page may be assigned as the store home page
		// (Design → Landing Pages → Page settings → "Use as store home page").
		$landing_id = (int)$this->config->get('config_landing_home_id');

		if ($landing_id > 0) {
			$this->load->model('design/landing');

			$landing = $this->model_design_landing->getLanding($landing_id);

			if ($landing && $this->model_design_landing->isLive($landing)) {
				$this->request->get['landing_id'] = $landing_id;
				$this->response->setOutput($this->load->controller('landing/page'));

				return;
			}
		}

		$this->document->setTitle($this->config->get('config_meta_title'));
		$this->document->setDescription($this->config->get('config_meta_description'));
		$this->document->setKeywords($this->config->get('config_meta_keyword'));

		if (isset($this->request->get['route'])) {
			$this->document->addLink($this->config->get('config_url'), 'canonical');
		}

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('common/home', $data));
	}
}
