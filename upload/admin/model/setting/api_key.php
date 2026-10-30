<?php
/**
 * DockerCart admin — API key model.
 *
 * Storage for oc_api_key. The plain bearer token never touches the database:
 * only its SHA-256 hash is stored, so a dump cannot be replayed against the
 * API. Tokens are returned exactly once (create / rotate).
 *
 * A key either belongs to an admin user (`user_id` > 0) or to a user group
 * (`user_id` = 0, the "shared" key). The permissions of an owned key always
 * follow the *current* group of its owner, so oc_user_group.permission stays
 * the single source of truth.
 *
 * Keep in sync with upload/system/library/dockercart/api/key_repository.php,
 * which performs the token lookup on every API request.
 *
 * @package    DockerCart
 * @subpackage Admin
 */

declare(strict_types=1);

class ModelSettingApiKey extends Model {
	/**
	 * Creates a key.
	 *
	 * @param array $data name, comment, user_id, customer_id, user_group_id,
	 *                    status, expires_at, ip_allowlist, rate_limit, scopes
	 * @return array{api_key_id: int, token: string}
	 */
	public function addKey(array $data): array {
		$token = \Dockercart\Api\KeyRepository::generateToken();

		$user_id = (int)($data['user_id'] ?? 0);
		$customer_id = (int)($data['customer_id'] ?? 0);
		$user_group_id = (int)($data['user_group_id'] ?? 0);

		// An owned key inherits the group of its owner — the group select is
		// only relevant for shared keys. A customer key has no admin group.
		if ($user_id) {
			$owner = $this->getOwner($user_id);

			if ($owner) {
				$user_group_id = (int)$owner['user_group_id'];
			}
		} elseif ($customer_id) {
			$user_group_id = 0;
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "api_key` SET
			`name` = '" . $this->db->escape((string)$data['name']) . "',
			`user_id` = '" . $user_id . "',
			`customer_id` = '" . $customer_id . "',
			`comment` = '" . $this->db->escape((string)($data['comment'] ?? '')) . "',
			`prefix` = '" . $this->db->escape($token['prefix']) . "',
			`token_hash` = '" . $this->db->escape($token['token_hash']) . "',
			`user_group_id` = '" . $user_group_id . "',
			`status` = '" . (int)($data['status'] ?? 1) . "',
			`scopes` = " . $this->scopesSql($data['scopes'] ?? null) . ",
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

	public function editKey(int $api_key_id, array $data): void {
		$user_id = (int)($data['user_id'] ?? 0);
		$customer_id = (int)($data['customer_id'] ?? 0);
		$user_group_id = (int)($data['user_group_id'] ?? 0);

		if ($user_id) {
			$owner = $this->getOwner($user_id);

			if ($owner) {
				$user_group_id = (int)$owner['user_group_id'];
			}
		} elseif ($customer_id) {
			$user_group_id = 0;
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "api_key` SET
			`name` = '" . $this->db->escape((string)$data['name']) . "',
			`user_id` = '" . $user_id . "',
			`customer_id` = '" . $customer_id . "',
			`comment` = '" . $this->db->escape((string)($data['comment'] ?? '')) . "',
			`user_group_id` = '" . $user_group_id . "',
			`status` = '" . (int)($data['status'] ?? 1) . "',
			`scopes` = " . $this->scopesSql($data['scopes'] ?? null) . ",
			`expires_at` = " . (!empty($data['expires_at']) ? "'" . $this->db->escape((string)$data['expires_at']) . "'" : 'NULL') . ",
			`ip_allowlist` = '" . $this->db->escape((string)($data['ip_allowlist'] ?? '')) . "',
			`rate_limit` = '" . (int)($data['rate_limit'] ?? 0) . "',
			`date_modified` = NOW()
			WHERE `api_key_id` = '" . $api_key_id . "'");
	}

	/**
	 * Encodes a scope list for the `scopes` column (NULL = inherit all).
	 *
	 * @param mixed $scopes
	 */
	private function scopesSql($scopes): string {
		$encoded = \Dockercart\Api\CustomerScopes::encode($scopes);

		return $encoded === null ? 'NULL' : "'" . $this->db->escape($encoded) . "'";
	}

	/**
	 * Issues a new token for an existing key (the old one stops working
	 * immediately) and returns it once.
	 */
	public function rotateKey(int $api_key_id): string {
		$token = \Dockercart\Api\KeyRepository::generateToken();

		$this->db->query("UPDATE `" . DB_PREFIX . "api_key` SET
			`prefix` = '" . $this->db->escape($token['prefix']) . "',
			`token_hash` = '" . $this->db->escape($token['token_hash']) . "',
			`date_modified` = NOW()
			WHERE `api_key_id` = '" . $api_key_id . "'");

		return $token['token'];
	}

	public function setStatus(int $api_key_id, int $status): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "api_key` SET `status` = '" . ($status ? 1 : 0) . "', `date_modified` = NOW() WHERE `api_key_id` = '" . $api_key_id . "'");
	}

	public function deleteKey(int $api_key_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "api_key` WHERE `api_key_id` = '" . $api_key_id . "'");
	}

	/**
	 * Drops every key of a user (called when the user itself is deleted).
	 */
	public function deleteKeysByUser(int $user_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "api_key` WHERE `user_id` = '" . $user_id . "'");
	}

	/**
	 * Re-points the stored group of the keys of a user after the user moved to
	 * another group. The effective group is resolved from the owner on every
	 * request anyway — this only keeps the stored copy truthful.
	 */
	public function syncUserGroup(int $user_id, int $user_group_id): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "api_key` SET `user_group_id` = '" . $user_group_id . "', `date_modified` = NOW() WHERE `user_id` = '" . $user_id . "'");
	}

	public function getKey(int $api_key_id): array {
		$query = $this->db->query("SELECT k.*, ug.`name` AS `group_name`, u.`username` AS `owner_username`
			FROM `" . DB_PREFIX . "api_key` k
			LEFT JOIN `" . DB_PREFIX . "user_group` ug ON (ug.`user_group_id` = k.`user_group_id`)
			LEFT JOIN `" . DB_PREFIX . "user` u ON (u.`user_id` = k.`user_id`)
			WHERE k.`api_key_id` = '" . $api_key_id . "'");

		return $query->num_rows ? $query->row : [];
	}

	/**
	 * Key list with the group name, the owner and the request count of the last
	 * 24 hours.
	 *
	 * By default only admin/shared keys are returned: customer keys are managed
	 * from the customer form, so the central list stays admin-only. Pass
	 * `customer_id` to list the keys of one customer instead.
	 *
	 * @param array $filter user_id (owner), user_group_id, customer_id
	 */
	public function getKeys(array $filter = []): array {
		$where = '';

		if (array_key_exists('customer_id', $filter)) {
			$where .= " AND k.`customer_id` = '" . (int)$filter['customer_id'] . "'";
		} else {
			$where .= " AND k.`customer_id` = '0'";
		}

		if (!empty($filter['user_id'])) {
			$where .= " AND k.`user_id` = '" . (int)$filter['user_id'] . "'";
		}

		if (!empty($filter['user_group_id'])) {
			$where .= " AND k.`user_group_id` = '" . (int)$filter['user_group_id'] . "'";
		}

		$query = $this->db->query("SELECT k.*, ug.`name` AS `group_name`, u.`username` AS `owner_username`, u.`user_group_id` AS `owner_group_id`,
				(SELECT COUNT(*) FROM `" . DB_PREFIX . "api_log` l WHERE l.`api_key_id` = k.`api_key_id` AND l.`date_added` >= DATE_SUB(NOW(), INTERVAL 1 DAY)) AS `requests_24h`,
				(SELECT COUNT(*) FROM `" . DB_PREFIX . "api_log` l WHERE l.`api_key_id` = k.`api_key_id`) AS `requests_total`
			FROM `" . DB_PREFIX . "api_key` k
			LEFT JOIN `" . DB_PREFIX . "user_group` ug ON (ug.`user_group_id` = k.`user_group_id`)
			LEFT JOIN `" . DB_PREFIX . "user` u ON (u.`user_id` = k.`user_id`)
			WHERE 1 = 1" . $where . "
			ORDER BY k.`api_key_id` DESC");

		return $query->rows;
	}

	public function getTotalKeys(): int {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "api_key` WHERE `customer_id` = '0'");

		return (int)$query->row['total'];
	}

	/**
	 * Number of keys a customer owns (used to enforce the per-account cap).
	 */
	public function getTotalKeysByCustomer(int $customer_id): int {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "api_key` WHERE `customer_id` = '" . $customer_id . "'");

		return (int)$query->row['total'];
	}

	/**
	 * Per-account key cap (config_api_customer_max_keys, default 100).
	 */
	public function getMaxCustomerKeys(): int {
		$max = (int)$this->config->get('config_api_customer_max_keys');

		return $max > 0 ? $max : 100;
	}

	public function canAddCustomerKey(int $customer_id): bool {
		return $this->getTotalKeysByCustomer($customer_id) < $this->getMaxCustomerKeys();
	}

	/**
	 * Drops every key of a customer (called when the customer is deleted).
	 */
	public function deleteKeysByCustomer(int $customer_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "api_key` WHERE `customer_id` = '" . $customer_id . "'");
	}

	/**
	 * Selectable permission sources (admin user groups).
	 */
	public function getGroups(): array {
		$query = $this->db->query("SELECT `user_group_id`, `name`, `permission` FROM `" . DB_PREFIX . "user_group` ORDER BY `name` ASC");

		return $query->rows;
	}

	/**
	 * Admin users that may own a key.
	 */
	public function getUsers(): array {
		$query = $this->db->query("SELECT u.`user_id`, u.`username`, u.`firstname`, u.`lastname`, u.`status`, u.`user_group_id`, ug.`name` AS `group_name`
			FROM `" . DB_PREFIX . "user` u
			LEFT JOIN `" . DB_PREFIX . "user_group` ug ON (ug.`user_group_id` = u.`user_group_id`)
			ORDER BY u.`username` ASC");

		return $query->rows;
	}

	/**
	 * A single owner (admin user).
	 */
	public function getOwner(int $user_id): array {
		$query = $this->db->query("SELECT `user_id`, `username`, `firstname`, `lastname`, `status`, `user_group_id` FROM `" . DB_PREFIX . "user` WHERE `user_id` = '" . $user_id . "'");

		return $query->num_rows ? $query->row : [];
	}
}
