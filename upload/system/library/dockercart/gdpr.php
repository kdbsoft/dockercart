<?php
class DockercartGdpr {
	private $db;
	private $prefix;

	public function __construct($registry) {
		$this->db = $registry->get('db');
		$this->prefix = DB_PREFIX;
	}

	public function getFramework(): string {
		$query = $this->db->query("SELECT `key`, `value` FROM `" . $this->prefix . "setting` WHERE `code` = 'module_dockercart_gdpr' AND `key` = 'module_dockercart_gdpr_framework'");

		if ($query->num_rows && !empty($query->row['value'])) {
			return (string)$query->row['value'];
		}

		return 'eu';
	}

	public function getConsent(int $customerId, string $consentType): ?array {
		$query = $this->db->query(
			"SELECT * FROM `" . $this->prefix . "dockercart_gdpr_consent`
			 WHERE `customer_id` = " . (int)$customerId . "
			   AND `consent_type` = '" . $this->db->escape($consentType) . "'
			 ORDER BY `consent_id` DESC LIMIT 1"
		);

		return $query->num_rows ? $query->row : null;
	}

	public function hasConsented(int $customerId, string $consentType): bool {
		$row = $this->getConsent($customerId, $consentType);

		return $row !== null && (int)$row['consent_value'] === 1;
	}

	public function setConsent(int $customerId, string $consentType, bool $value, ?string $sessionId = null): void {
		$ip = $this->getIp();
		$userAgent = $this->getUserAgent();

		$this->db->query(
			"INSERT INTO `" . $this->prefix . "dockercart_gdpr_consent` SET
			 `customer_id` = " . (int)$customerId . ",
			 `session_id` = '" . $this->db->escape($sessionId ?? '') . "',
			 `consent_type` = '" . $this->db->escape($consentType) . "',
			 `consent_value` = " . ($value ? '1' : '0') . ",
			 `ip` = '" . $this->db->escape($ip) . "',
			 `user_agent` = '" . $this->db->escape($userAgent) . "',
			 `date_added` = NOW()"
		);
	}

	public function revokeConsent(int $customerId, string $consentType): void {
		$this->setConsent($customerId, $consentType, false);
	}

	public function createRequest(int $customerId, string $type, int $storeId = 0, int $languageId = 0): int {
		$ip = $this->getIp();
		$userAgent = $this->getUserAgent();

		$this->db->query(
			"INSERT INTO `" . $this->prefix . "dockercart_gdpr_request` SET
			 `customer_id` = " . (int)$customerId . ",
			 `type` = '" . $this->db->escape($type) . "',
			 `status` = 'pending',
			 `store_id` = " . (int)$storeId . ",
			 `language_id` = " . (int)$languageId . ",
			 `ip` = '" . $this->db->escape($ip) . "',
			 `user_agent` = '" . $this->db->escape($userAgent) . "',
			 `date_added` = NOW()"
		);

		return (int)$this->db->getLastId();
	}

	public function hasPendingRequest(int $customerId, string $type): bool {
		$query = $this->db->query(
			"SELECT COUNT(*) AS total FROM `" . $this->prefix . "dockercart_gdpr_request`
			 WHERE `customer_id` = " . (int)$customerId . "
			   AND `type` = '" . $this->db->escape($type) . "'
			   AND `status` IN ('pending','approved')"
		);

		return (int)$query->row['total'] > 0;
	}

	public function exportCustomerData(int $customerId): array {
		$data = array();

		$customer = $this->db->query(
			"SELECT * FROM `" . $this->prefix . "customer`
			 WHERE `customer_id` = " . (int)$customerId
		);

		if (!$customer->num_rows) {
			return $data;
		}

		$data['customer'] = $customer->row;

		$addresses = $this->db->query(
			"SELECT * FROM `" . $this->prefix . "address`
			 WHERE `customer_id` = " . (int)$customerId
		);
		$data['addresses'] = $addresses->rows;

		$orders = $this->db->query(
			"SELECT * FROM `" . $this->prefix . "order`
			 WHERE `customer_id` = " . (int)$customerId
		);
		$data['orders'] = $orders->rows;

		$consents = $this->db->query(
			"SELECT * FROM `" . $this->prefix . "dockercart_gdpr_consent`
			 WHERE `customer_id` = " . (int)$customerId
		);
		$data['consents'] = $consents->rows;

		$subscriber = $this->db->query(
			"SELECT * FROM `" . $this->prefix . "dockercart_newsletter_subscriber`
			 WHERE `email` = (SELECT `email` FROM `" . $this->prefix . "customer` WHERE `customer_id` = " . (int)$customerId . ")"
		);
		$data['newsletter'] = $subscriber->rows;

		return $data;
	}

	public function anonymizeCustomer(int $customerId): void {
		$anonEmail = 'deleted-' . $customerId . '@anon.local';

		$this->db->query(
			"UPDATE `" . $this->prefix . "customer` SET
			 `firstname` = '',
			 `lastname` = '',
			 `email` = '" . $this->db->escape($anonEmail) . "',
			 `telephone` = '',
			 `newsletter` = '0',
			 `safe` = '0',
			 `status` = '0',
			 `date_modified` = NOW()
			 WHERE `customer_id` = " . (int)$customerId
		);

		$this->db->query(
			"UPDATE `" . $this->prefix . "address` SET
			 `firstname` = '',
			 `lastname` = '',
			 `company` = '',
			 `address_1` = '',
			 `address_2` = '',
			 `city` = '',
			 `postcode` = '',
			 `zone_id` = '0',
			 `country_id` = '0'
			 WHERE `customer_id` = " . (int)$customerId
		);

		$this->db->query(
			"UPDATE `" . $this->prefix . "order` SET
			 `firstname` = '[deleted]',
			 `lastname` = '[deleted]',
			 `email` = '" . $this->db->escape($anonEmail) . "',
			 `telephone` = '[deleted]',
			 `payment_firstname` = '[deleted]',
			 `payment_lastname` = '[deleted]',
			 `payment_company` = '[deleted]',
			 `payment_address_1` = '[deleted]',
			 `payment_address_2` = '[deleted]',
			 `payment_city` = '[deleted]',
			 `payment_postcode` = '[deleted]',
			 `payment_country` = '[deleted]',
			 `payment_zone` = '[deleted]',
			 `shipping_firstname` = '[deleted]',
			 `shipping_lastname` = '[deleted]',
			 `shipping_company` = '[deleted]',
			 `shipping_address_1` = '[deleted]',
			 `shipping_address_2` = '[deleted]',
			 `shipping_city` = '[deleted]',
			 `shipping_postcode` = '[deleted]',
			 `shipping_country` = '[deleted]',
			 `shipping_zone` = '[deleted]'
			 WHERE `customer_id` = " . (int)$customerId
		);

		$this->db->query(
			"UPDATE `" . $this->prefix . "customer_activity` SET `key` = 'anonymized' WHERE `customer_id` = " . (int)$customerId
		);
	}

	public function getCookieGroups(int $languageId): array {
		$query = $this->db->query(
			"SELECT g.*, gd.name, gd.description
			 FROM `" . $this->prefix . "dockercart_cookie_group` g
			 LEFT JOIN `" . $this->prefix . "dockercart_cookie_group_description` gd
			   ON (g.cookie_group_id = gd.cookie_group_id AND gd.language_id = " . (int)$languageId . ")
			 WHERE g.status = 1
			 ORDER BY g.sort_order ASC"
		);

		return $query->rows;
	}

	public function getCookies(int $groupId, int $languageId): array {
		$query = $this->db->query(
			"SELECT c.*, cd.description
			 FROM `" . $this->prefix . "dockercart_cookie` c
			 LEFT JOIN `" . $this->prefix . "dockercart_cookie_description` cd
			   ON (c.cookie_id = cd.cookie_id AND cd.language_id = " . (int)$languageId . ")
			 WHERE c.cookie_group_id = " . (int)$groupId . "
			   AND c.status = 1
			 ORDER BY c.sort_order ASC"
		);

		return $query->rows;
	}

	private function getIp(): string {
		if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);

			return trim((string)$ips[0]);
		}

		return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
	}

	private function getUserAgent(): string {
		return $_SERVER['HTTP_USER_AGENT'] ?? '';
	}
}
