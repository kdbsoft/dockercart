<?php
class ControllerExtensionTotalShipping extends Controller {
	private $error = array();

	public function index() {
		// Settings form removed: Order Totals are fully managed on the
		// DockerCart Checkout page (extension/module/dockercart_checkout).
		$this->response->redirect($this->url->link('extension/module/dockercart_checkout', 'user_token=' . $this->session->data['user_token'], true));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/total/shipping')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}