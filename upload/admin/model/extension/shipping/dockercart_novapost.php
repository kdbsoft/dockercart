<?php
/**
 * DockerCart NovaPost Shipping Model (Admin)
 * Handles division sync, CRUD, and statistics.
 */

$novapostAutoloader = DIR_STORAGE . 'vendor/autoload.php';
if (is_file($novapostAutoloader)) {
	require_once $novapostAutoloader;
}

class ModelExtensionShippingDockercartNovapost extends Model {

	private $schema_ensured = false;

	const SUPPORTED_COUNTRIES = [
		'CZ' => 'Czech Republic',
		'DE' => 'Germany',
		'EE' => 'Estonia',
		'ES' => 'Spain',
		'FR' => 'France',
		'GB' => 'United Kingdom',
		'HU' => 'Hungary',
		'IT' => 'Italy',
		'LT' => 'Lithuania',
		'LV' => 'Latvia',
		'MD' => 'Moldova',
		'NL' => 'Netherlands',
		'PL' => 'Poland',
		'RO' => 'Romania',
		'SK' => 'Slovakia',
		'UA' => 'Ukraine',
	];

	const DIVISION_CATEGORIES = [
		'CargoBranch',
		'PostBranch',
		'Postomat',
		'PUDO',
	];

	public function install() {
		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "dockercart_novapost_division` (
				`division_id` INT(11) NOT NULL AUTO_INCREMENT,
				`site_key` VARCHAR(64) NOT NULL DEFAULT '',
				`number` VARCHAR(32) NOT NULL DEFAULT '',
				`type` VARCHAR(32) NOT NULL DEFAULT '',
				`category` VARCHAR(32) NOT NULL DEFAULT '',
				`name` VARCHAR(255) NOT NULL DEFAULT '',
				`short_address` VARCHAR(512) NOT NULL DEFAULT '',
				`full_address` TEXT,
				`city_ref` VARCHAR(64) NOT NULL DEFAULT '',
				`city_name` VARCHAR(255) NOT NULL DEFAULT '',
				`region_ref` VARCHAR(64) NOT NULL DEFAULT '',
				`region_name` VARCHAR(255) NOT NULL DEFAULT '',
				`country_code` VARCHAR(2) NOT NULL DEFAULT '',
				`latitude` DECIMAL(10,7) DEFAULT NULL,
				`longitude` DECIMAL(10,7) DEFAULT NULL,
				`phone` VARCHAR(64) NOT NULL DEFAULT '',
				`schedule` JSON DEFAULT NULL,
				`max_weight` INT(11) DEFAULT NULL,
				`enabled` TINYINT(1) NOT NULL DEFAULT '1',
				`date_added` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`date_modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (`division_id`),
				UNIQUE KEY `uk_site_key` (`site_key`),
				KEY `idx_country_code` (`country_code`),
				KEY `idx_category` (`category`),
				KEY `idx_city_name` (`city_name`(100)),
				KEY `idx_region_name` (`region_name`(100)),
				KEY `idx_enabled` (`enabled`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
		");

		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "dockercart_novapost_sync_log` (
				`log_id` INT(11) NOT NULL AUTO_INCREMENT,
				`status` VARCHAR(32) NOT NULL DEFAULT '',
				`total_loaded` INT(11) NOT NULL DEFAULT '0',
				`total_errors` INT(11) NOT NULL DEFAULT '0',
				`countries` VARCHAR(255) NOT NULL DEFAULT '',
				`categories` VARCHAR(255) NOT NULL DEFAULT '',
				`started_at` DATETIME NOT NULL,
				`finished_at` DATETIME DEFAULT NULL,
				`error_message` TEXT,
				PRIMARY KEY (`log_id`),
				KEY `idx_status` (`status`),
				KEY `idx_started_at` (`started_at`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
		");
	}

	public function uninstall() {
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "dockercart_novapost_sync_log`");
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "dockercart_novapost_division`");
	}

	private function ensureSchema() {
		if ($this->schema_ensured) {
			return;
		}
		$this->schema_ensured = true;

		$table = DB_PREFIX . 'dockercart_novapost_division';
		$check = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape($table) . "'");
		if (!$check->num_rows) {
			$this->install();
		}
	}

	public function syncDivisions(string $apiKey, bool $sandbox, array $countryCodes, array $categories): array {
		$this->ensureSchema();

		$logId = $this->logSyncStart('running', $countryCodes, $categories);

		$result = [
			'total_loaded' => 0,
			'total_errors' => 0,
			'errors'       => [],
		];

		try {
			$factory = new \NovaDigital\NovaPost\NovaPostApiFactory();
			$novaPostApi = $factory(apiKey: $apiKey, useSandbox: $sandbox);

			$this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "dockercart_novapost_division`");

			foreach ($countryCodes as $countryCode) {
				try {
					$page = 1;

					$lastPage = 1;

					while (true) {
						$response = $novaPostApi->divisions()->get([
							'countryCodes'       => [$countryCode],
							'divisionCategories' => $categories,
							'page'               => $page,
						]);

						if ($page === 1) {
							$lastPage = $response['last_page'] ?? 1;
						}

						$divisions = $response['items'] ?? [];

						if (empty($divisions)) {
							break;
						}

						foreach ($divisions as $division) {
							try {
								$this->saveDivision($division);
								$result['total_loaded']++;
							} catch (\Exception $e) {
								$result['total_errors']++;
								$result['errors'][] = sprintf(
									'Error saving division %s: %s',
									$division['id'] ?? $division['site_key'] ?? 'unknown',
									$e->getMessage()
								);
							}
						}

						if ($page >= $lastPage) {
							break;
						}

						$page++;
					}
				} catch (\Exception $e) {
					$result['total_errors']++;
					$result['errors'][] = sprintf(
						'Error fetching divisions for %s: %s',
						$countryCode,
						$e->getMessage()
					);
				}
			}

			$status = $result['total_errors'] > 0 ? 'partial' : 'success';
			$this->logSyncComplete($logId, $status, $result['total_loaded'], $result['total_errors']);

			$this->updateLastSyncDate();

		} catch (\Exception $e) {
			$result['total_errors']++;
			$result['errors'][] = $e->getMessage();
			$this->logSyncComplete($logId, 'failed', $result['total_loaded'], $result['total_errors'], $e->getMessage());
		}

		return $result;
	}

	private function saveDivision(array $division): void {
		$siteKey   = $this->db->escape((string)($division['id'] ?? $division['site_key'] ?? ''));
		$number    = $this->db->escape($division['number'] ?? '');
		$type      = $this->db->escape($division['divisionCategory'] ?? $division['type'] ?? '');
		$category  = $this->db->escape($division['divisionCategory'] ?? $division['category'] ?? '');
		$name      = $this->db->escape($division['name'] ?? '');
		$shortAddr = $this->db->escape($division['shortName'] ?? $division['shortAddress'] ?? $division['short_address'] ?? '');
		$fullAddr  = $this->db->escape($division['address'] ?? $division['fullAddress'] ?? $division['full_address'] ?? '');
		$cityRef   = $this->db->escape((string)($division['settlement']['id'] ?? $division['cityRef'] ?? $division['city_ref'] ?? ''));
		$cityName  = $this->db->escape($division['settlement']['name'] ?? $division['cityName'] ?? $division['city_name'] ?? '');
		$regionRef = $this->db->escape((string)($division['settlement']['region']['id'] ?? $division['regionRef'] ?? $division['region_ref'] ?? ''));
		$regionNm  = $this->db->escape($division['settlement']['region']['name'] ?? $division['regionName'] ?? $division['region_name'] ?? '');
		$country   = $this->db->escape($division['countryCode'] ?? $division['country_code'] ?? '');
		$phone     = $this->db->escape(is_array($division['publicPhones'] ?? null) ? ($division['publicPhones'][0] ?? '') : ($division['phone'] ?? ''));
		$enabled   = ($division['status'] ?? '') === 'Working' ? 1 : (!empty($division['enabled'] ?? '') ? 1 : 0);
		$maxWeight = isset($division['maxWeightPlaceSender'])
			? (int)$division['maxWeightPlaceSender']
			: (isset($division['maxWeight'])
				? (int)$division['maxWeight']
				: (isset($division['max_weight']) ? (int)$division['max_weight'] : 'NULL'));

		$lat = isset($division['latitude']) && $division['latitude'] !== '' ? "'" . (float)$division['latitude'] . "'" : 'NULL';
		$lon = isset($division['longitude']) && $division['longitude'] !== '' ? "'" . (float)$division['longitude'] . "'" : 'NULL';

		$schedule = 'NULL';
		$scheduleData = $division['workSchedule'] ?? $division['schedule'] ?? null;
		if (is_array($scheduleData)) {
			$schedule = "'" . $this->db->escape(json_encode($scheduleData)) . "'";
		} elseif (is_string($scheduleData) && $scheduleData !== '') {
			$schedule = "'" . $this->db->escape($scheduleData) . "'";
		}

		$maxWeightSql = $maxWeight === 'NULL' ? 'NULL' : "'" . (int)$maxWeight . "'";

		$this->db->query("
			INSERT INTO `" . DB_PREFIX . "dockercart_novapost_division` SET
				`site_key` = '" . $siteKey . "',
				`number` = '" . $number . "',
				`type` = '" . $type . "',
				`category` = '" . $category . "',
				`name` = '" . $name . "',
				`short_address` = '" . $shortAddr . "',
				`full_address` = '" . $fullAddr . "',
				`city_ref` = '" . $cityRef . "',
				`city_name` = '" . $cityName . "',
				`region_ref` = '" . $regionRef . "',
				`region_name` = '" . $regionNm . "',
				`country_code` = '" . $country . "',
				`latitude` = " . $lat . ",
				`longitude` = " . $lon . ",
				`phone` = '" . $phone . "',
				`schedule` = " . $schedule . ",
				`max_weight` = " . $maxWeightSql . ",
				`enabled` = '" . $enabled . "'
			ON DUPLICATE KEY UPDATE
				`number` = VALUES(`number`),
				`type` = VALUES(`type`),
				`category` = VALUES(`category`),
				`name` = VALUES(`name`),
				`short_address` = VALUES(`short_address`),
				`full_address` = VALUES(`full_address`),
				`city_ref` = VALUES(`city_ref`),
				`city_name` = VALUES(`city_name`),
				`region_ref` = VALUES(`region_ref`),
				`region_name` = VALUES(`region_name`),
				`country_code` = VALUES(`country_code`),
				`latitude` = VALUES(`latitude`),
				`longitude` = VALUES(`longitude`),
				`phone` = VALUES(`phone`),
				`schedule` = VALUES(`schedule`),
				`max_weight` = VALUES(`max_weight`),
				`enabled` = VALUES(`enabled`),
				`date_modified` = NOW()
		");
	}

	public function getTotalDivisions(): int {
		$this->ensureSchema();
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "dockercart_novapost_division`");
		return (int)$query->row['total'];
	}

	public function getDivisionsByCountry(): array {
		$this->ensureSchema();
		$query = $this->db->query("
			SELECT country_code, COUNT(*) AS total
			FROM `" . DB_PREFIX . "dockercart_novapost_division`
			WHERE enabled = '1'
			GROUP BY country_code
			ORDER BY country_code ASC
		");
		return $query->rows;
	}

	public function getDivisionsByCategory(): array {
		$this->ensureSchema();
		$query = $this->db->query("
			SELECT category, COUNT(*) AS total
			FROM `" . DB_PREFIX . "dockercart_novapost_division`
			WHERE enabled = '1'
			GROUP BY category
			ORDER BY category ASC
		");
		return $query->rows;
	}

	public function getLastSync(): ?array {
		$this->ensureSchema();
		$query = $this->db->query("
			SELECT * FROM `" . DB_PREFIX . "dockercart_novapost_sync_log`
			ORDER BY log_id DESC LIMIT 1
		");
		return $query->num_rows ? $query->row : null;
	}

	public function getSyncLogs(int $start = 0, int $limit = 20): array {
		$this->ensureSchema();
		$query = $this->db->query("
			SELECT * FROM `" . DB_PREFIX . "dockercart_novapost_sync_log`
			ORDER BY started_at DESC
			LIMIT " . (int)$start . "," . (int)$limit
		);
		return $query->rows;
	}

	public function getTotalSyncLogs(): int {
		$this->ensureSchema();
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "dockercart_novapost_sync_log`");
		return (int)$query->row['total'];
	}

	public function getDivisions(array $data = []): array {
		$this->ensureSchema();

		$sql = "SELECT * FROM `" . DB_PREFIX . "dockercart_novapost_division` WHERE 1=1";

		if (!empty($data['filter_country'])) {
			$sql .= " AND country_code = '" . $this->db->escape($data['filter_country']) . "'";
		}
		if (!empty($data['filter_category'])) {
			$sql .= " AND category = '" . $this->db->escape($data['filter_category']) . "'";
		}
		if (!empty($data['filter_city'])) {
			$sql .= " AND city_name LIKE '%" . $this->db->escape($data['filter_city']) . "%'";
		}
		if (isset($data['filter_enabled'])) {
			$sql .= " AND enabled = '" . (int)$data['filter_enabled'] . "'";
		}
		if (!empty($data['filter_search'])) {
			$search = $this->db->escape($data['filter_search']);
			$sql .= " AND (name LIKE '%" . $search . "%' OR short_address LIKE '%" . $search . "%' OR city_name LIKE '%" . $search . "%')";
		}

		$sortAllow = ['name', 'city_name', 'country_code', 'category', 'date_added', 'date_modified'];
		$sort = !empty($data['sort']) && in_array($data['sort'], $sortAllow) ? $data['sort'] : 'name';
		$order = !empty($data['order']) && in_array(strtoupper($data['order']), ['ASC', 'DESC']) ? $data['order'] : 'ASC';
		$sql .= " ORDER BY " . $sort . " " . $order;

		if (isset($data['start']) || isset($data['limit'])) {
			$start = $data['start'] < 0 ? 0 : (int)$data['start'];
			$limit = $data['limit'] < 1 ? 20 : (int)$data['limit'];
			$sql .= " LIMIT " . $start . "," . $limit;
		}

		$query = $this->db->query($sql);
		return $query->rows;
	}

	public function getTotalDivisionsFiltered(array $data = []): int {
		$this->ensureSchema();

		$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "dockercart_novapost_division` WHERE 1=1";

		if (!empty($data['filter_country'])) {
			$sql .= " AND country_code = '" . $this->db->escape($data['filter_country']) . "'";
		}
		if (!empty($data['filter_category'])) {
			$sql .= " AND category = '" . $this->db->escape($data['filter_category']) . "'";
		}
		if (!empty($data['filter_city'])) {
			$sql .= " AND city_name LIKE '%" . $this->db->escape($data['filter_city']) . "%'";
		}
		if (isset($data['filter_enabled'])) {
			$sql .= " AND enabled = '" . (int)$data['filter_enabled'] . "'";
		}
		if (!empty($data['filter_search'])) {
			$search = $this->db->escape($data['filter_search']);
			$sql .= " AND (name LIKE '%" . $search . "%' OR short_address LIKE '%" . $search . "%' OR city_name LIKE '%" . $search . "%')";
		}

		$query = $this->db->query($sql);
		return (int)$query->row['total'];
	}

	public function getDivision(int $division_id): ?array {
		$this->ensureSchema();
		$query = $this->db->query("
			SELECT * FROM `" . DB_PREFIX . "dockercart_novapost_division`
			WHERE division_id = '" . (int)$division_id . "'
		");
		return $query->num_rows ? $query->row : null;
	}

	public function getDivisionsByCity(string $cityName, string $countryCode = ''): array {
		$this->ensureSchema();
		$sql = "SELECT * FROM `" . DB_PREFIX . "dockercart_novapost_division` WHERE city_name = '" . $this->db->escape($cityName) . "' AND enabled = '1'";
		if ($countryCode !== '') {
			$sql .= " AND country_code = '" . $this->db->escape($countryCode) . "'";
		}
		$sql .= " ORDER BY name ASC";
		$query = $this->db->query($sql);
		return $query->rows;
	}

	public function searchDivisions(string $query, string $countryCode = '', int $limit = 20): array {
		$this->ensureSchema();
		$sql = "SELECT * FROM `" . DB_PREFIX . "dockercart_novapost_division`
			WHERE enabled = '1'
			AND (name LIKE '%" . $this->db->escape($query) . "%'
				OR short_address LIKE '%" . $this->db->escape($query) . "%'
				OR city_name LIKE '%" . $this->db->escape($query) . "%')";
		if ($countryCode !== '') {
			$sql .= " AND country_code = '" . $this->db->escape($countryCode) . "'";
		}
		$sql .= " ORDER BY name ASC LIMIT " . (int)$limit;
		$result = $this->db->query($sql);
		return $result->rows;
	}

	private function logSyncStart(string $status, array $countryCodes, array $categories): int {
		$this->db->query("
			INSERT INTO `" . DB_PREFIX . "dockercart_novapost_sync_log` SET
				`status` = '" . $this->db->escape($status) . "',
				`countries` = '" . $this->db->escape(implode(',', $countryCodes)) . "',
				`categories` = '" . $this->db->escape(implode(',', $categories)) . "',
				`started_at` = NOW()
		");
		return (int)$this->db->getLastId();
	}

	private function logSyncComplete(int $logId, string $status, int $loaded, int $errors, string $errorMessage = ''): void {
		$this->db->query("
			UPDATE `" . DB_PREFIX . "dockercart_novapost_sync_log` SET
				`status` = '" . $this->db->escape($status) . "',
				`total_loaded` = '" . (int)$loaded . "',
				`total_errors` = '" . (int)$errors . "',
				`finished_at` = NOW(),
				`error_message` = '" . $this->db->escape($errorMessage) . "'
			WHERE log_id = '" . (int)$logId . "'
		");
	}

	private function updateLastSyncDate(): void {
		$this->load->model('setting/setting');
		$this->db->query("
			INSERT INTO `" . DB_PREFIX . "setting` (`store_id`, `code`, `key`, `value`, `serialized`)
			VALUES (0, 'shipping_dockercart_novapost', 'shipping_dockercart_novapost_sync_date', NOW(), 0)
			ON DUPLICATE KEY UPDATE `value` = NOW()
		");
	}
}
