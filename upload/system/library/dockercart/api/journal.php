<?php
/**
 * DockerCart admin REST API — request journal (oc_api_log).
 *
 * One row per API request, written from the shutdown handler registered in
 * admin/controller/startup/api.php so that responses ending in a fatal error are
 * journalled too. key_name is a snapshot so the journal stays readable after a
 * key is deleted (records are intended to outlive keys — they are security
 * evidence, not profile data).
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

final class Journal {
	private \DB $db;

	public function __construct(\Registry $registry) {
		$this->db = $registry->get('db');
	}

	public function record(array $row): void {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "api_log` SET
			`api_key_id` = '" . (int)($row['api_key_id'] ?? 0) . "',
			`key_name` = '" . $this->db->escape((string)($row['key_name'] ?? '')) . "',
			`method` = '" . $this->db->escape((string)($row['method'] ?? '')) . "',
			`path` = '" . $this->db->escape(substr((string)($row['path'] ?? ''), 0, 255)) . "',
			`route` = '" . $this->db->escape(substr((string)($row['route'] ?? ''), 0, 128)) . "',
			`status_code` = '" . (int)($row['status_code'] ?? 0) . "',
			`duration_ms` = '" . (int)($row['duration_ms'] ?? 0) . "',
			`ip` = '" . $this->db->escape(substr((string)($row['ip'] ?? ''), 0, 45)) . "',
			`user_agent` = '" . $this->db->escape(substr((string)($row['user_agent'] ?? ''), 0, 255)) . "',
			`request_id` = '" . $this->db->escape(substr((string)($row['request_id'] ?? ''), 0, 40)) . "',
			`error_code` = '" . $this->db->escape(substr((string)($row['error_code'] ?? ''), 0, 64)) . "',
			`date_added` = NOW()");
	}

	/**
	 * Deletes journal rows older than the given number of days.
	 */
	public function purgeOlderThan(int $days): int {
		$days = max(0, $days);

		$this->db->query("DELETE FROM `" . DB_PREFIX . "api_log` WHERE `date_added` < DATE_SUB(NOW(), INTERVAL " . $days . " DAY)");

		return (int)$this->db->countAffected();
	}

	public function clear(): int {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "api_log`");

		return (int)$this->db->countAffected();
	}
}
