<?php
class ControllerMailTransaction extends Controller {
	public function index(&$route, &$args, &$output) {
		$this->load->language('mail/transaction');

		$this->load->model('account/customer');
		
		$customer_info = $this->model_account_customer->getCustomer($args[0]);

		if ($customer_info) {
			$data['text_received'] = sprintf($this->language->get('text_received'), $this->config->get('config_name'));
			$data['text_amount'] = $this->language->get('text_amount');
			$data['text_total'] = $this->language->get('text_total');
			
			$data['amount'] = $this->currency->format($args[2], $this->config->get('config_currency'));
			$data['total'] = $this->currency->format($this->model_account_customer->getTransactionTotal($args[0]), $this->config->get('config_currency'));
	
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
	
			$mail->setTo($customer_info['email']);
			$mail->setFrom($this->config->get('config_email'));
			$mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
			$mail->setSubject(html_entity_decode(sprintf($this->language->get('text_subject'), $this->config->get('config_name')), ENT_QUOTES, 'UTF-8'));
			$mail->setText($this->load->view('mail/transaction', $data));
			$mail->on_token_refresh = function ($token) {
				$this->db->query("UPDATE " . DB_PREFIX . "setting SET `value` = '" . $this->db->escape($token) . "' WHERE `key` = 'config_mail_smtp_oauth_token' AND `store_id` = '" . (int)$this->config->get('config_store_id') . "'");
			};

			$mail->send();
		}
	}
}



