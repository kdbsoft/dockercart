<?php
class ControllerMailOrder extends Controller {
	public function index(&$route, &$args, &$output) {
		if (isset($args[0])) {
			$order_id = $args[0];
		} else {
			$order_id = 0;
		}

		if (isset($args[1])) {
			$order_status_id = $args[1];
		} else {
			$order_status_id = 0;
		}

		if (isset($args[2])) {
			$comment = $args[2];
		} else {
			$comment = '';
		}

		if (isset($args[3])) {
			$notify = $args[3];
		} else {
			$notify = '';
		}

		if ($notify) {
			$this->load->model('sale/order');

			$order_info = $this->model_sale_order->getOrder($order_id);

			if ($order_info && $order_info['email']) {
				$this->load->language('mail/order');

				$status_query = $this->db->query("SELECT name FROM `" . DB_PREFIX . "order_status` WHERE order_status_id = '" . (int)$order_status_id . "' AND language_id = '" . (int)$order_info['language_id'] . "'");

				if ($status_query->num_rows) {
					$order_status = $status_query->row['name'];
				} else {
					$order_status = '';
				}

				$data['order_id'] = $order_id;
				$data['date_added'] = date($this->language->get('date_format_short'), strtotime($order_info['date_added']));
				$data['order_status'] = $order_status;
				$data['tracking'] = $order_info['tracking_number'] ? str_replace('|', ', ', $order_info['tracking_number']) : '';
				$data['comment'] = strip_tags(html_entity_decode($comment, ENT_QUOTES, 'UTF-8'));
				$data['link'] = $order_info['store_url'] . 'index.php?route=account/order/info&order_id=' . $order_id;

				$data['store_name'] = html_entity_decode($order_info['store_name'], ENT_QUOTES, 'UTF-8');
				$data['store_url'] = $order_info['store_url'];
				$data['text_button_order'] = $this->language->get('text_button_order');

				$this->send($order_info, sprintf($this->language->get('text_subject'), html_entity_decode($order_info['store_name'], ENT_QUOTES, 'UTF-8'), $order_id), $this->load->view('mail/order_edit', $data));
			}
		}
	}

	public function shipped(&$data) {
		$order_id = (int)($data['order_id'] ?? 0);
		$tracking_number = trim((string)($data['tracking_number'] ?? ''));
		$comment = trim((string)($data['comment'] ?? ''));

		if (!$order_id || !$tracking_number) {
			return;
		}

		$this->load->model('sale/order');

		$order_info = $this->model_sale_order->getOrder($order_id);

		if ($order_info && $order_info['email']) {
			$this->load->language('mail/order');

			$data['tracking_number'] = $tracking_number;
			$data['link'] = $order_info['store_url'] . 'index.php?route=account/order/info&order_id=' . $order_id;

			$data['store_name'] = html_entity_decode($order_info['store_name'], ENT_QUOTES, 'UTF-8');
			$data['store_url'] = $order_info['store_url'];
			$data['text_button_order'] = $this->language->get('text_button_order');

			$this->send($order_info, sprintf($this->language->get('text_shipped_subject'), html_entity_decode($order_info['store_name'], ENT_QUOTES, 'UTF-8'), $order_id), $this->load->view('mail/order_shipped', $data));
		}
	}

	private function send(array $order_info, string $subject, string $body): void {
		if (!$this->config->get('config_mail_engine')) {
			return;
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

		$mail->setTo($order_info['email']);
		$mail->setFrom($this->config->get('config_email'));
		$mail->setSender(html_entity_decode($order_info['store_name'], ENT_QUOTES, 'UTF-8'));
		$mail->setSubject($subject);
		$mail->setHtml($body);
		$mail->on_token_refresh = function ($token) {
			$this->db->query("UPDATE " . DB_PREFIX . "setting SET `value` = '" . $this->db->escape($token) . "' WHERE `key` = 'config_mail_smtp_oauth_token' AND `store_id` = '" . (int)$this->config->get('config_store_id') . "'");
		};

		try {
			$mail->send();
		} catch (\Exception $e) {
			$this->log->write('Order mail send failed (order #' . $order_info['order_id'] . '): ' . $e->getMessage());
		}
	}
}
