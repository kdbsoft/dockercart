<?php

declare(strict_types=1);

class DockercartLicensing {
	private $registry;
	private $db;
	private $config;
	private $logger;

	const GRACE_DAYS = 7;
	const VALIDATE_TTL = 604800;

	public function __construct($registry) {
		$this->registry = $registry;
		$this->db = $registry->get('db');
		$this->config = $registry->get('config');

		require_once DIR_SYSTEM . 'library/dockercart_logger.php';
		$this->logger = new DockercartLogger($this->registry, 'licensing');
	}

	public function getApiUrl(): string {
		return defined('LICENSING_API_URL') ? LICENSING_API_URL : 'http://licensing.docker.localhost:8080';
	}

	public function getDomain(): string {
		if (defined('LICENSING_DOMAIN') && LICENSING_DOMAIN !== '') {
			return LICENSING_DOMAIN;
		}

		$domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
		$domain = strtolower($domain);
		$domain = preg_replace('/^www\./', '', $domain);
		$domain = preg_replace('/:.*$/', '', $domain);

		return $domain;
	}

	public function getFingerprint(): string {
		$parts = [
			$this->getDomain(),
			defined('DB_HOSTNAME') ? DB_HOSTNAME : '',
			defined('DB_DATABASE') ? DB_DATABASE : '',
		];

		return md5(implode('|', $parts));
	}

	public function getGraceDays(): int {
		return defined('LICENSING_GRACE_DAYS') ? (int)LICENSING_GRACE_DAYS : self::GRACE_DAYS;
	}

	public function getLicense(string $module_code): ?array {
		$result = $this->db->query(
			"SELECT * FROM `" . DB_PREFIX . "dockercart_license`
			 WHERE `module_code` = '" . $this->db->escape($module_code) . "'"
		);

		return $result->num_rows ? $result->row : null;
	}

	public function setLicenseKey(string $module_code, string $sku, string $license_key): void {
		$existing = $this->getLicense($module_code);

		if ($existing) {
			$this->db->query(
				"UPDATE `" . DB_PREFIX . "dockercart_license`
				 SET `license_key` = '" . $this->db->escape($license_key) . "',
				     `sku` = '" . $this->db->escape($sku) . "',
				     `status` = 'unknown',
				     `last_verified` = NULL,
				     `last_valid` = NULL,
				     `consecutive_failures` = 0,
				     `domain` = '" . $this->db->escape($this->getDomain()) . "',
				     `fingerprint` = '" . $this->db->escape($this->getFingerprint()) . "',
				     `date_modified` = NOW()
				 WHERE `module_code` = '" . $this->db->escape($module_code) . "'"
			);
		} else {
			$this->db->query(
				"INSERT INTO `" . DB_PREFIX . "dockercart_license`
				 SET `module_code` = '" . $this->db->escape($module_code) . "',
				     `sku` = '" . $this->db->escape($sku) . "',
				     `license_key` = '" . $this->db->escape($license_key) . "',
				     `domain` = '" . $this->db->escape($this->getDomain()) . "',
				     `fingerprint` = '" . $this->db->escape($this->getFingerprint()) . "',
				     `status` = 'unknown',
				     `date_added` = NOW(),
				     `date_modified` = NOW()"
			);
		}
	}

	public function activate(string $module_code, string $key): array {
		$api_url = $this->getApiUrl();
		$domain = $this->getDomain();
		$fingerprint = $this->getFingerprint();

		$payload = json_encode([
			'key' => $key,
			'domain' => $domain,
			'fingerprint' => $fingerprint,
		]);

		$url = $api_url . '/api/v1/license/activate';

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
		$this->applyResolve($ch, $url);

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($http_code >= 200 && $http_code < 300 && $response !== false) {
			$body = json_decode($response, true);

			if (is_array($body) && isset($body['activation'])) {
				$license = $this->getLicense($module_code);
				$new_status = (isset($body['isNew']) && $body['isNew']) ? 'active' : 'active';

				if ($license) {
					$this->db->query(
						"UPDATE `" . DB_PREFIX . "dockercart_license`
						 SET `status` = '" . $this->db->escape($new_status) . "',
						     `fingerprint` = '" . $this->db->escape($fingerprint) . "',
						     `last_verified` = NOW(),
						     `last_valid` = NOW(),
						     `consecutive_failures` = 0,
						     `date_modified` = NOW()
						 WHERE `module_code` = '" . $this->db->escape($module_code) . "'"
					);
				}

				$this->logger->info('Activation: SUCCESS for ' . $module_code . ' domain=' . $domain);

				return ['success' => true, 'is_new' => $body['isNew'] ?? false];
			}
		}

		$this->logger->info('Activation: FAILED for ' . $module_code . ' HTTP=' . $http_code);

		return ['success' => false, 'error' => 'Activation failed. HTTP ' . $http_code];
	}

	public function validate(string $module_code, bool $force = false): array {
		$license = $this->getLicense($module_code);

		if (!$license || empty($license['license_key'])) {
			return ['valid' => false, 'reason' => 'no_license'];
		}

		$grace_days = $this->getGraceDays();

		if (!$force && $license['last_verified'] !== null) {
			$last_verified = strtotime($license['last_verified']);
			if ((time() - $last_verified) < self::VALIDATE_TTL) {
				if ($license['status'] === 'active' || $license['status'] === 'grace') {
					return [
						'valid' => true,
						'domain' => $license['domain'],
						'is_test' => (bool)$license['is_test'],
						'expires_at' => $license['expires_at'],
						'status' => $license['status'],
						'cached' => true,
					];
				}
				if (in_array($license['status'], ['revoked', 'expired', 'invalid'], true)) {
					return ['valid' => false, 'reason' => 'status_' . $license['status']];
				}
			}
		}

		$api_url = $this->getApiUrl();
		$domain = $this->getDomain();

		$payload = json_encode([
			'key' => $license['license_key'],
			'domain' => $domain,
			'moduleSku' => $license['sku'] ?? $module_code,
		]);

		$ch = curl_init($api_url . '/api/v1/license/validate');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
		$this->applyResolve($ch, $api_url . '/api/v1/license/validate');

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($response === false || $http_code === 0) {
			$this->recordFailure($module_code);

			$last_valid = $license['last_valid'] ? strtotime($license['last_valid']) : 0;
			$grace_seconds = $grace_days * 86400;
			$in_grace = $last_valid > 0 && (time() - $last_valid) < $grace_seconds;

			if ($in_grace) {
				$this->db->query(
					"UPDATE `" . DB_PREFIX . "dockercart_license`
					 SET `status` = 'grace',
					     `last_verified` = NOW(),
					     `date_modified` = NOW()
					 WHERE `module_code` = '" . $this->db->escape($module_code) . "'"
				);

				$this->logger->info('Validate: GRACE mode for ' . $module_code . ' (server unreachable)');

				return [
					'valid' => true,
					'domain' => $license['domain'],
					'is_test' => (bool)$license['is_test'],
					'expires_at' => $license['expires_at'],
					'status' => 'grace',
				];
			}

			$this->logger->info('Validate: NETWORK ERROR for ' . $module_code . ' (grace expired)');

			return ['valid' => false, 'reason' => 'network_error'];
		}

		$body = json_decode($response, true);

		if (!is_array($body)) {
			$this->recordFailure($module_code);

			return ['valid' => false, 'reason' => 'invalid_response'];
		}

		$this->db->query(
			"UPDATE `" . DB_PREFIX . "dockercart_license`
			 SET `last_verified` = NOW(),
			     `consecutive_failures` = 0,
			     `date_modified` = NOW()
			 WHERE `module_code` = '" . $this->db->escape($module_code) . "'"
		);

		if (!empty($body['valid'])) {
			$expires_at = isset($body['expiresAt']) ? $body['expiresAt'] : null;
			$expires_at_db = ($expires_at !== null) ? date('Y-m-d H:i:s', strtotime($expires_at)) : null;

			$this->db->query(
				"UPDATE `" . DB_PREFIX . "dockercart_license`
				 SET `status` = 'active',
				     `is_test` = " . (!empty($body['isTest']) ? '1' : '0') . ",
				     `expires_at` = " . ($expires_at_db !== null ? "'" . $this->db->escape($expires_at_db) . "'" : 'NULL') . ",
				     `domain` = '" . $this->db->escape($body['domain'] ?? $domain) . "',
				     `last_valid` = NOW(),
				     `date_modified` = NOW()
				 WHERE `module_code` = '" . $this->db->escape($module_code) . "'"
			);

			$this->logger->info('Validate: SUCCESS for ' . $module_code);

			return [
				'valid' => true,
				'domain' => $body['domain'] ?? $domain,
				'is_test' => !empty($body['isTest']),
				'expires_at' => $expires_at,
				'status' => 'active',
			];
		}

		$reason = $body['reason'] ?? 'unknown';

		$new_status = 'invalid';
		if (strpos($reason, 'revoked') !== false) {
			$new_status = 'revoked';
		} elseif (strpos($reason, 'expired') !== false) {
			$new_status = 'expired';
		} elseif ($reason === 'not_found') {
			$new_status = 'invalid';
		}

		$this->db->query(
			"UPDATE `" . DB_PREFIX . "dockercart_license`
			 SET `status` = '" . $this->db->escape($new_status) . "',
			     `date_modified` = NOW()
			 WHERE `module_code` = '" . $this->db->escape($module_code) . "'"
		);

		$this->logger->info('Validate: FAILED for ' . $module_code . ' reason=' . $reason);

		return ['valid' => false, 'reason' => $reason];
	}

	public function heartbeat(string $module_code): bool {
		$license = $this->getLicense($module_code);

		if (!$license || empty($license['license_key'])) {
			return false;
		}

		$api_url = $this->getApiUrl();
		$domain = $this->getDomain();

		$payload = json_encode([
			'key' => $license['license_key'],
			'domain' => $domain,
		]);

		$ch = curl_init($api_url . '/api/v1/license/heartbeat');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
		$this->applyResolve($ch, $api_url . '/api/v1/license/heartbeat');

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		return $http_code >= 200 && $http_code < 300;
	}

	public function check(string $module_code): bool {
		$result = $this->validate($module_code);

		if (!empty($result['valid'])) {
			return true;
		}

		$this->logger->info('Check: BLOCKED ' . $module_code . ' reason=' . ($result['reason'] ?? 'unknown'));

		return false;
	}

	public function autoPopulate(): int {
		$api_url = $this->getApiUrl();
		$domain = $this->getDomain();

		$ch = curl_init($api_url . '/api/v1/modules/by-domain?domain=' . urlencode($domain));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
		$this->applyResolve($ch, $api_url . '/api/v1/modules/by-domain?domain=' . urlencode($domain));

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($http_code !== 200 || $response === false) {
			$this->logger->info('AutoPopulate: HTTP ' . $http_code . ' for domain=' . $domain);

			return 0;
		}

		$body = json_decode($response, true);

		if (!is_array($body) || empty($body['success']) || !isset($body['data'])) {
			return 0;
		}

		$count = 0;

		foreach ($body['data'] as $item) {
			if (empty($item['sku']) || empty($item['licenseKey'])) {
				continue;
			}

			$sku = $item['sku'];
			$key = $item['licenseKey'];
			$module_code = $sku;

			$existing = $this->getLicense($module_code);

			if ($existing) {
				if ($existing['license_key'] !== $key) {
					$this->db->query(
						"UPDATE `" . DB_PREFIX . "dockercart_license`
						 SET `license_key` = '" . $this->db->escape($key) . "',
						     `sku` = '" . $this->db->escape($sku) . "',
						     `status` = 'unknown',
						     `last_verified` = NULL,
						     `last_valid` = NULL,
						     `consecutive_failures` = 0,
						     `is_test` = " . (!empty($item['isTest']) ? '1' : '0') . ",
						     `expires_at` = " . (isset($item['expiresAt']) && $item['expiresAt'] !== null ? "'" . $this->db->escape($item['expiresAt']) . "'" : 'NULL') . ",
						     `date_modified` = NOW()
						 WHERE `module_code` = '" . $this->db->escape($module_code) . "'"
					);
				}

				continue;
			}

			$expires_at = (isset($item['expiresAt']) && $item['expiresAt'] !== null)
				? $item['expiresAt']
				: null;

			$this->db->query(
				"INSERT INTO `" . DB_PREFIX . "dockercart_license`
				 SET `module_code` = '" . $this->db->escape($module_code) . "',
				     `sku` = '" . $this->db->escape($sku) . "',
				     `license_key` = '" . $this->db->escape($key) . "',
				     `domain` = '" . $this->db->escape($domain) . "',
				     `fingerprint` = '" . $this->db->escape($this->getFingerprint()) . "',
				     `status` = 'unknown',
				     `is_test` = " . (!empty($item['isTest']) ? '1' : '0') . ",
				     `expires_at` = " . ($expires_at !== null ? "'" . $this->db->escape($expires_at) . "'" : 'NULL') . ",
				     `date_added` = NOW(),
				     `date_modified` = NOW()"
			);

			$count++;
		}

		$this->logger->info('AutoPopulate: populated ' . $count . ' licenses for domain=' . $domain);

		return $count;
	}

	public function getAllLicenses(): array {
		$result = $this->db->query(
			"SELECT * FROM `" . DB_PREFIX . "dockercart_license`
			 ORDER BY `module_code` ASC"
		);

		return $result->rows;
	}

	public function removeLicense(string $module_code): void {
		$this->db->query(
			"DELETE FROM `" . DB_PREFIX . "dockercart_license`
			 WHERE `module_code` = '" . $this->db->escape($module_code) . "'"
		);
	}

	private function applyResolve($ch, string $url): void {
		$parsed = parse_url($url);
		$gateway_ip = @gethostbyname('host.docker.internal');

		if ($gateway_ip !== 'host.docker.internal') {
			curl_setopt($ch, CURLOPT_RESOLVE, array(
				$parsed['host'] . ':' . ($parsed['port'] ?? 80) . ':' . $gateway_ip,
			));
		}
	}

	private function recordFailure(string $module_code): void {
		$this->db->query(
			"UPDATE `" . DB_PREFIX . "dockercart_license`
			 SET `consecutive_failures` = `consecutive_failures` + 1,
			     `last_verified` = NOW(),
			     `date_modified` = NOW()
			 WHERE `module_code` = '" . $this->db->escape($module_code) . "'"
		);

		$grace_days = $this->getGraceDays();

		$result = $this->db->query(
			"SELECT `consecutive_failures`, `last_valid`
			 FROM `" . DB_PREFIX . "dockercart_license`
			 WHERE `module_code` = '" . $this->db->escape($module_code) . "'"
		);

		if ($result->num_rows && $result->row['last_valid']) {
			$last_valid = strtotime($result->row['last_valid']);
			$grace_seconds = $grace_days * 86400;

			if ((time() - $last_valid) >= $grace_seconds) {
				$this->db->query(
					"UPDATE `" . DB_PREFIX . "dockercart_license`
					 SET `status` = 'invalid'
					 WHERE `module_code` = '" . $this->db->escape($module_code) . "'"
				);

				$this->logger->info('Validate: GRACE EXPIRED for ' . $module_code);
			}
		}
	}

	private function maskKey(string $key): string {
		if (strlen($key) < 20) {
			return substr($key, 0, 8) . '...';
		}

		return substr($key, 0, 12) . '...' . substr($key, -8);
	}
}
