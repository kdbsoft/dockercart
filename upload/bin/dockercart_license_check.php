#!/usr/bin/env php
<?php
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
	fwrite(STDERR, "This script must be run from CLI.\n");
	exit(1);
}

if (!is_file('/var/www/html/admin/config.php')) {
	fwrite(STDERR, "Config file not found.\n");
	exit(1);
}

require_once '/var/www/html/admin/config.php';

require_once DIR_SYSTEM . 'helper/general.php';
require_once DIR_SYSTEM . 'library/dockercart/licensing.php';

$registry = new \Opencart\System\Engine\Registry();

$config = new \Opencart\System\Engine\Config();

$db = new \Opencart\System\Library\DB(
	DB_DRIVER,
	DB_HOSTNAME,
	DB_USERNAME,
	DB_PASSWORD,
	DB_DATABASE,
	DB_PORT
);

$registry->set('config', $config);
$registry->set('db', $db);

$licensing = new DockercartLicensing($registry);

echo '[' . date('Y-m-d H:i:s') . '] Starting license verification...' . "\n";

$populated = $licensing->autoPopulate();

echo '[' . date('Y-m-d H:i:s') . '] Auto-populated ' . $populated . ' new license(s)' . "\n";

$licenses = $db->query(
	"SELECT `module_code`, `license_key`, `sku`, `status`, `domain`, `frontend_blocked`
	 FROM `" . DB_PREFIX . "dockercart_license`"
);

$count = 0;
$errors = 0;
$blocked = 0;
$restored = 0;

foreach ($licenses->rows as $row) {
	$result = $licensing->validate($row['module_code'], true);

	if (!empty($result['valid'])) {
		$licensing->heartbeat($row['module_code']);

		if (!empty($row['frontend_blocked'])) {
			$licensing->enableExtension($row['module_code']);
			$restored++;
			echo '[' . date('Y-m-d H:i:s') . '] RESTORED: ' . $row['module_code'] . ' (license valid)' . "\n";
		}

		$count++;
	} else {
		$reason = $result['reason'] ?? 'unknown';
		echo '[' . date('Y-m-d H:i:s') . '] INVALID: ' . $row['module_code'] . ' reason=' . $reason . "\n";

		$blockable_statuses = ['revoked', 'expired', 'invalid'];
		$row_status = $db->query(
			"SELECT `status` FROM `" . DB_PREFIX . "dockercart_license`
			 WHERE `module_code` = '" . $db->escape($row['module_code']) . "'"
		);

		if ($row_status->num_rows && in_array($row_status->row['status'], $blockable_statuses, true)) {
			$licensing->disableExtension($row['module_code']);
			$blocked++;
			echo '[' . date('Y-m-d H:i:s') . '] BLOCKED: ' . $row['module_code'] . ' (frontend disabled)' . "\n";
		}

		$errors++;
	}
}

echo '[' . date('Y-m-d H:i:s') . '] Verification complete. ' . $count . ' valid, ' . $errors . ' invalid, ' . $blocked . ' blocked, ' . $restored . ' restored.' . "\n";

exit(0);
