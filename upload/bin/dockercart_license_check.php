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
	"SELECT `module_code`, `license_key`, `sku`, `status`, `domain`
	 FROM `" . DB_PREFIX . "dockercart_license`"
);

$count = 0;
$errors = 0;

foreach ($licenses->rows as $row) {
	$result = $licensing->validate($row['module_code'], true);

	if (!empty($result['valid'])) {
		$licensing->heartbeat($row['module_code']);
		$count++;
	} else {
		$reason = $result['reason'] ?? 'unknown';
		echo '[' . date('Y-m-d H:i:s') . '] INVALID: ' . $row['module_code'] . ' reason=' . $reason . "\n";
		$errors++;
	}
}

echo '[' . date('Y-m-d H:i:s') . '] Verification complete. ' . $count . ' valid, ' . $errors . ' invalid.' . "\n";

exit($errors > 0 ? 0 : 0);
