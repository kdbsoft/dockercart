<?php
class ControllerCheckoutSuccess extends Controller {
	public function index() {
		$this->load->language('checkout/success');

		// Preserve order_id (use order_id or last_order_id) and keep last_order_id so it survives page refresh
		$order_id = null;
		if (isset($this->session->data['order_id'])) {
			$order_id = $this->session->data['order_id'];
			// store last_order_id so the success page can be reloaded and still show the number
			$this->session->data['last_order_id'] = $order_id;
			$this->cart->clear();

			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);
			unset($this->session->data['guest']);
			unset($this->session->data['comment']);
			unset($this->session->data['order_id']);
			unset($this->session->data['coupon']);
			unset($this->session->data['reward']);
			unset($this->session->data['voucher']);
			unset($this->session->data['vouchers']);
			unset($this->session->data['totals']);
		} elseif (isset($this->session->data['last_order_id'])) {
			// If the user reloads the success page, use last_order_id to keep showing the number
			$order_id = $this->session->data['last_order_id'];
		}

		// Build heading title and document title, using localized format when available
		if ($order_id) {
			// prefer a language string with placeholder if present
			$heading_format = $this->language->get('heading_title_order');
			if ($heading_format) {
				$data['heading_title'] = sprintf($heading_format, $order_id);
			} else {
				$data['heading_title'] = $this->language->get('heading_title') . ' №' . $order_id;
			}
		} else {
			$data['heading_title'] = $this->language->get('heading_title');
		}

		$this->document->setTitle($data['heading_title']);

		// Expose order_id to the view in case templates want to use it
		$data['order_id'] = $order_id;

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_basket'),
			'href' => $this->url->link('checkout/cart')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_checkout'),
			'href' => $this->url->link('checkout/checkout', '', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_success'),
			'href' => $this->url->link('checkout/success')
		);

		if ($this->customer->isLogged()) {
			$data['text_message'] = sprintf($this->language->get('text_customer'), $this->url->link('account/account', '', true), $this->url->link('account/order', '', true), $this->url->link('account/download', '', true), $this->url->link('information/contact'));
		} else {
			$data['text_message'] = sprintf($this->language->get('text_guest'), $this->url->link('information/contact'));
		}

		$data['continue'] = '/';

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('common/success', $data));
	}
}