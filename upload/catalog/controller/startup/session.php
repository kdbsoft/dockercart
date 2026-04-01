<?php
class ControllerStartupSession extends Controller {
	public function index() {
		if (isset($this->request->get['api_token']) && isset($this->request->get['route']) && substr($this->request->get['route'], 0, 4) == 'api/') {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "api_session` WHERE TIMESTAMPADD(HOUR, 1, date_modified) < NOW()");
					
			// Check if API session exists and is valid
			$api_query = $this->db->query("SELECT DISTINCT a.*, `as`.* FROM `" . DB_PREFIX . "api` `a` 
				INNER JOIN `" . DB_PREFIX . "api_session` `as` ON (a.api_id = as.api_id) 
				WHERE a.status = '1' AND `as`.`session_id` = '" . $this->db->escape($this->request->get['api_token']) . "'");
		 
			if ($api_query->num_rows) {
				$api_id = $api_query->row['api_id'];
				
				// Check IP restrictions: only allow if IP matches an allowed IP or no IP restrictions are set
				$ip_check = $this->db->query("SELECT COUNT(*) as count FROM `" . DB_PREFIX . "api_ip` WHERE api_id = '" . (int)$api_id . "'");
				$has_restrictions = $ip_check->row['count'] > 0;
				
				$ip_allowed = true;
				if ($has_restrictions) {
					// If restrictions exist, check if current IP is in the whitelist
					$ip_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "api_ip` WHERE api_id = '" . (int)$api_id . "' AND ip = '" . $this->db->escape($this->request->server['REMOTE_ADDR']) . "'");
					$ip_allowed = ($ip_query->num_rows > 0);
				}
				// If no restrictions, all IPs are allowed
				
				if ($ip_allowed) {
					$this->session->start($this->request->get['api_token']);
					
					// keep the session alive
					$this->db->query("UPDATE `" . DB_PREFIX . "api_session` SET `date_modified` = NOW() WHERE `api_session_id` = '" . (int)$api_query->row['api_session_id'] . "'");
				}
			}
		} else {
			if (isset($_COOKIE[$this->config->get('session_name')])) {
				$session_id = $_COOKIE[$this->config->get('session_name')];
			} else {
				$session_id = '';
			}
			
			$this->session->start($session_id);
			
			setcookie($this->config->get('session_name'), $this->session->getId(), (ini_get('session.cookie_lifetime') ? time() + ini_get('session.cookie_lifetime') : 0), ini_get('session.cookie_path'), ini_get('session.cookie_domain'));	
		}
	}
}
