<?php
class ControllerAccountAccount extends Controller {
	public function index() {
		if (!$this->customer->isLogged()) {
			$this->session->data['redirect'] = $this->url->link('account/account', '', true);

			$this->response->redirect($this->url->link('account/login', '', true));
		}

		$this->load->language('account/account');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('account/order');
		$this->load->model('account/wishlist');

		// Dashboard stats
		$data['total_orders'] = $this->model_account_order->getTotalOrders();
		$recent_orders = $this->model_account_order->getOrders(0, 3);

		if ($recent_orders) {
			foreach ($recent_orders as &$order) {
				$order['view'] = $this->url->link('account/order/info', 'order_id=' . $order['order_id'], true);
				$order['products'] = $this->model_account_order->getTotalOrderProductsByOrderId($order['order_id']);
				$order['total'] = $this->currency->format($order['total'], $order['currency_code'], $order['currency_value']);
			}
			unset($order);
		}

		$data['recent_orders'] = $recent_orders;
		$data['total_wishlist'] = $this->model_account_wishlist->getTotalWishlist();

		$this->load->model('account/customer');
		$data['total_reward'] = (int)$this->model_account_customer->getRewardTotal($this->customer->getId());
		$data['total_spent'] = (float)$this->model_account_customer->getTransactionTotal($this->customer->getId());

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_account'),
			'href' => $this->url->link('account/account', '', true)
		);

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		} 
		
		$data['edit'] = $this->url->link('account/edit', '', true);
		$data['password'] = $this->url->link('account/password', '', true);
		$data['address'] = $this->url->link('account/address', '', true);
		
		$data['credit_cards'] = array();
		
		$files = glob(DIR_APPLICATION . 'controller/extension/credit_card/*.php');
		
		foreach ($files as $file) {
			$code = basename($file, '.php');
			
			if ($this->config->get('payment_' . $code . '_status') && $this->config->get('payment_' . $code . '_card')) {
				$this->load->language('extension/credit_card/' . $code, 'extension');

				$data['credit_cards'][] = array(
					'name' => $this->language->get('extension')->get('heading_title'),
					'href' => $this->url->link('extension/credit_card/' . $code, '', true)
				);
			}
		}
		
		$data['wishlist'] = $this->url->link('account/wishlist');
		$data['viewed'] = $this->url->link('account/viewed', '', true);
		$data['order'] = $this->url->link('account/order', '', true);
		$data['account_download_status'] = (int)$this->config->get('config_account_download_status');
		$data['download'] = $data['account_download_status'] ? $this->url->link('account/download', '', true) : '';
		
		if ($this->config->get('total_reward_status')) {
		$data['reward'] = $this->url->link('account/reward', '', true);
		$data['return'] = $this->url->link('account/return', '', true);
		$data['transaction'] = $this->url->link('account/transaction', '', true);
		$data['newsletter'] = $this->url->link('account/newsletter', '', true);
		}

		$data['affiliate_status'] = (int)$this->config->get('config_affiliate_status');

		if ($data['affiliate_status']) {
			$this->load->model('account/customer');

			$affiliate_info = $this->model_account_customer->getAffiliate($this->customer->getId());

			if (!$affiliate_info) {
				$data['affiliate'] = $this->url->link('account/affiliate/add', '', true);
			} else {
				$data['affiliate'] = $this->url->link('account/affiliate/edit', '', true);
			}

			if ($affiliate_info) {
				$data['tracking'] = $this->url->link('account/tracking', '', true);
			} else {
				$data['tracking'] = '';
			}
		} else {
			$data['affiliate'] = '';
			$data['tracking'] = '';
		}
		
		$data['account_menu'] = $this->load->controller('common/account_menu');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
		
		$this->response->setOutput($this->load->view('account/account', $data));
	}

	public function country() {
		$json = array();

		$this->load->model('localisation/country');

		$country_info = $this->model_localisation_country->getCountry($this->request->get['country_id']);

		if ($country_info) {
			$this->load->model('localisation/zone');

			$json = array(
				'country_id'        => $country_info['country_id'],
				'name'              => $country_info['name'],
				'iso_code_2'        => $country_info['iso_code_2'],
				'iso_code_3'        => $country_info['iso_code_3'],
				'address_format'    => $country_info['address_format'],
				'postcode_required' => $country_info['postcode_required'],
				'zone'              => $this->model_localisation_zone->getZonesByCountryId($this->request->get['country_id']),
				'status'            => $country_info['status']
			);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
