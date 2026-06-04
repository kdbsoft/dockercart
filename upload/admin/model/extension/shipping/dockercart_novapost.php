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
	private array $discoveredRegions = [];

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

	const DELIVERY_TYPES = [
		'branch'  => 'Branch (В отделение)',
		'locker'  => 'Locker (В почтомат)',
		'courier' => 'Courier (Курьером)',
	];

	private function getLanguageHeader(string $code): string {
		$map = [
			'en-gb' => 'en',
			'ru-ua' => 'ru',
			'uk-ua' => 'uk',
		];
		return $map[$code] ?? 'en';
	}

	private function getActiveLanguages(): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "language` WHERE `status` = '1' ORDER BY `sort_order` ASC");
		return $query->rows;
	}

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

		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "dockercart_novapost_division_description` (
				`division_id` INT(11) NOT NULL,
				`language_id` INT(11) NOT NULL,
				`name` VARCHAR(255) NOT NULL DEFAULT '',
				`short_address` VARCHAR(512) NOT NULL DEFAULT '',
				`full_address` TEXT,
				`city_name` VARCHAR(255) NOT NULL DEFAULT '',
				`region_name` VARCHAR(255) NOT NULL DEFAULT '',
				PRIMARY KEY (`division_id`, `language_id`),
				KEY `lang_idx` (`language_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
		");

		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "dockercart_novapost_tariff` (
				`tariff_id` INT(11) NOT NULL AUTO_INCREMENT,
				`country_code` VARCHAR(2) NOT NULL DEFAULT '',
				`delivery_type` VARCHAR(32) NOT NULL DEFAULT '',
				`weight_from` DECIMAL(10,3) NOT NULL DEFAULT '0.000',
				`weight_to` DECIMAL(10,3) NOT NULL DEFAULT '0.000',
				`cost` DECIMAL(10,4) NOT NULL DEFAULT '0.0000',
				`free_shipping_from` DECIMAL(10,4) DEFAULT NULL,
				`status` TINYINT(1) NOT NULL DEFAULT '1',
				`sort_order` INT(11) NOT NULL DEFAULT '0',
				`date_added` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`date_modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (`tariff_id`),
				KEY `idx_country_delivery` (`country_code`, `delivery_type`),
				KEY `idx_status` (`status`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
		");

		// Add parent region columns to division table (idempotent)
		$this->db->query("ALTER TABLE `" . DB_PREFIX . "dockercart_novapost_division`
			ADD COLUMN IF NOT EXISTS `parent_region_id` VARCHAR(64) NOT NULL DEFAULT '' AFTER `region_name`,
			ADD COLUMN IF NOT EXISTS `parent_region_name` VARCHAR(255) NOT NULL DEFAULT '' AFTER `parent_region_id`
		");

		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "dockercart_novapost_region_map` (
				`region_map_id` INT(11) NOT NULL AUTO_INCREMENT,
				`novapost_region_id` VARCHAR(64) NOT NULL DEFAULT '',
				`country_code` VARCHAR(2) NOT NULL DEFAULT '',
				`novapost_region_name` VARCHAR(255) NOT NULL DEFAULT '',
				`city_name` VARCHAR(255) NOT NULL DEFAULT '',
				`oc_zone_id` INT(11) NOT NULL DEFAULT '0',
				`date_added` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`date_modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (`region_map_id`),
				UNIQUE KEY `uk_np_region` (`novapost_region_id`, `country_code`, `city_name`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
		");

		// Add city_name column if upgrading from old schema
		$this->db->query("ALTER TABLE `" . DB_PREFIX . "dockercart_novapost_region_map`
			ADD COLUMN IF NOT EXISTS `city_name` VARCHAR(255) NOT NULL DEFAULT '' AFTER `novapost_region_name`,
			DROP INDEX IF EXISTS `uk_np_region`,
			ADD UNIQUE KEY `uk_np_region` (`novapost_region_id`, `country_code`, `city_name`)
		");

		// Add parent_region_name to description table (idempotent)
		$this->db->query("ALTER TABLE `" . DB_PREFIX . "dockercart_novapost_division_description`
			ADD COLUMN IF NOT EXISTS `parent_region_name` VARCHAR(255) NOT NULL DEFAULT '' AFTER `region_name`
		");
	}

	public function uninstall() {
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "dockercart_novapost_region_map`");
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "dockercart_novapost_tariff`");
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "dockercart_novapost_division_description`");
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

		$descTable = DB_PREFIX . 'dockercart_novapost_division_description';
		$descCheck = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape($descTable) . "'");
		if (!$descCheck->num_rows) {
			$this->db->query("
				CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "dockercart_novapost_division_description` (
					`division_id` INT(11) NOT NULL,
					`language_id` INT(11) NOT NULL,
					`name` VARCHAR(255) NOT NULL DEFAULT '',
					`short_address` VARCHAR(512) NOT NULL DEFAULT '',
					`full_address` TEXT,
					`city_name` VARCHAR(255) NOT NULL DEFAULT '',
					`region_name` VARCHAR(255) NOT NULL DEFAULT '',
					PRIMARY KEY (`division_id`, `language_id`),
					KEY `lang_idx` (`language_id`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
			");
		}

		$this->db->query("ALTER TABLE `" . DB_PREFIX . "dockercart_novapost_division_description`
			ADD COLUMN IF NOT EXISTS `parent_region_name` VARCHAR(255) NOT NULL DEFAULT '' AFTER `region_name`
		");
	}

	public function syncDivisions(string $apiKey, bool $sandbox, array $countryCodes, array $categories): array {
		set_time_limit(0);
		ignore_user_abort(true);

		$this->ensureSchema();

		$logId = $this->logSyncStart('running', $countryCodes, $categories);

		$result = [
			'total_loaded' => 0,
			'total_errors' => 0,
			'errors'       => [],
		];

		try {
			$languages = $this->getActiveLanguages();
			if (empty($languages)) {
				throw new \Exception('No active languages found in the system');
			}

			$this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "dockercart_novapost_division`");
			$this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_novapost_division_description`");

			$factory = new \NovaDigital\NovaPost\NovaPostApiFactory();

			// First pass: English for ALL countries (populates main table + EN descriptions)
			// Subsequent passes: other languages for ALL countries (populates descriptions only)
			foreach ($languages as $langIndex => $language) {
				$isFirstLanguage = $langIndex === 0;
				$acceptLanguage = $this->getLanguageHeader($language['code']);

				$containerBuilder = new \NovaDigital\NovaPost\DI\ContainerBuilder();
				$containerBuilder->setParameter('config', [
					'headers' => ['Accept-Language' => $acceptLanguage],
				]);

				$novaPostApi = $factory(
					apiKey: $apiKey,
					useSandbox: $sandbox,
					containerBuilder: $containerBuilder
				);

				foreach ($countryCodes as $countryCode) {
					try {
						$page = 1;
						$lastPage = 1;

						while (true) {
							$response = $novaPostApi->divisions()->get([
								'countryCodes'       => [$countryCode],
								'divisionCategories' => $categories,
								'page'               => $page,
								'limit'              => 100,
							]);

							if ($page === 1) {
								$lastPage = $response['last_page'] ?? 1;
							}

							$divisions = $response['items'] ?? [];

							if (empty($divisions)) {
								if ($page === 1) {
									$result['errors'][] = sprintf(
										'No divisions returned for %s (%s)',
										$countryCode,
										$acceptLanguage
									);
								}
								break;
							}

							foreach ($divisions as $division) {
								try {
									if ($isFirstLanguage) {
										$divisionId = $this->saveDivision($division);
										$result['total_loaded']++;
									} else {
										$divisionId = $this->findDivisionIdBySiteKey(
											$division['id'] ?? $division['site_key'] ?? ''
										);
									}

									if ($divisionId) {
										$this->saveDivisionDescription(
											$divisionId,
											(int)$language['language_id'],
											$division
										);
									}
								} catch (\Exception $e) {
									$result['total_errors']++;
									$result['errors'][] = sprintf(
										'Error saving division %s (%s): %s',
										$division['id'] ?? $division['site_key'] ?? 'unknown',
										$acceptLanguage,
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
							'Error fetching divisions for %s (%s): %s',
							$countryCode,
							$acceptLanguage,
							$e->getMessage()
						);
					}
				}
			}

			$this->flushDiscoveredRegions();
			$this->flushDiscoveredCities();
			$this->applyDefaultMappings();

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

	private function saveDivision(array $division): int {
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
		$parentRegionId   = $this->db->escape((string)($division['settlement']['region']['parent']['id'] ?? $division['parentRegionId'] ?? $division['parent_region_id'] ?? ''));
		$parentRegionName = $this->db->escape($division['settlement']['region']['parent']['name'] ?? $division['parentRegionName'] ?? $division['parent_region_name'] ?? '');
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
				`parent_region_id` = '" . $parentRegionId . "',
				`parent_region_name` = '" . $parentRegionName . "',
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
				`parent_region_id` = VALUES(`parent_region_id`),
				`parent_region_name` = VALUES(`parent_region_name`),
				`country_code` = VALUES(`country_code`),
				`latitude` = VALUES(`latitude`),
				`longitude` = VALUES(`longitude`),
				`phone` = VALUES(`phone`),
				`schedule` = VALUES(`schedule`),
				`max_weight` = VALUES(`max_weight`),
				`enabled` = VALUES(`enabled`),
				`date_modified` = NOW()
		");

		$divisionId = (int)$this->db->getLastId();
		if ($divisionId === 0) {
			$query = $this->db->query("
				SELECT `division_id`
				FROM `" . DB_PREFIX . "dockercart_novapost_division`
				WHERE `site_key` = '" . $siteKey . "'
				LIMIT 1
			");
			$divisionId = (int)$query->row['division_id'];
		}

		// Track discovered parent regions for region_map
		if ($parentRegionId !== "''" && $parentRegionId !== '') {
			$key = $country . '|' . $parentRegionId;
			if (!isset($this->discoveredRegions[$key])) {
				$this->discoveredRegions[$key] = [
					'novapost_region_id'   => $parentRegionId,
					'country_code'         => $country,
					'novapost_region_name' => $parentRegionName !== "''" ? $parentRegionName : "''",
				];
			}
		}

		return $divisionId;
	}

	private function findDivisionIdBySiteKey(string $siteKey): ?int {
		if (empty($siteKey)) {
			return null;
		}
		$query = $this->db->query("
			SELECT `division_id`
			FROM `" . DB_PREFIX . "dockercart_novapost_division`
			WHERE `site_key` = '" . $this->db->escape($siteKey) . "'
			LIMIT 1
		");
		return $query->num_rows ? (int)$query->row['division_id'] : null;
	}

	private function saveDivisionDescription(int $divisionId, int $languageId, array $division): void {
		$name      = $this->db->escape($division['name'] ?? '');
		$shortAddr = $this->db->escape($division['shortName'] ?? $division['shortAddress'] ?? $division['short_address'] ?? '');
		$fullAddr  = $this->db->escape($division['address'] ?? $division['fullAddress'] ?? $division['full_address'] ?? '');
		$cityName  = $this->db->escape($division['settlement']['name'] ?? $division['cityName'] ?? $division['city_name'] ?? '');
		$regionNm  = $this->db->escape($division['settlement']['region']['name'] ?? $division['regionName'] ?? $division['region_name'] ?? '');
		$parentRegionName = $this->db->escape($division['settlement']['region']['parent']['name'] ?? $division['parentRegionName'] ?? $division['parent_region_name'] ?? '');

		$this->db->query("
			INSERT INTO `" . DB_PREFIX . "dockercart_novapost_division_description` SET
				`division_id` = '" . (int)$divisionId . "',
				`language_id` = '" . (int)$languageId . "',
				`name` = '" . $name . "',
				`short_address` = '" . $shortAddr . "',
				`full_address` = '" . $fullAddr . "',
				`city_name` = '" . $cityName . "',
				`region_name` = '" . $regionNm . "',
				`parent_region_name` = '" . $parentRegionName . "'
			ON DUPLICATE KEY UPDATE
				`name` = VALUES(`name`),
				`short_address` = VALUES(`short_address`),
				`full_address` = VALUES(`full_address`),
				`city_name` = VALUES(`city_name`),
				`region_name` = VALUES(`region_name`),
				`parent_region_name` = VALUES(`parent_region_name`)
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

		$languageId = (int)$this->config->get('config_language_id');

		$sql = "SELECT m.*,
			COALESCE(d.`name`, m.`name`) AS `name`,
			COALESCE(d.`short_address`, m.`short_address`) AS `short_address`,
			COALESCE(d.`full_address`, m.`full_address`) AS `full_address`,
			COALESCE(d.`city_name`, m.`city_name`) AS `city_name`,
			COALESCE(d.`region_name`, m.`region_name`) AS `region_name`
			FROM `" . DB_PREFIX . "dockercart_novapost_division` m
			LEFT JOIN `" . DB_PREFIX . "dockercart_novapost_division_description` d
				ON d.`division_id` = m.`division_id` AND d.`language_id` = '" . $languageId . "'
			WHERE 1=1";

		if (!empty($data['filter_country'])) {
			$sql .= " AND m.country_code = '" . $this->db->escape($data['filter_country']) . "'";
		}
		if (!empty($data['filter_category'])) {
			$sql .= " AND m.category = '" . $this->db->escape($data['filter_category']) . "'";
		}
		if (!empty($data['filter_city'])) {
			$sql .= " AND (m.city_name LIKE '%" . $this->db->escape($data['filter_city']) . "%' OR d.city_name LIKE '%" . $this->db->escape($data['filter_city']) . "%')";
		}
		if (isset($data['filter_enabled'])) {
			$sql .= " AND m.enabled = '" . (int)$data['filter_enabled'] . "'";
		}
		if (!empty($data['filter_search'])) {
			$search = $this->db->escape($data['filter_search']);
			$sql .= " AND (m.name LIKE '%" . $search . "%' OR d.name LIKE '%" . $search . "%'
				OR m.short_address LIKE '%" . $search . "%' OR d.short_address LIKE '%" . $search . "%'
				OR m.city_name LIKE '%" . $search . "%' OR d.city_name LIKE '%" . $search . "%')";
		}

		$sortAllow = ['name', 'city_name', 'country_code', 'category', 'date_added', 'date_modified'];
		$sort = !empty($data['sort']) && in_array($data['sort'], $sortAllow) ? $data['sort'] : 'name';
		$order = !empty($data['order']) && in_array(strtoupper($data['order']), ['ASC', 'DESC']) ? $data['order'] : 'ASC';

		if (in_array($sort, ['name', 'city_name'])) {
			$sort = "COALESCE(d.`" . $sort . "`, m.`" . $sort . "`)";
		} else {
			$sort = "m.`" . $sort . "`";
		}

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

		$languageId = (int)$this->config->get('config_language_id');

		$sql = "SELECT COUNT(*) AS total
			FROM `" . DB_PREFIX . "dockercart_novapost_division` m
			LEFT JOIN `" . DB_PREFIX . "dockercart_novapost_division_description` d
				ON d.`division_id` = m.`division_id` AND d.`language_id` = '" . $languageId . "'
			WHERE 1=1";

		if (!empty($data['filter_country'])) {
			$sql .= " AND m.country_code = '" . $this->db->escape($data['filter_country']) . "'";
		}
		if (!empty($data['filter_category'])) {
			$sql .= " AND m.category = '" . $this->db->escape($data['filter_category']) . "'";
		}
		if (!empty($data['filter_city'])) {
			$sql .= " AND (m.city_name LIKE '%" . $this->db->escape($data['filter_city']) . "%' OR d.city_name LIKE '%" . $this->db->escape($data['filter_city']) . "%')";
		}
		if (isset($data['filter_enabled'])) {
			$sql .= " AND m.enabled = '" . (int)$data['filter_enabled'] . "'";
		}
		if (!empty($data['filter_search'])) {
			$search = $this->db->escape($data['filter_search']);
			$sql .= " AND (m.name LIKE '%" . $search . "%' OR d.name LIKE '%" . $search . "%'
				OR m.short_address LIKE '%" . $search . "%' OR d.short_address LIKE '%" . $search . "%'
				OR m.city_name LIKE '%" . $search . "%' OR d.city_name LIKE '%" . $search . "%')";
		}

		$query = $this->db->query($sql);
		return (int)$query->row['total'];
	}

	public function getDivision(int $division_id): ?array {
		$this->ensureSchema();
		$languageId = (int)$this->config->get('config_language_id');
		$query = $this->db->query("
			SELECT m.*,
				COALESCE(d.`name`, m.`name`) AS `name`,
				COALESCE(d.`short_address`, m.`short_address`) AS `short_address`,
				COALESCE(d.`full_address`, m.`full_address`) AS `full_address`,
				COALESCE(d.`city_name`, m.`city_name`) AS `city_name`,
				COALESCE(d.`region_name`, m.`region_name`) AS `region_name`
			FROM `" . DB_PREFIX . "dockercart_novapost_division` m
			LEFT JOIN `" . DB_PREFIX . "dockercart_novapost_division_description` d
				ON d.`division_id` = m.`division_id` AND d.`language_id` = '" . $languageId . "'
			WHERE m.division_id = '" . (int)$division_id . "'
		");
		return $query->num_rows ? $query->row : null;
	}

	public function getDivisionsByCity(string $cityName, string $countryCode = '', ?int $languageId = null): array {
		$this->ensureSchema();
		if ($languageId === null) {
			$languageId = (int)$this->config->get('config_language_id');
		}
		$sql = "SELECT m.*,
			COALESCE(d.`name`, m.`name`) AS `name`,
			COALESCE(d.`short_address`, m.`short_address`) AS `short_address`,
			COALESCE(d.`full_address`, m.`full_address`) AS `full_address`,
			COALESCE(d.`city_name`, m.`city_name`) AS `city_name`,
			COALESCE(d.`region_name`, m.`region_name`) AS `region_name`
			FROM `" . DB_PREFIX . "dockercart_novapost_division` m
			LEFT JOIN `" . DB_PREFIX . "dockercart_novapost_division_description` d
				ON d.`division_id` = m.`division_id` AND d.`language_id` = '" . (int)$languageId . "'
			WHERE (m.city_name = '" . $this->db->escape($cityName) . "' OR d.city_name = '" . $this->db->escape($cityName) . "')
			AND m.enabled = '1'";
		if ($countryCode !== '') {
			$sql .= " AND m.country_code = '" . $this->db->escape($countryCode) . "'";
		}
		$sql .= " ORDER BY COALESCE(d.`name`, m.`name`) ASC";
		$query = $this->db->query($sql);
		return $query->rows;
	}

	public function searchDivisions(string $query, string $countryCode = '', int $limit = 20, ?int $languageId = null): array {
		$this->ensureSchema();
		if ($languageId === null) {
			$languageId = (int)$this->config->get('config_language_id');
		}
		$sql = "SELECT m.*,
			COALESCE(d.`name`, m.`name`) AS `name`,
			COALESCE(d.`short_address`, m.`short_address`) AS `short_address`,
			COALESCE(d.`full_address`, m.`full_address`) AS `full_address`,
			COALESCE(d.`city_name`, m.`city_name`) AS `city_name`,
			COALESCE(d.`region_name`, m.`region_name`) AS `region_name`
			FROM `" . DB_PREFIX . "dockercart_novapost_division` m
			LEFT JOIN `" . DB_PREFIX . "dockercart_novapost_division_description` d
				ON d.`division_id` = m.`division_id` AND d.`language_id` = '" . (int)$languageId . "'
			WHERE m.enabled = '1'
			AND (m.name LIKE '%" . $this->db->escape($query) . "%'
				OR d.name LIKE '%" . $this->db->escape($query) . "%'
				OR m.short_address LIKE '%" . $this->db->escape($query) . "%'
				OR d.short_address LIKE '%" . $this->db->escape($query) . "%'
				OR m.city_name LIKE '%" . $this->db->escape($query) . "%'
				OR d.city_name LIKE '%" . $this->db->escape($query) . "%')";
		if ($countryCode !== '') {
			$sql .= " AND m.country_code = '" . $this->db->escape($countryCode) . "'";
		}
		$sql .= " ORDER BY COALESCE(d.`name`, m.`name`) ASC LIMIT " . (int)$limit;
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

	private function flushDiscoveredRegions(): void {
		if (empty($this->discoveredRegions)) {
			return;
		}

		$this->ensureRegionMapTable();

		foreach ($this->discoveredRegions as $region) {
			$this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "dockercart_novapost_region_map` SET
				`novapost_region_id` = '" . $region['novapost_region_id'] . "',
				`country_code` = '" . $region['country_code'] . "',
				`novapost_region_name` = '" . $region['novapost_region_name'] . "',
				`city_name` = ''
			");
		}

		$this->discoveredRegions = [];
	}

	private function flushDiscoveredCities(): void {
		$this->ensureRegionMapTable();

		$languageId = (int)$this->config->get('config_language_id');
		$result = $this->db->query("
			INSERT IGNORE INTO `" . DB_PREFIX . "dockercart_novapost_region_map`
				(`novapost_region_id`, `country_code`, `novapost_region_name`, `city_name`)
			SELECT DISTINCT m.city_ref, m.country_code, 'City-level mapping',
				COALESCE(NULLIF(d.city_name, ''), m.city_name)
			FROM `" . DB_PREFIX . "dockercart_novapost_division` m
			LEFT JOIN `" . DB_PREFIX . "dockercart_novapost_division_description` d
				ON d.division_id = m.division_id AND d.language_id = '" . (int)$languageId . "'
			WHERE m.parent_region_id = ''
				AND m.enabled = '1'
				AND NOT EXISTS (
					SELECT 1 FROM `" . DB_PREFIX . "dockercart_novapost_region_map` rm
					WHERE rm.city_name != ''
					AND rm.novapost_region_id = m.city_ref
					AND rm.country_code = m.country_code
				)
		");
	}

	private function applyDefaultMappings(): void {
		$this->db->query("
			INSERT INTO `" . DB_PREFIX . "dockercart_novapost_region_map`
				(`novapost_region_id`, `country_code`, `novapost_region_name`, `city_name`, `oc_zone_id`)
			VALUES
				('398', 'UA', 'Vinnytska oblast', '', '3501'),
				('399', 'UA', 'Volynska oblast', '', '3502'),
				('400', 'UA', 'Dnipropetrovska oblast', '', '3484'),
				('401', 'UA', 'Donetska oblast', '', '3485'),
				('402', 'UA', 'Zhytomyrska oblast', '', '3505'),
				('403', 'UA', 'Zakarpatska oblast', '', '3503'),
				('404', 'UA', 'Zaporizka oblast', '', '3504'),
				('405', 'UA', 'Ivano-Frankivska oblast', '', '3486'),
				('406', 'UA', 'Kyivska oblast', '', '3491'),
				('407', 'UA', 'Kirovohradska oblast', '', '3489'),
				('409', 'UA', 'Lvivska oblast', '', '3493'),
				('410', 'UA', 'Mykolaivska oblast', '', '3494'),
				('411', 'UA', 'Odeska oblast', '', '3495'),
				('412', 'UA', 'Poltavska oblast', '', '3496'),
				('413', 'UA', 'Rivnenska oblast', '', '3497'),
				('414', 'UA', 'Sumska oblast', '', '3499'),
				('415', 'UA', 'Ternopilska oblast', '', '3500'),
				('416', 'UA', 'Kharkivska oblast', '', '4224'),
				('417', 'UA', 'Khersonska oblast', '', '3487'),
				('418', 'UA', 'Khmelnytska oblast', '', '3488'),
				('419', 'UA', 'Cherkaska oblast', '', '3480'),
				('420', 'UA', 'Chernivetska oblast', '', '3482'),
				('421', 'UA', 'Chernihivska oblast', '', '3481')
			ON DUPLICATE KEY UPDATE `oc_zone_id` = VALUES(`oc_zone_id`)
		");

		$this->db->query("
			UPDATE `" . DB_PREFIX . "dockercart_novapost_region_map`
			SET `oc_zone_id` = CASE `novapost_region_id`
				WHEN '110024' THEN '3491'
				WHEN '112225' THEN '3501'
				WHEN '114833' THEN '3484'
				WHEN '115640' THEN '3505'
				WHEN '116260' THEN '3504'
				WHEN '117153' THEN '3486'
				WHEN '118064' THEN '3490'
				WHEN '119794' THEN '3489'
				WHEN '121270' THEN '3502'
				WHEN '121300' THEN '3493'
				WHEN '122635' THEN '3494'
				WHEN '125394' THEN '3495'
				WHEN '127808' THEN '3496'
				WHEN '128845' THEN '3497'
				WHEN '131868' THEN '3499'
				WHEN '132363' THEN '3500'
				WHEN '133058' THEN '3503'
				WHEN '133451' THEN '4224'
				WHEN '133502' THEN '3487'
				WHEN '133581' THEN '3488'
				WHEN '134271' THEN '3480'
				WHEN '134333' THEN '3482'
				WHEN '134335' THEN '3481'
			END
			WHERE `country_code` = 'UA'
				AND `city_name` != ''
				AND `oc_zone_id` = '0'
				AND `novapost_region_id` IN (
					'110024', '112225', '114833', '115640', '116260',
					'117153', '118064', '119794', '121270', '121300',
					'122635', '125394', '127808', '128845', '131868',
					'132363', '133058', '133451', '133502', '133581',
					'134271', '134333', '134335'
				)
		");
	}

	private function ensureRegionMapTable(): void {
		$table = DB_PREFIX . 'dockercart_novapost_region_map';
		$check = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape($table) . "'");
		if (!$check->num_rows) {
			$this->db->query("
				CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "dockercart_novapost_region_map` (
					`region_map_id` INT(11) NOT NULL AUTO_INCREMENT,
					`novapost_region_id` VARCHAR(64) NOT NULL DEFAULT '',
					`country_code` VARCHAR(2) NOT NULL DEFAULT '',
					`novapost_region_name` VARCHAR(255) NOT NULL DEFAULT '',
					`city_name` VARCHAR(255) NOT NULL DEFAULT '',
					`oc_zone_id` INT(11) NOT NULL DEFAULT '0',
					`date_added` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
					`date_modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (`region_map_id`),
					UNIQUE KEY `uk_np_region` (`novapost_region_id`, `country_code`, `city_name`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
			");
		} else {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "dockercart_novapost_region_map`
				ADD COLUMN IF NOT EXISTS `city_name` VARCHAR(255) NOT NULL DEFAULT '' AFTER `novapost_region_name`,
				DROP INDEX IF EXISTS `uk_np_region`,
				ADD UNIQUE KEY `uk_np_region` (`novapost_region_id`, `country_code`, `city_name`)
			");
		}
	}

	// ── Tariff CRUD ────────────────────────────────────────────────────────

	public function getTariffs(array $data = []): array {
		$sql = "SELECT * FROM `" . DB_PREFIX . "dockercart_novapost_tariff` WHERE 1=1";

		if (!empty($data['filter_country'])) {
			$sql .= " AND country_code = '" . $this->db->escape($data['filter_country']) . "'";
		}
		if (!empty($data['filter_delivery_type'])) {
			$sql .= " AND delivery_type = '" . $this->db->escape($data['filter_delivery_type']) . "'";
		}

		$sortAllow = ['country_code', 'delivery_type', 'weight_from', 'cost', 'sort_order', 'status', 'date_added'];
		$sort = !empty($data['sort']) && in_array($data['sort'], $sortAllow) ? $data['sort'] : 'country_code';
		$order = !empty($data['order']) && in_array(strtoupper($data['order']), ['ASC', 'DESC']) ? $data['order'] : 'ASC';

		$sql .= " ORDER BY `" . $sort . "` " . $order;

		if (isset($data['start']) || isset($data['limit'])) {
			$start = $data['start'] < 0 ? 0 : (int)$data['start'];
			$limit = $data['limit'] < 1 ? 20 : (int)$data['limit'];
			$sql .= " LIMIT " . $start . "," . $limit;
		}

		$query = $this->db->query($sql);
		return $query->rows;
	}

	public function getTotalTariffs(array $data = []): int {
		$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "dockercart_novapost_tariff` WHERE 1=1";

		if (!empty($data['filter_country'])) {
			$sql .= " AND country_code = '" . $this->db->escape($data['filter_country']) . "'";
		}
		if (!empty($data['filter_delivery_type'])) {
			$sql .= " AND delivery_type = '" . $this->db->escape($data['filter_delivery_type']) . "'";
		}

		$query = $this->db->query($sql);
		return (int)$query->row['total'];
	}

	public function getTariff(int $tariff_id): ?array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "dockercart_novapost_tariff` WHERE tariff_id = '" . (int)$tariff_id . "'");
		return $query->num_rows ? $query->row : null;
	}

	public function addTariff(array $data): int {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "dockercart_novapost_tariff` SET
			`country_code` = '" . $this->db->escape($data['country_code']) . "',
			`delivery_type` = '" . $this->db->escape($data['delivery_type']) . "',
			`weight_from` = '" . (float)$data['weight_from'] . "',
			`weight_to` = '" . (float)$data['weight_to'] . "',
			`cost` = '" . (float)$data['cost'] . "',
			`free_shipping_from` = " . ($data['free_shipping_from'] !== '' && $data['free_shipping_from'] !== null ? "'" . (float)$data['free_shipping_from'] . "'" : 'NULL') . ",
			`status` = '" . (int)($data['status'] ?? 1) . "',
			`sort_order` = '" . (int)($data['sort_order'] ?? 0) . "',
			`date_added` = NOW(),
			`date_modified` = NOW()
		");
		return (int)$this->db->getLastId();
	}

	public function editTariff(int $tariff_id, array $data): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "dockercart_novapost_tariff` SET
			`country_code` = '" . $this->db->escape($data['country_code']) . "',
			`delivery_type` = '" . $this->db->escape($data['delivery_type']) . "',
			`weight_from` = '" . (float)$data['weight_from'] . "',
			`weight_to` = '" . (float)$data['weight_to'] . "',
			`cost` = '" . (float)$data['cost'] . "',
			`free_shipping_from` = " . ($data['free_shipping_from'] !== '' && $data['free_shipping_from'] !== null ? "'" . (float)$data['free_shipping_from'] . "'" : 'NULL') . ",
			`status` = '" . (int)($data['status'] ?? 1) . "',
			`sort_order` = '" . (int)($data['sort_order'] ?? 0) . "',
			`date_modified` = NOW()
			WHERE tariff_id = '" . (int)$tariff_id . "'
		");
	}

	public function deleteTariff(int $tariff_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_novapost_tariff` WHERE tariff_id = '" . (int)$tariff_id . "'");
	}

	// ── Region Map CRUD ────────────────────────────────────────────────────

	public function getRegionMaps(array $data = []): array {
		$this->ensureRegionMapTable();

		$languageId = (int)$this->config->get('config_language_id');

		$sql = "SELECT rm.*, c.name AS country_name,
				(SELECT COALESCE(dd.parent_region_name, d.parent_region_name)
				 FROM `" . DB_PREFIX . "dockercart_novapost_division` d
				 LEFT JOIN `" . DB_PREFIX . "dockercart_novapost_division_description` dd
					ON dd.division_id = d.division_id AND dd.language_id = '" . $languageId . "'
				 WHERE d.country_code = rm.country_code
					AND d.parent_region_id = rm.novapost_region_id
					AND d.parent_region_id != ''
				 LIMIT 1) AS localized_parent_name
			FROM `" . DB_PREFIX . "dockercart_novapost_region_map` rm
			LEFT JOIN `" . DB_PREFIX . "country` c ON c.iso_code_2 = rm.country_code COLLATE utf8mb4_general_ci AND c.status = '1'
			WHERE 1=1";

		if (!empty($data['filter_country'])) {
			$sql .= " AND rm.country_code = '" . $this->db->escape($data['filter_country']) . "'";
		}
		if (isset($data['filter_mapped']) && $data['filter_mapped'] !== '') {
			if ($data['filter_mapped'] === '1') {
				$sql .= " AND rm.oc_zone_id > 0";
			} else {
				$sql .= " AND rm.oc_zone_id = 0";
			}
		}

		$sortAllow = ['country_code', 'novapost_region_name', 'oc_zone_id', 'date_added'];
		$sort = !empty($data['sort']) && in_array($data['sort'], $sortAllow) ? $data['sort'] : 'country_code';
		$order = !empty($data['order']) && in_array(strtoupper($data['order']), ['ASC', 'DESC']) ? $data['order'] : 'ASC';

		$sql .= " ORDER BY `" . $sort . "` " . $order;

		if (isset($data['start']) || isset($data['limit'])) {
			$start = $data['start'] < 0 ? 0 : (int)$data['start'];
			$limit = $data['limit'] < 1 ? 20 : (int)$data['limit'];
			$sql .= " LIMIT " . $start . "," . $limit;
		}

		$query = $this->db->query($sql);
		return $query->rows;
	}

	public function getTotalRegionMaps(array $data = []): int {
		$this->ensureRegionMapTable();

		$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "dockercart_novapost_region_map` WHERE 1=1";

		if (!empty($data['filter_country'])) {
			$sql .= " AND country_code = '" . $this->db->escape($data['filter_country']) . "'";
		}
		if (isset($data['filter_mapped']) && $data['filter_mapped'] !== '') {
			if ($data['filter_mapped'] === '1') {
				$sql .= " AND oc_zone_id > 0";
			} else {
				$sql .= " AND oc_zone_id = 0";
			}
		}

		$query = $this->db->query($sql);
		return (int)$query->row['total'];
	}

	public function updateRegionMap(int $region_map_id, int $oc_zone_id, string $city_name = ''): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "dockercart_novapost_region_map` SET
			`oc_zone_id` = '" . (int)$oc_zone_id . "',
			`city_name` = '" . $this->db->escape($city_name) . "',
			`date_modified` = NOW()
			WHERE region_map_id = '" . (int)$region_map_id . "'
		");
	}

	public function getZonesByCountryCode(string $iso_code_2): array {
		$query = $this->db->query("
			SELECT z.zone_id, z.name, z.code
			FROM `" . DB_PREFIX . "zone` z
			INNER JOIN `" . DB_PREFIX . "country` c ON c.country_id = z.country_id
			WHERE c.iso_code_2 = '" . $this->db->escape($iso_code_2) . "' COLLATE utf8mb4_general_ci
			AND z.status = '1'
			ORDER BY z.name ASC
		");
		return $query->rows;
	}
}
