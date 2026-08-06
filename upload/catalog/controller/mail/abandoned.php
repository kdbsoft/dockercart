<?php
/**
 * Abandoned cart reminder mail
 * Triggered by the abandoned cart cleanup worker (upload/bin/dockercart_abandoned_cart_cleanup.php).
 */
class ControllerMailAbandoned extends Controller {
	public function index(&$route, &$args, &$output) {
		if (empty($args[0]) || empty($args[0]['email'])) {
			return;
		}

		$cart = $args[0];

		$this->load->language('mail/abandoned');

		$data['store_name'] = html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8');
		$data['store_url'] = $this->config->get('config_url');
		$data['logo'] = $this->config->get('config_logo');

		$data['text_greeting'] = sprintf($this->language->get('text_greeting'), $data['store_name']);
		$data['text_items_heading'] = $this->language->get('text_items_heading');
		$data['text_restore'] = $this->language->get('text_restore');
		$data['text_expires'] = $this->language->get('text_expires');
		$data['text_thanks'] = $this->language->get('text_thanks');
		$data['text_coupon_heading'] = $this->language->get('text_coupon_heading');
		$data['text_coupon_hint'] = $this->language->get('text_coupon_hint');

		$data['restore_url'] = $cart['restore_url'];

		// Coupon from the reminder wave (empty when the wave has no discount)
		$data['coupon_code'] = !empty($cart['coupon_code']) ? $cart['coupon_code'] : '';
		$data['discount'] = !empty($cart['discount']) ? (int)$cart['discount'] : 0;

		$data['items'] = array();

		if (!empty($cart['items']) && is_array($cart['items'])) {
			foreach ($cart['items'] as $item) {
				$data['items'][] = array(
					'name'     => $item['name'],
					'quantity' => $item['quantity'],
					'total'    => $item['total']
				);
			}
		}

		$mail = new Mail($this->config->get('config_mail_engine'));
		$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
		$mail->smtp_username = $this->config->get('config_mail_smtp_username');
		$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
		$mail->smtp_port = $this->config->get('config_mail_smtp_port');
		$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');
		$mail->smtp_auth_method = $this->config->get('config_mail_smtp_auth_method');
		$mail->smtp_oauth_token = $this->config->get('config_mail_smtp_oauth_token');
		$mail->smtp_oauth_refresh_token = $this->config->get('config_mail_smtp_oauth_refresh_token');
		$mail->smtp_oauth_client_id = $this->config->get('config_mail_smtp_oauth_client_id');
		$mail->smtp_oauth_client_secret = $this->config->get('config_mail_smtp_oauth_client_secret');

		$mail->setTo($cart['email']);
		$mail->setFrom($this->config->get('config_email'));
		$mail->setSender($data['store_name']);
		$mail->setSubject(sprintf($this->language->get('text_subject'), $data['store_name']));
		$mail->setHtml($this->load->view('mail/abandoned', $data));
		$mail->on_token_refresh = function ($token) {
			$this->db->query("UPDATE " . DB_PREFIX . "setting SET `value` = '" . $this->db->escape($token) . "' WHERE `key` = 'config_mail_smtp_oauth_token' AND `store_id` = '" . (int)$this->config->get('config_store_id') . "'");
		};

		$mail->send();
	}
}
