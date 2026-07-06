<?php
/**
 * GDPR Consent Cleanup Cron Job
 *
 * Removes expired consent records and auto-denies stale data requests.
 * Registered via DockercartScheduler during module install.
 */

$start = microtime(true);

require_once dirname(__DIR__) . '/system/startup.php';

$registry = new Registry();
$loader = new Loader($registry);
$registry->set('load', $loader);
$config = new Config();
$registry->set('config', $config);

$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
$registry->set('db', $db);

$config->set('config_store_id', 0);

$expiry_days = 365;
$query = $db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `code` = 'module_dockercart_gdpr' AND `key` = 'module_dockercart_gdpr_consent_expiry' LIMIT 1");
if ($query->num_rows && (int)$query->row['value'] > 0) {
	$expiry_days = (int)$query->row['value'];
}

$db->query(
	"DELETE FROM `" . DB_PREFIX . "dockercart_gdpr_consent`
	 WHERE `date_added` < DATE_SUB(NOW(), INTERVAL " . (int)$expiry_days . " DAY)"
);

$db->query(
	"UPDATE `" . DB_PREFIX . "dockercart_gdpr_request`
	 SET `status` = 'denied', `date_processed` = NOW()
	 WHERE `status` = 'pending'
	   AND `date_added` < DATE_SUB(NOW(), INTERVAL 30 DAY)"
);

$elapsed = round(microtime(true) - $start, 4);
echo "[GDPR Cleanup] Done. Removed expired consents, auto-denied stale requests. Took {$elapsed}s" . PHP_EOL;
