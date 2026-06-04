<?php
class ControllerExtensionModuleDockercartSeoDescription extends Controller {
	public function index($setting) {
		if (isset($setting['module_description'][$this->config->get('config_language_id')])) {
			$this->load->language('extension/module/dockercart_seo_description');

			$data['description'] = html_entity_decode($setting['module_description'][$this->config->get('config_language_id')]['description'], ENT_QUOTES, 'UTF-8');

			$data['text_read_all'] = $this->language->get('text_read_all');
			$data['text_collapse'] = $this->language->get('text_collapse');

			return $this->load->view('extension/module/dockercart_seo_description', $data);
		}
	}
}
