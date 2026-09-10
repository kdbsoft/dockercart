<?php
class ControllerExtensionTotalVoucher extends Controller {
	private $error = array();

	public function index() {
		// Settings form removed: Order Totals are fully managed on the
		// DockerCart Checkout page (extension/module/dockercart_checkout).
		$this->response->redirect($this->url->link('extension/module/dockercart_checkout', 'user_token=' . $this->session->data['user_token'], true));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/total/voucher')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	public function install() {
		// Register the event triggers
		$this->load->model('setting/event');

		$this->model_setting_event->addEvent('voucher', 'catalog/model/checkout/order/addOrderHistory/after', 'extension/total/voucher/send');
	}

	public function uninstall() {
		// delete the event triggers
		$this->load->model('setting/event');

		$this->model_setting_event->deleteEventByCode('voucher');
	}
}
