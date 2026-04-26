<?php
class ControllerCommonFooter extends Controller {
	public function index() {
		$this->load->language('common/footer');

		if ($this->user->isLogged() && isset($this->request->get['user_token']) && ($this->request->get['user_token'] == $this->session->data['user_token'])) {
			$display_version = defined('DOCKERCART_VERSION') ? DOCKERCART_VERSION : VERSION;
			$data['text_version'] = sprintf($this->language->get('text_version'), $display_version);
		} else {
			$data['text_version'] = '';
		}

		return $this->load->view('common/footer', $data);
	}
}
