<?php
class ControllerCommonAccountMenu extends Controller {
	public function index() {
		$this->load->language('account/account');

		$route = $this->request->get['route'] ?? '';

		// Customer info
		$this->load->model('account/customer');
		$customer_info = $this->model_account_customer->getCustomer($this->customer->getId());

		$data['customer_name'] = $customer_info ? trim($customer_info['firstname'] . ' ' . $customer_info['lastname']) : '';
		$data['customer_telephone'] = $customer_info ? $customer_info['telephone'] : '';
		$data['account_href'] = $this->url->link('account/account', '', true);

		$data['menu_items'] = array(
			array(
				'heading' => $this->language->get('text_my_account'),
				'children' => array(
					'account/edit' => array(
						'text' => $this->language->get('text_edit'),
						'href' => $this->url->link('account/edit', '', true),
						'icon' => 'user',
					),
					'account/password' => array(
						'text' => $this->language->get('text_password'),
						'href' => $this->url->link('account/password', '', true),
						'icon' => 'lock',
					),
					'account/address' => array(
						'text' => $this->language->get('text_address'),
						'href' => $this->url->link('account/address', '', true),
						'icon' => 'map-pin',
					),
					'account/wishlist' => array(
						'text' => $this->language->get('text_wishlist'),
						'href' => $this->url->link('account/wishlist', '', true),
						'icon' => 'heart',
					),
					'account/viewed' => array(
						'text' => $this->language->get('text_viewed'),
						'href' => $this->url->link('account/viewed', '', true),
						'icon' => 'history',
					),
				'account/newsletter' => array(
					'text' => $this->language->get('text_newsletter'),
					'href' => $this->url->link('account/newsletter', '', true),
					'icon' => 'mail',
				),
				'extension/module/dockercart_gdpr/account' => array(
					'text' => 'Privacy Settings',
					'href' => $this->url->link('extension/module/dockercart_gdpr/account', '', true),
					'icon' => 'shield',
				),
			),
		),
		array(
				'heading' => $this->language->get('text_my_orders'),
				'children' => array(
					'account/order' => array(
						'text' => $this->language->get('text_order'),
						'href' => $this->url->link('account/order', '', true),
						'icon' => 'shopping-bag',
					),
					'account/download' => array(
						'text' => $this->language->get('text_download'),
						'href' => $this->url->link('account/download', '', true),
						'icon' => 'download',
						'status' => (int)$this->config->get('config_account_download_status'),
					),
					'account/reward' => array(
						'text' => $this->language->get('text_reward'),
						'href' => $this->url->link('account/reward', '', true),
						'icon' => 'star',
						'status' => (int)$this->config->get('total_reward_status'),
					),
					'account/return' => array(
						'text' => $this->language->get('text_return'),
						'href' => $this->url->link('account/return', '', true),
						'icon' => 'rotate-ccw',
					),
					'account/transaction' => array(
						'text' => $this->language->get('text_transaction'),
						'href' => $this->url->link('account/transaction', '', true),
						'icon' => 'receipt',
					),
				),
			),
		);

		if ($this->config->get('config_affiliate_status')) {
			$this->load->model('account/customer');
			$affiliate_info = $this->model_account_customer->getAffiliate($this->customer->getId());

			if (!$affiliate_info) {
				$data['menu_items'][0]['children']['account/affiliate'] = array(
					'text' => $this->language->get('text_affiliate_add'),
					'href' => $this->url->link('account/affiliate/add', '', true),
					'icon' => 'users',
				);
			} else {
				$data['menu_items'][0]['children']['account/affiliate'] = array(
					'text' => $this->language->get('text_affiliate_edit'),
					'href' => $this->url->link('account/affiliate/edit', '', true),
					'icon' => 'users',
				);
				$data['menu_items'][0]['children']['account/tracking'] = array(
					'text' => $this->language->get('text_tracking'),
					'href' => $this->url->link('account/tracking', '', true),
					'icon' => 'link',
				);
			}
		}

		// Determine current route prefix for matching (account/* matches any sub-page)
		$current_route = '';
		if (preg_match('#^account/([a-z_]+)#', $route, $m)) {
			$current_route = 'account/' . $m[1];
		}
		$data['current_route'] = $current_route;

		return $this->load->view('common/account_menu', $data);
	}
}
