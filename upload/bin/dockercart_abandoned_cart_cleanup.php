#!/usr/bin/env php
<?php
/**
 * DockerCart Abandoned Cart Cleanup & Reminder — CLI Worker
 *
 * Runs once per day via the scheduler daemon. Two jobs:
 *
 *  1. Send a reminder e-mail to customers whose abandoned cart is at least
 *     config_cart_abandoned_delay_days old (default 1) and has not been
 *     reminded yet (reminder_sent = 0). Each cart gets a one-time restore
 *     token so the customer can put the items back into their cart.
 *
 *  2. Delete old abandoned carts: recovered rows and contact-less rows older
 *     than config_cart_abandoned_retention_days (default 90).
 *
 * The whole run is guarded by config_cart_abandoned_enable. reminder_sent is
 * set before sending so a concurrent run (or a re-run) does not double-send;
 * if sending fails the flag is rolled back.
 *
 * Usage:
 *   php /var/www/html/bin/dockercart_abandoned_cart_cleanup.php
 *
 * Exit codes:
 *   0 — success
 *   1 — failure
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
	fwrite(STDERR, "This script must be run from CLI.\n");
	exit(1);
}

$_SERVER['HTTP_HOST']      = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI']    = '/';

$config_path = __DIR__ . '/../config.php';

if (!is_file($config_path)) {
	fwrite(STDERR, "[abandoned-cart] ERROR: config.php not found at {$config_path}\n");
	exit(1);
}

require_once $config_path;

if (!defined('DIR_APPLICATION')) {
	fwrite(STDERR, "[abandoned-cart] ERROR: DIR_APPLICATION not defined\n");
	exit(1);
}

require_once DIR_SYSTEM . 'startup.php';

try {
	$registry = new Registry();

	$config = new Config();
	$config->load('default');
	$config->load('catalog');
	$registry->set('config', $config);

	$log = new Log($config->get('error_filename') ?: 'error.log');
	$registry->set('log', $log);

	$event = new Event($registry);
	$registry->set('event', $event);

	$loader = new Loader($registry);
	$registry->set('load', $loader);

	$db = new DB(
		$config->get('db_engine')    ?: 'mysqli',
		$config->get('db_hostname')  ?: 'mariadb',
		$config->get('db_username')  ?: 'dockercart',
		$config->get('db_password')  ?: 'dockercart_password',
		$config->get('db_database')  ?: 'dockercart',
		$config->get('db_port')      ?: '3306'
	);
	$registry->set('db', $db);

	$config->set('config_store_id', 0);
	$config->set('config_language_id', 1);

	$query = $db->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE store_id = '0'");
	foreach ($query->rows as $result) {
		if (!$result['serialized']) {
			$config->set($result['key'], $result['value']);
		} else {
			$config->set($result['key'], json_decode($result['value'], true));
		}
	}

	if (!$config->get('config_cart_abandoned_enable')) {
		fwrite(STDOUT, "[abandoned-cart] Abandoned carts are disabled (config_cart_abandoned_enable = 0).\n");
		exit(0);
	}

	// Minimal services needed by the mail controller and the SEO rewriter
	$cache = new Cache($config->get('cache_engine') ?: 'file', (int)($config->get('cache_expire') ?: 3600));
	$registry->set('cache', $cache);

	$url = new Url($config->get('config_url') ?: (defined('HTTP_SERVER') ? HTTP_SERVER : ''), $config->get('config_ssl') ?: '');
	$registry->set('url', $url);

	// Register the SEO URL rewriter so restore links in e-mails are clean
	// SEO URLs (/restore-cart?token=...) instead of index.php?route=...
	if ($config->get('config_seo_url')) {
		require_once DIR_APPLICATION . 'controller/startup/seo_url.php';
		$seoUrl = new ControllerStartupSeoUrl($registry);
		$seoUrl->initializeRequestState();
		$url->addRewrite($seoUrl);
	}

	$session = new stdClass();
	$session->data = array('language' => $config->get('config_language') ?: 'en-gb');
	$registry->set('session', $session);

	$language = new Language($config->get('config_language') ?: 'en-gb');
	$language->load($config->get('config_language') ?: 'en-gb');
	$registry->set('language', $language);

	// Create a percentage coupon with a unique code for a reminder wave.
	$createWaveCoupon = function ($db, int $discount) use ($config) {
		do {
			$code = 'ABANDONED-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
			$exists = $db->query("SELECT coupon_id FROM `" . DB_PREFIX . "coupon` WHERE code = '" . $db->escape($code) . "'");
		} while ($exists->num_rows);

		$store_name = $config->get('config_name') ?: 'Store';
		$start = date('Y-m-d');
		$end = date('Y-m-d', strtotime('+30 days'));

		$db->query("INSERT INTO `" . DB_PREFIX . "coupon` SET
		            name = '" . $db->escape($store_name . ' — abandoned cart ' . $discount . '%') . "',
		            code = '" . $db->escape($code) . "',
		            type = 'P',
		            discount = '" . (float)$discount . "',
		            total = '0',
		            logged = '0',
		            shipping = '1',
		            date_start = '" . $db->escape($start) . "',
		            date_end = '" . $db->escape($end) . "',
		            uses_total = '1000',
		            uses_customer = '1',
		            status = '1',
		            date_added = NOW()");

		$coupon_id = $db->getLastId();

		// Coupon description for every active language.
		$languages = $db->query("SELECT language_id FROM `" . DB_PREFIX . "language` WHERE status = '1'");

		foreach ($languages->rows as $lang) {
			$db->query("INSERT INTO `" . DB_PREFIX . "coupon_description` SET
			            coupon_id = '" . (int)$coupon_id . "',
			            language_id = '" . (int)$lang['language_id'] . "',
			            name = '" . $db->escape($store_name . ' — abandoned cart ' . $discount . '%') . "'");
		}

		return $code;
	};

	$retention = (int)$config->get('config_cart_abandoned_retention_days');
	$retention = $retention > 0 ? $retention : 90;

	$delay = (int)$config->get('config_cart_abandoned_delay_days');
	$delay = $delay > 0 ? $delay : 1;

	// Mail is not configured: the Mail\Smtp adaptor silently skips sending when
	// smtp_hostname is empty, which would mark carts as reminded without any
	// e-mail actually going out. Detect this up front and skip reminders
	// (leave reminder_sent = 0 so they are picked up once mail is configured).
	$mail_configured = (bool)$config->get('config_mail_smtp_hostname');

	if ($delay > 0 && !$mail_configured) {
		fwrite(STDOUT, "[abandoned-cart] SMTP not configured (config_mail_smtp_hostname empty), skipping reminders. Carts will be reminded once mail is set up.\n");
	}

	// Cleanup 1: recovered rows older than the retention period.
	$db->query("DELETE FROM `" . DB_PREFIX . "dockercart_checkout_abandoned`
	            WHERE recovered = 1
	            AND date_modified < DATE_SUB(NOW(), INTERVAL " . (int)$retention . " DAY)");

	$cleaned_recovered = $db->countAffected();

	// Cleanup 2: abandoned rows without a contact email older than the retention period.
	$db->query("DELETE FROM `" . DB_PREFIX . "dockercart_checkout_abandoned`
	            WHERE recovered = 0
	            AND (email IS NULL OR email = '')
	            AND date_modified < DATE_SUB(NOW(), INTERVAL " . (int)$retention . " DAY)");

	$cleaned_no_email = $db->countAffected();

	// Reminder waves: [{days: N, discount: N}, ...] sorted ascending by day.
	$waves = (array)$config->get('config_cart_abandoned_waves');

	if (!$waves) {
		$waves = array(array('days' => max(1, $delay), 'discount' => 0));
	}

	usort($waves, function ($a, $b) {
		return (int)($a['days'] ?? 0) <=> (int)($b['days'] ?? 0);
	});

	$sent = 0;
	$failed = 0;

	if ($mail_configured && $waves) {
		// Carts that still have an unsent wave. Age is checked per wave below.
		$carts = $db->query(
			"SELECT * FROM `" . DB_PREFIX . "dockercart_checkout_abandoned`
			 WHERE recovered = 0
			   AND email IS NOT NULL
			   AND email != ''
			   AND reminder_wave < " . count($waves) . "
			   AND date_added < DATE_SUB(NOW(), INTERVAL " . (int)$waves[0]['days'] . " DAY)"
		);

		foreach ($carts->rows as $cart) {
			$abandoned_id = (int)$cart['abandoned_id'];
			$next_wave_index = (int)$cart['reminder_wave'];

			// The next wave this cart is due for (index into $waves).
			// Pick the LATEST wave whose day threshold has already passed,
			// so a 5-day-old cart skips straight to wave 3 if configured.
			$wave_index = -1;
			$cart_age = strtotime($cart['date_added']);

			foreach ($waves as $i => $wave) {
				if ($i < $next_wave_index) {
					continue;
				}

				$wave_days = (int)$wave['days'];

				// Cart is at least N days old when its date_added is earlier
				// than (now - N days).
				if ($cart_age <= strtotime('-' . $wave_days . ' days')) {
					$wave_index = $i;
				} else {
					break;
				}
			}

			if ($wave_index < 0) {
				continue;
			}

			$wave = $waves[$wave_index];

			// Re-check the guard: a concurrent run or an admin may have handled it.
			$check = $db->query(
				"SELECT abandoned_id FROM `" . DB_PREFIX . "dockercart_checkout_abandoned`
				 WHERE abandoned_id = '" . $abandoned_id . "'
				   AND recovered = 0
				   AND reminder_wave = '" . (int)$next_wave_index . "'"
			);

			if (!$check->num_rows) {
				continue;
			}

			$token = bin2hex(random_bytes(24));

			// Auto-create a coupon for this wave (once per cart per wave).
			$coupon_code = '';
			$discount = (int)($wave['discount'] ?? 0);

			if ($discount > 0) {
				if (!empty($cart['reminder_coupon_id'])) {
					$coupon_query = $db->query("SELECT code FROM `" . DB_PREFIX . "coupon` WHERE coupon_id = '" . (int)$cart['reminder_coupon_id'] . "'");

					if ($coupon_query->num_rows) {
						$coupon_code = $coupon_query->row['code'];
					}
				}

				if (!$coupon_code) {
					$coupon_code = $createWaveCoupon($db, $discount);

					$db->query("UPDATE `" . DB_PREFIX . "dockercart_checkout_abandoned`
					            SET reminder_coupon_id = (SELECT coupon_id FROM `" . DB_PREFIX . "coupon` WHERE code = '" . $db->escape($coupon_code) . "')
					            WHERE abandoned_id = '" . $abandoned_id . "'");
				}
			}

			$db->query("UPDATE `" . DB_PREFIX . "dockercart_checkout_abandoned`
			            SET restore_token = '" . $db->escape($token) . "',
			                restore_expires = DATE_ADD(NOW(), INTERVAL 7 DAY),
			                reminder_sent = 1,
			                reminder_sent_at = NOW(),
			                reminder_wave = '" . ($wave_index + 1) . "',
			                date_modified = NOW()
			            WHERE abandoned_id = '" . $abandoned_id . "'");

			$items = array();
			$cart_data = json_decode($cart['cart_data'], true);

			if (is_array($cart_data)) {
				foreach ($cart_data as $item) {
					if (!isset($item['name'], $item['quantity'])) {
						continue;
					}

					$items[] = array(
						'name'     => $item['name'],
						'quantity' => $item['quantity'],
						'total'    => isset($item['total']) ? $item['total'] : ''
					);
				}
			}

			$mail_data = array(
				'abandoned_id'  => $abandoned_id,
				'email'         => $cart['email'],
				'items'         => $items,
				'restore_url'   => $url->link('checkout/dockercart_checkout/restore', 'token=' . $token),
				'coupon_code'   => $coupon_code,
				'discount'      => $discount,
				'wave_number'   => $wave_index + 1,
				'wave_count'    => count($waves)
			);

			try {
				$loader->controller('mail/abandoned', array($mail_data));
				$sent++;
			} catch (\Throwable $e) {
				// Roll back so the next run retries this cart.
				$db->query("UPDATE `" . DB_PREFIX . "dockercart_checkout_abandoned`
				            SET reminder_sent = 0,
				                reminder_sent_at = NULL,
				                restore_token = NULL,
				                restore_expires = NULL,
				                reminder_wave = '" . (int)$next_wave_index . "',
				                date_modified = NOW()
				            WHERE abandoned_id = '" . $abandoned_id . "'");

				$failed++;
				fwrite(STDERR, "[abandoned-cart] Failed to send reminder for cart #" . $abandoned_id . ": " . $e->getMessage() . "\n");
			}
		}
	}

	fwrite(STDOUT, "[abandoned-cart] Done. Reminders sent: " . $sent . ", failed: " . $failed . ", cleaned recovered: " . $cleaned_recovered . ", cleaned no-email: " . $cleaned_no_email . ".\n");

	exit(0);
} catch (\Throwable $e) {
	fwrite(STDERR, "[abandoned-cart] FATAL: " . $e->getMessage() . "\n");
	exit(1);
}
