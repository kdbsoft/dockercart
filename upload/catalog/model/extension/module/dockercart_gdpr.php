<?php
class ModelExtensionModuleDockercartGdpr extends Model {
	public function getSetting($code, $store_id = 0) {
		$this->load->model('setting/setting');

		return $this->model_setting_setting->getSetting($code, $store_id);
	}

	public function getCookieGroups($language_id) {
		$query = $this->db->query(
			"SELECT g.*, gd.name, gd.description
			 FROM `" . DB_PREFIX . "dockercart_cookie_group` g
			 LEFT JOIN `" . DB_PREFIX . "dockercart_cookie_group_description` gd
			   ON (g.cookie_group_id = gd.cookie_group_id AND gd.language_id = " . (int)$language_id . ")
			 WHERE g.status = 1
			 ORDER BY g.sort_order ASC"
		);

		return $query->rows;
	}

	public function getCookies($group_id, $language_id) {
		$query = $this->db->query(
			"SELECT c.*, cd.description
			 FROM `" . DB_PREFIX . "dockercart_cookie` c
			 LEFT JOIN `" . DB_PREFIX . "dockercart_cookie_description` cd
			   ON (c.cookie_id = cd.cookie_id AND cd.language_id = " . (int)$language_id . ")
			 WHERE c.cookie_group_id = " . (int)$group_id . "
			   AND c.status = 1
			 ORDER BY c.sort_order ASC"
		);

		return $query->rows;
	}

	public function getInformationUrl($information_id) {
		if (!$information_id) {
			return '';
		}

		$this->load->model('catalog/information');

		$information_info = $this->model_catalog_information->getInformation($information_id);

		if ($information_info) {
			return $this->url->link('information/information', 'information_id=' . $information_id);
		}

		return '';
	}

	public function getCustomerByEmail($email) {
		$query = $this->db->query(
			"SELECT customer_id FROM `" . DB_PREFIX . "customer`
			 WHERE LCASE(email) = '" . $this->db->escape(strtolower($email)) . "'
			 LIMIT 1"
		);

		return $query->num_rows ? $query->row : null;
	}

	public function doNotSellOptOut($customer_id) {
		$this->load->library('dockercart/gdpr');
		$gdpr = new DockercartGdpr($this->registry);
		$gdpr->setConsent((int)$customer_id, 'do_not_sell', true, $this->session->getId());
	}

	public function doNotSellOptIn($customer_id) {
		$this->load->library('dockercart/gdpr');
		$gdpr = new DockercartGdpr($this->registry);
		$gdpr->setConsent((int)$customer_id, 'do_not_sell', false, $this->session->getId());
	}

	public function hasDoNotSellOptOut($customer_id) {
		$this->load->library('dockercart/gdpr');
		$gdpr = new DockercartGdpr($this->registry);
		return $gdpr->hasConsented((int)$customer_id, 'do_not_sell');
	}
}
