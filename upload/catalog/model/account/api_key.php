<?php
/**
 * DockerCart storefront — customer API keys (Account > API).
 *
 * Storage for the customer-owned rows of oc_api_key. Every method is scoped by
 * the authenticated customer id, so a customer can never touch a key of someone
 * else (or an admin key).
 *
 * The plain bearer token never touches the database: only its SHA-256 hash is
 * stored. Tokens are returned exactly once (create / rotate). The per-account
 * cap (config_api_customer_max_keys, default 100) is enforced here.
 *
 * @package    DockerCart
 * @subpackage Catalog
 */

declare(strict_types=1);

class ModelAccountApiKey extends Model {
	/**
	 * Creates a key for a customer.
	 *
	 * @param array $data name, comment, expires_at, ip_allowlist, rate_limit, scopes
	 * @return array{api_key_id: int, token: string}
	 */
	public function addKey(int $customer_id, array $data): array {
		$token = \Dockercart\Api\KeyRepository::generateToken();

		$scopes = \Dockercart\Api\CustomerScopes::encode($data['scopes'] ?? null);

		$this->db->query("INSERT INTO `" . DB_PREFIX . "api_key` SET
			`name` = '" . $this->db->escape((string)$data['name']) . "',
			`user_id` = '0',
			`customer_id` = '" . (int)$customer_id . "',
			`comment` = '" . $this->db->escape((string)($data['comment'] ?? '')) . "',
			`prefix` = '" . $this->db->escape($token['prefix']) . "',
			`token_hash` = '" . $this->db->escape($token['token_hash']) . "',
			`user_group_id` = '0',
			`status` = '1',
			`scopes` = " . ($scopes === null ? 'NULL' : "'" . $this->db->escape($scopes) . "'") . ",
			`expires_at` = " . (!empty($data['expires_at']) ? "'" . $this->db->escape((string)$data['expires_at']) . "'" : 'NULL') . ",
			`ip_allowlist` = '" . $this->db->escape((string)($data['ip_allowlist'] ?? '')) . "',
			`rate_limit` = '" . (int)($data['rate_limit'] ?? 0) . "',
			`date_added` = NOW(),
			`date_modified` = NOW()");

		return [
			'api_key_id' => (int)$this->db->getLastId(),
			'token'      => $token['token'],
		];
	}

	/**
	 * Keys of one customer (never admin keys, never another customer's).
	 */
	public function getKeys(int $customer_id): array {
		$query = $this->db->query("SELECT k.*,
				(SELECT COUNT(*) FROM `" . DB_PREFIX . "api_log` l WHERE l.`api_key_id` = k.`api_key_id` AND l.`date_added` >= DATE_SUB(NOW(), INTERVAL 1 DAY)) AS `requests_24h`,
				(SELECT COUNT(*) FROM `" . DB_PREFIX . "api_log` l WHERE l.`api_key_id` = k.`api_key_id`) AS `requests_total`
			FROM `" . DB_PREFIX . "api_key` k
			WHERE k.`customer_id` = '" . (int)$customer_id . "'
			ORDER BY k.`api_key_id` DESC");

		return $query->rows;
	}

	/**
	 * A single key — only when it belongs to the customer.
	 */
	public function getKey(int $customer_id, int $api_key_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "api_key` WHERE `api_key_id` = '" . (int)$api_key_id . "' AND `customer_id` = '" . (int)$customer_id . "'");

		return $query->num_rows ? $query->row : [];
	}

	/**
	 * Issues a new token (the old one stops working immediately) and returns it
	 * once. The WHERE clause keeps the rotation scoped to the customer.
	 */
	public function rotateKey(int $customer_id, int $api_key_id): string {
		$token = \Dockercart\Api\KeyRepository::generateToken();

		$this->db->query("UPDATE `" . DB_PREFIX . "api_key` SET
			`prefix` = '" . $this->db->escape($token['prefix']) . "',
			`token_hash` = '" . $this->db->escape($token['token_hash']) . "',
			`date_modified` = NOW()
			WHERE `api_key_id` = '" . (int)$api_key_id . "' AND `customer_id` = '" . (int)$customer_id . "'");

		return $token['token'];
	}

	public function setStatus(int $customer_id, int $api_key_id, int $status): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "api_key` SET `status` = '" . ($status ? 1 : 0) . "', `date_modified` = NOW() WHERE `api_key_id` = '" . (int)$api_key_id . "' AND `customer_id` = '" . (int)$customer_id . "'");
	}

	public function deleteKey(int $customer_id, int $api_key_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "api_key` WHERE `api_key_id` = '" . (int)$api_key_id . "' AND `customer_id` = '" . (int)$customer_id . "'");
	}

	public function getTotalKeys(int $customer_id): int {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "api_key` WHERE `customer_id` = '" . (int)$customer_id . "'");

		return (int)$query->row['total'];
	}

	/**
	 * Per-account key cap (config_api_customer_max_keys, default 100).
	 */
	public function getMaxKeys(): int {
		$max = (int)$this->config->get('config_api_customer_max_keys');

		return $max > 0 ? $max : 100;
	}

	public function canAdd(int $customer_id): bool {
		return $this->getTotalKeys($customer_id) < $this->getMaxKeys();
	}
}
