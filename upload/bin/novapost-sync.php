#!/usr/bin/env php
<?php
/**
 * CLI script for NovaPost divisions synchronization.
 * Run via cron: 0 2 * * * /var/www/html/bin/novapost-sync.php
 *
 * Usage:
 *   php bin/novapost-sync.php [--sandbox] [--countries=PL,UA] [--categories=CargoBranch,PostBranch]
 */

declare(strict_types=1);

$baseDir = dirname(__DIR__);

require $baseDir . '/../storage/vendor/autoload.php';

$dbHost     = getenv('DB_HOSTNAME') ?: 'mariadb';
$dbUser     = getenv('DB_USERNAME') ?: 'dockercart';
$dbPass     = getenv('DB_PASSWORD') ?: 'dockercart_password';
$dbName     = getenv('DB_DATABASE') ?: 'dockercart';
$dbPort     = getenv('DB_PORT') ?: '3306';
$dbPrefix   = getenv('DB_PREFIX') ?: 'oc_';

try {
	$pdo = new PDO(
		"mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
		$dbUser,
		$dbPass,
		[
			PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		]
	);
} catch (PDOException $e) {
	echo "Database connection failed: " . $e->getMessage() . "\n";
	exit(1);
}

// Parse CLI arguments
$options = getopt('', ['sandbox', 'countries:', 'categories:', 'api-key:', 'language:']);

$sandbox = isset($options['sandbox']);
$countryCodes = !empty($options['countries']) ? explode(',', $options['countries']) : null;
$categories = !empty($options['categories']) ? explode(',', $options['categories']) : null;
$apiKeyOverride = !empty($options['api-key']) ? $options['api-key'] : null;
$languageOverride = !empty($options['language']) ? $options['language'] : null;

// Read settings from database
$stmt = $pdo->query("
	SELECT `key`, `value` FROM `{$dbPrefix}setting`
	WHERE `code` = 'shipping_dockercart_novapost'
");
$settings = [];
while ($row = $stmt->fetch()) {
	$settings[$row['key']] = $row['value'];
}

$apiKey = $apiKeyOverride ?: ($settings['shipping_dockercart_novapost_api_key'] ?? '');
if (empty($apiKey)) {
	echo "Error: API key not configured. Set it in admin or pass --api-key=YOUR_KEY\n";
	exit(1);
}

if ($sandbox === false) {
	$sandbox = !empty($settings['shipping_dockercart_novapost_sandbox']);
}

if ($countryCodes === null) {
	$countryCodes = $settings['shipping_dockercart_novapost_country_codes'] ?? 'PL,UA';
	if (is_string($countryCodes)) {
		$countryCodes = array_filter(explode(',', $countryCodes));
	}
}

if ($categories === null) {
	$categories = $settings['shipping_dockercart_novapost_division_categories'] ?? 'CargoBranch,PostBranch,Postomat,PUDO';
	if (is_string($categories)) {
		$categories = array_filter(explode(',', $categories));
	}
}

function getLanguageHeader(string $code): string {
    $map = [
        'en-gb' => 'en',
        'ru-ua' => 'ru',
        'uk-ua' => 'uk',
    ];
    return $map[$code] ?? 'en';
}

// Load active languages
$activeLanguages = [];
$langStmt = $pdo->query("SELECT * FROM `{$dbPrefix}language` WHERE `status` = '1' ORDER BY `sort_order` ASC");
while ($row = $langStmt->fetch()) {
    $activeLanguages[] = $row;
}

if (empty($activeLanguages)) {
    echo "Error: No active languages found in the system\n";
    exit(1);
}

if ($languageOverride) {
    $filtered = array_filter($activeLanguages, fn($l) => $l['code'] === $languageOverride);
    if (empty($filtered)) {
        echo "Error: Language '{$languageOverride}' not found or inactive\n";
        exit(1);
    }
    $activeLanguages = array_values($filtered);
}

echo "NovaPost Sync CLI\n";
echo "==================\n";
echo "API Key: " . substr($apiKey, 0, 8) . "...\n";
echo "Sandbox: " . ($sandbox ? 'Yes' : 'No') . "\n";
echo "Languages: " . implode(', ', array_column($activeLanguages, 'code')) . "\n";
echo "Countries: " . implode(', ', $countryCodes) . "\n";
echo "Categories: " . implode(', ', $categories) . "\n";
echo "\nStarting sync...\n";

set_time_limit(0);

// Create sync log entry
$stmt = $pdo->prepare("
	INSERT INTO `{$dbPrefix}dockercart_novapost_sync_log`
		(`status`, `countries`, `categories`, `started_at`)
	VALUES ('running', ?, ?, NOW())
");
$stmt->execute([implode(',', $countryCodes), implode(',', $categories)]);
$logId = (int)$pdo->lastInsertId();

$totalLoaded = 0;
$totalErrors = 0;
$errors = [];

try {
	$factory = new \NovaDigital\NovaPost\NovaPostApiFactory();

	// Truncate before full sync
	$pdo->exec("TRUNCATE TABLE `{$dbPrefix}dockercart_novapost_division`");
	$pdo->exec("DELETE FROM `{$dbPrefix}dockercart_novapost_division_description`");

	// First pass: English for ALL countries (populates main table + EN descriptions)
	// Subsequent passes: other languages for ALL countries (populates descriptions only)
	foreach ($activeLanguages as $langIndex => $language) {
		$isFirstLanguage = $langIndex === 0;
		$langCode = $language['code'];
		$langId = (int)$language['language_id'];
		$acceptLanguage = getLanguageHeader($langCode);

		echo "Language: {$langCode} ({$acceptLanguage})" . ($isFirstLanguage ? ' [main]' : ' [descriptions only]') . "...\n";

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
			echo "  Country: {$countryCode}...\n";

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
							echo "    No divisions returned.\n";
							$errors[] = "No divisions returned for {$countryCode} ({$acceptLanguage})";
						}
						break;
					}

					echo "    Page {$page}/{$lastPage}: " . count($divisions) . " divisions. ";

					foreach ($divisions as $division) {
						try {
							if ($isFirstLanguage) {
								$divisionId = saveDivision($pdo, $dbPrefix, $division);
								$totalLoaded++;
							} else {
								$divisionId = findDivisionIdBySiteKey($pdo, $dbPrefix, $division['id'] ?? $division['site_key'] ?? '');
							}

							if ($divisionId) {
								saveDivisionDescription($pdo, $dbPrefix, $divisionId, $langId, $division);
							}
						} catch (Exception $e) {
							$totalErrors++;
							$errors[] = "Error saving division {$division['id']} ({$acceptLanguage}): " . $e->getMessage();
						}
					}

					echo "\n";

					if ($page >= $lastPage) {
						break;
					}

					$page++;
				}
			} catch (Exception $e) {
				$totalErrors++;
				$errors[] = "Error fetching {$countryCode} ({$acceptLanguage}): " . $e->getMessage();
				echo "    Error: " . $e->getMessage() . "\n";
			}
		}

		echo "  Done.\n";
	}

	// Flush discovered parent regions into region_map
	flushDiscoveredRegions($pdo, $dbPrefix);
	flushDiscoveredCities($pdo, $dbPrefix);

	$status = $totalErrors > 0 ? 'partial' : 'success';

} catch (Exception $e) {
	$status = 'failed';
	$errors[] = $e->getMessage();
	echo "Fatal error: " . $e->getMessage() . "\n";
}

// Update sync log
$stmt = $pdo->prepare("
	UPDATE `{$dbPrefix}dockercart_novapost_sync_log` SET
		`status` = ?,
		`total_loaded` = ?,
		`total_errors` = ?,
		`finished_at` = NOW(),
		`error_message` = ?
	WHERE `log_id` = ?
");
$stmt->execute([
	$status,
	$totalLoaded,
	$totalErrors,
	implode('; ', $errors),
	$logId,
]);

// Update last sync date
$pdo->exec("
	INSERT INTO `{$dbPrefix}setting` (`store_id`, `code`, `key`, `value`, `serialized`)
	VALUES (0, 'shipping_dockercart_novapost', 'shipping_dockercart_novapost_sync_date', NOW(), 0)
	ON DUPLICATE KEY UPDATE `value` = NOW()
");

echo "\nSync completed!\n";
echo "Status: {$status}\n";
echo "Loaded: {$totalLoaded}\n";
echo "Errors: {$totalErrors}\n";

if ($errors) {
	echo "\nErrors:\n";
	foreach ($errors as $err) {
		echo "  - {$err}\n";
	}
}

exit($status === 'failed' ? 1 : 0);

function findDivisionIdBySiteKey(PDO $pdo, string $prefix, string $siteKey): ?int {
    if (empty($siteKey)) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT `division_id` FROM `{$prefix}dockercart_novapost_division` WHERE `site_key` = ? LIMIT 1");
    $stmt->execute([$siteKey]);
    $row = $stmt->fetch();
    return $row ? (int)$row['division_id'] : null;
}

function saveDivisionDescription(PDO $pdo, string $prefix, int $divisionId, int $languageId, array $division): void {
    $name      = $division['name'] ?? '';
    $shortAddr = $division['shortName'] ?? $division['shortAddress'] ?? $division['short_address'] ?? '';
    $fullAddr  = $division['address'] ?? $division['fullAddress'] ?? $division['full_address'] ?? '';
    $cityName  = $division['settlement']['name'] ?? $division['cityName'] ?? $division['city_name'] ?? '';
    $regionNm  = $division['settlement']['region']['name'] ?? $division['regionName'] ?? $division['region_name'] ?? '';
    $parentRegionName = $division['settlement']['region']['parent']['name'] ?? $division['parentRegionName'] ?? $division['parent_region_name'] ?? '';

    $stmt = $pdo->prepare("
        INSERT INTO `{$prefix}dockercart_novapost_division_description`
            (`division_id`, `language_id`, `name`, `short_address`, `full_address`, `city_name`, `region_name`, `parent_region_name`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            `name` = VALUES(`name`),
            `short_address` = VALUES(`short_address`),
            `full_address` = VALUES(`full_address`),
            `city_name` = VALUES(`city_name`),
            `region_name` = VALUES(`region_name`),
            `parent_region_name` = VALUES(`parent_region_name`)
    ");
    $stmt->execute([$divisionId, $languageId, $name, $shortAddr, $fullAddr, $cityName, $regionNm, $parentRegionName]);
}

function saveDivision(PDO $pdo, string $prefix, array $division): int {
	$siteKey   = (string)($division['id'] ?? $division['site_key'] ?? '');
	$number    = $division['number'] ?? '';
	$type      = $division['divisionCategory'] ?? $division['type'] ?? '';
	$category  = $division['divisionCategory'] ?? $division['category'] ?? '';
	$name      = $division['name'] ?? '';
	$shortAddr = $division['shortName'] ?? $division['shortAddress'] ?? $division['short_address'] ?? '';
	$fullAddr  = $division['address'] ?? $division['fullAddress'] ?? $division['full_address'] ?? '';
	$cityRef   = (string)($division['settlement']['id'] ?? $division['cityRef'] ?? $division['city_ref'] ?? '');
	$cityName  = $division['settlement']['name'] ?? $division['cityName'] ?? $division['city_name'] ?? '';
	$regionRef = (string)($division['settlement']['region']['id'] ?? $division['regionRef'] ?? $division['region_ref'] ?? '');
	$regionNm  = $division['settlement']['region']['name'] ?? $division['regionName'] ?? $division['region_name'] ?? '';
	$parentRegionId   = (string)($division['settlement']['region']['parent']['id'] ?? $division['parentRegionId'] ?? $division['parent_region_id'] ?? '');
	$parentRegionName = $division['settlement']['region']['parent']['name'] ?? $division['parentRegionName'] ?? $division['parent_region_name'] ?? '';
	$country   = $division['countryCode'] ?? $division['country_code'] ?? '';
	$phone     = is_array($division['publicPhones'] ?? null) ? ($division['publicPhones'][0] ?? '') : ($division['phone'] ?? '');
	$enabled   = ($division['status'] ?? '') === 'Working' ? 1 : (!empty($division['enabled'] ?? '') ? 1 : 0);
	$maxWeight = isset($division['maxWeightPlaceSender'])
		? (int)$division['maxWeightPlaceSender']
		: (isset($division['maxWeight'])
			? (int)$division['maxWeight']
			: (isset($division['max_weight']) ? (int)$division['max_weight'] : null));

	$lat = isset($division['latitude']) && $division['latitude'] !== '' ? (float)$division['latitude'] : null;
	$lon = isset($division['longitude']) && $division['longitude'] !== '' ? (float)$division['longitude'] : null;

	$schedule = null;
	$scheduleData = $division['workSchedule'] ?? $division['schedule'] ?? null;
	if (is_array($scheduleData)) {
		$schedule = json_encode($scheduleData);
	} elseif (is_string($scheduleData) && $scheduleData !== '') {
		$schedule = $scheduleData;
	}

	$stmt = $pdo->prepare("
		INSERT INTO `{$prefix}dockercart_novapost_division` (
			`site_key`, `number`, `type`, `category`, `name`,
			`short_address`, `full_address`,
			`city_ref`, `city_name`, `region_ref`, `region_name`,
			`parent_region_id`, `parent_region_name`,
			`country_code`,
			`latitude`, `longitude`, `phone`, `schedule`, `max_weight`, `enabled`
		) VALUES (
			?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
		) ON DUPLICATE KEY UPDATE
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

	$stmt->execute([
		$siteKey, $number, $type, $category, $name,
		$shortAddr, $fullAddr,
		$cityRef, $cityName, $regionRef, $regionNm,
		$parentRegionId, $parentRegionName,
		$country,
		$lat, $lon, $phone, $schedule, $maxWeight, $enabled,
	]);

	$divisionId = (int)$pdo->lastInsertId();
	if ($divisionId === 0) {
		$fetchStmt = $pdo->prepare("SELECT `division_id` FROM `{$prefix}dockercart_novapost_division` WHERE `site_key` = ? LIMIT 1");
		$fetchStmt->execute([$siteKey]);
		$row = $fetchStmt->fetch();
		$divisionId = $row ? (int)$row['division_id'] : 0;
	}

	// Track discovered parent regions for region_map
	if ($parentRegionId !== '') {
		global $discoveredRegions;
		if (!isset($discoveredRegions)) {
			$discoveredRegions = [];
		}
		$key = $country . '|' . $parentRegionId;
		if (!isset($discoveredRegions[$key])) {
			$discoveredRegions[$key] = [
				'novapost_region_id'   => $parentRegionId,
				'country_code'         => $country,
				'novapost_region_name' => $parentRegionName,
			];
		}
	}

	return $divisionId;
}

function flushDiscoveredRegions(PDO $pdo, string $prefix): void {
	global $discoveredRegions;

	if (empty($discoveredRegions)) {
		return;
	}

	// Ensure region_map table exists
	$check = $pdo->query("SHOW TABLES LIKE '{$prefix}dockercart_novapost_region_map'");
	if (!$check->fetch()) {
		$pdo->exec("
			CREATE TABLE IF NOT EXISTS `{$prefix}dockercart_novapost_region_map` (
				`region_map_id` INT(11) NOT NULL AUTO_INCREMENT,
				`novapost_region_id` VARCHAR(64) NOT NULL DEFAULT '',
				`country_code` VARCHAR(2) NOT NULL DEFAULT '',
				`novapost_region_name` VARCHAR(255) NOT NULL DEFAULT '',
				`oc_zone_id` INT(11) NOT NULL DEFAULT '0',
				`date_added` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`date_modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (`region_map_id`),
				UNIQUE KEY `uk_np_region` (`novapost_region_id`, `country_code`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
		");
	}

	$stmt = $pdo->prepare("
		INSERT IGNORE INTO `{$prefix}dockercart_novapost_region_map`
			(`novapost_region_id`, `country_code`, `novapost_region_name`)
		VALUES (?, ?, ?)
	");

	foreach ($discoveredRegions as $region) {
		$stmt->execute([
			$region['novapost_region_id'],
			$region['country_code'],
			$region['novapost_region_name'],
		]);
	}

	echo "Flushed " . count($discoveredRegions) . " unique parent regions to region_map.\n";
}

function flushDiscoveredCities(PDO $pdo, string $prefix): void {
	$stmt = $pdo->prepare("
		INSERT IGNORE INTO `{$prefix}dockercart_novapost_region_map`
			(`novapost_region_id`, `country_code`, `novapost_region_name`, `city_name`)
		SELECT DISTINCT m.city_ref, m.country_code, 'City-level mapping', m.city_name
		FROM `{$prefix}dockercart_novapost_division` m
		WHERE m.parent_region_id = ''
			AND m.enabled = '1'
			AND m.city_ref != ''
			AND NOT EXISTS (
				SELECT 1 FROM `{$prefix}dockercart_novapost_region_map` rm
				WHERE rm.city_name != ''
				AND rm.novapost_region_id = m.city_ref
				AND rm.country_code = m.country_code
			)
	");
	$stmt->execute();
	echo "Flushed discovered cities to region_map.\n";
}
