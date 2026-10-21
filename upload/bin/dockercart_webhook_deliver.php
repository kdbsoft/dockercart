#!/usr/bin/env php
<?php
/**
 * DockerCart Webhook Delivery Worker — CLI.
 *
 * Registered as the system scheduler task `webhook_deliver` (`* * * * *`,
 * hidden from the scheduler UI, see migration 20261025_webhooks.sql). One run:
 *
 *   1. reloads store settings (the HMAC encryption key lives in oc_setting);
 *   2. recovers rows stuck in `sending` after a crashed worker;
 *   3. stops when the global kill switch (config_webhook_status) is off —
 *      rows stay pending and are resumed when it is turned back on;
 *   4. claims a bounded batch of due rows, sends them sequentially (5–10 s
 *      timeout each), journals status/response/duration and schedules the next
 *      exponential attempt (1 m / 5 m / 30 m / 2 h / 12 h);
 *   5. fails permanently on 4xx (except 408/425/429), SSRF/invalid URL and
 *      missing secrets; a 410 disables the subscription immediately; four
 *      consecutive failed deliveries (test events excluded) auto-disable it
 *      with a visible reason in the panel.
 *
 * Usage:
 *   php /var/www/html/bin/dockercart_webhook_deliver.php
 *
 * Environment:
 *   WEBHOOK_ALLOW_PRIVATE=1   development only: allow loopback/private URLs.
 *
 * Exit codes:
 *   0 — success (individual delivery failures are normal, they are journaled)
 *   1 — setup/DB failure
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
	fwrite(STDERR, "[webhook-deliver] ERROR: config.php not found at {$config_path}\n");
	exit(1);
}

require_once $config_path;

if (!defined('DIR_APPLICATION')) {
	fwrite(STDERR, "[webhook-deliver] ERROR: DIR_APPLICATION not defined\n");
	exit(1);
}

require_once DIR_SYSTEM . 'startup.php';

use Dockercart\Webhook\Delivery;
use Dockercart\Webhook\Sender;
use Dockercart\Webhook\Secret;
use Dockercart\Webhook\Signature;

/** Maximum rows handled per run (one run per minute). */
const WEBHOOK_BATCH = 20;

/** Stop starting new sends after this many seconds (scheduler timeout safety). */
const WEBHOOK_TIME_BUDGET = 50;

/** A `sending` row older than this is considered crashed and is retried. */
const WEBHOOK_LOCK_MINUTES = 2;

/**
 * Backfills Config with the store settings (config_encryption lives there).
 */
function webhook_load_settings($config, $db): void {
	$query = $db->query("SELECT `key`, `value`, `serialized` FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '0'");

	foreach ($query->rows as $setting) {
		if ((int)$setting['serialized'] === 1) {
			$config->set($setting['key'], json_decode((string)$setting['value'], true));
		} else {
			$config->set($setting['key'], $setting['value']);
		}
	}
}

/**
 * Uses the same clock as the panel (startup/startup): the admin and catalog
 * bootstrappers run `SET time_zone = date('P')` whenever config_timezone is set.
 * Without it this worker would compare `next_attempt_at` values written by a
 * request's session (local time) against its own UTC `NOW()` — a delivery would
 * silently wait for the store's UTC offset.
 */
function webhook_sync_timezone($config, $db): void {
	$timezone = (string)$config->get('config_timezone');

	if ($timezone === '') {
		return;
	}

	date_default_timezone_set($timezone);

	$db->query("SET time_zone = '" . $db->escape(date('P')) . "'");
}

/**
 * Journals a failed attempt and schedules/finishes it.
 */
function webhook_fail($db, $delivery, array $row, array $result, int $attempt, int $max_attempts, int $disable_after): void {
	$status = (int)$result['status'];
	$error = (string)$result['error'];
	$permanent = Delivery::isPermanent($error, $status) || $attempt >= $max_attempts;

	// Always DB-relative: the CLI worker does not run the panel's SET time_zone.
	$next_attempt = Delivery::nextAttemptSql($attempt, $max_attempts);
	$body = (string)$result['body'];

	$db->query("UPDATE `" . DB_PREFIX . "dockercart_webhook_delivery` SET
		`status` = '" . ($permanent ? Delivery::STATUS_FAILED : Delivery::STATUS_PENDING) . "',
		`next_attempt_at` = " . $next_attempt . ",
		`locked_until` = NULL,
		`response_code` = '" . $status . "',
		`response_body` = " . ($body !== '' ? "'" . $db->escape($body) . "'" : 'NULL') . ",
		`duration_ms` = '" . (int)$result['duration_ms'] . "',
		`last_error` = '" . $db->escape(substr($error, 0, 255)) . "',
		`date_modified` = NOW()
	 WHERE `delivery_id` = '" . (int)$row['delivery_id'] . "'");

	if (!$permanent) {
		return;
	}

	// A 410 Gone means the endpoint was removed: disable at once, regardless of
	// the consecutive-failure counter (test deliveries never disable).
	if ($status === 410 && (int)$row['is_test'] === 0) {
		webhook_disable_subscription($db, (int)$row['subscription_id'], 'gone', $delivery);

		return;
	}

	if ((int)$row['is_test'] === 1) {
		return;
	}

	$db->query("UPDATE `" . DB_PREFIX . "dockercart_webhook_subscription` SET
		`consecutive_failures` = `consecutive_failures` + 1,
		`last_failure_at` = NOW(),
		`date_modified` = NOW()
	 WHERE `subscription_id` = '" . (int)$row['subscription_id'] . "'");

	$counter = $db->query("SELECT `consecutive_failures`, `status` FROM `" . DB_PREFIX . "dockercart_webhook_subscription` WHERE `subscription_id` = '" . (int)$row['subscription_id'] . "'");

	if ($counter->num_rows && (int)$counter->row['status'] === 1 && (int)$counter->row['consecutive_failures'] >= $disable_after) {
		webhook_disable_subscription($db, (int)$row['subscription_id'], 'repeated_failures', $delivery);
	}
}

/**
 * Disables a subscription and cancels its still-pending deliveries.
 */
function webhook_disable_subscription($db, int $subscription_id, string $reason, $delivery): void {
	$db->query("UPDATE `" . DB_PREFIX . "dockercart_webhook_subscription` SET
		`status` = '0',
		`disabled_reason` = '" . $db->escape($reason) . "',
		`disabled_at` = NOW(),
		`date_modified` = NOW()
	 WHERE `subscription_id` = '" . $subscription_id . "'");

	$delivery->cancelPending($subscription_id);

	fwrite(STDOUT, "[webhook-deliver] subscription #" . $subscription_id . " disabled (" . $reason . ")\n");
}

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

	// The encryption key and the webhook limits are store settings.
	webhook_load_settings($config, $db);
	webhook_sync_timezone($config, $db);

	$limits = Delivery::limits(
		$config->get('config_webhook_max_attempts'),
		$config->get('config_webhook_timeout'),
		$config->get('config_webhook_disable_after'),
		$config->get('config_webhook_status')
	);

	$max_attempts = $limits['max_attempts'];
	$timeout = $limits['timeout'];
	$disable_after = $limits['disable_after'];
	$enabled = $limits['enabled'];

	$started_at = time();

	// Recover rows whose worker died between "sending" and the final update.
	$db->query("UPDATE `" . DB_PREFIX . "dockercart_webhook_delivery` SET
		`status` = '" . Delivery::STATUS_PENDING . "',
		`locked_until` = NULL,
		`last_error` = 'stale_lock',
		`date_modified` = NOW()
	 WHERE `status` = '" . Delivery::STATUS_SENDING . "'
	   AND `locked_until` IS NOT NULL
	   AND `locked_until` < NOW()");

	if (!$enabled) {
		fwrite(STDOUT, "[webhook-deliver] Disabled by config_webhook_status — pending deliveries left untouched.\n");

		exit(0);
	}

	$due = $db->query("SELECT d.*, s.`url`, s.`status` AS `subscription_status`, s.`secret_encrypted`
		FROM `" . DB_PREFIX . "dockercart_webhook_delivery` d
		LEFT JOIN `" . DB_PREFIX . "dockercart_webhook_subscription` s ON (s.`subscription_id` = d.`subscription_id`)
		WHERE d.`status` = '" . Delivery::STATUS_PENDING . "'
		  AND d.`next_attempt_at` <= NOW()
		ORDER BY d.`next_attempt_at` ASC, d.`delivery_id` ASC
		LIMIT " . WEBHOOK_BATCH);

	$delivery = new Delivery($registry);
	$sender = new Sender($registry);
	$secret_key = (string)$config->get('config_encryption');

	$delivered = 0;
	$failed = 0;
	$deferred = 0;
	$skipped = 0;

	foreach ($due->rows as $row) {
		if (time() - $started_at >= WEBHOOK_TIME_BUDGET) {
			$deferred++;

			continue;
		}

		$attempt = (int)$row['attempts'] + 1;

		// Claim: exactly one worker may send this row. If another one got it
		// first, the conditional UPDATE affects zero rows.
		$db->query("UPDATE `" . DB_PREFIX . "dockercart_webhook_delivery` SET
			`status` = '" . Delivery::STATUS_SENDING . "',
			`attempts` = '" . $attempt . "',
			`locked_until` = DATE_ADD(NOW(), INTERVAL " . WEBHOOK_LOCK_MINUTES . " MINUTE),
			`date_modified` = NOW()
		 WHERE `delivery_id` = '" . (int)$row['delivery_id'] . "'
		   AND `status` = '" . Delivery::STATUS_PENDING . "'");

		if ($db->countAffected() !== 1) {
			$skipped++;

			continue;
		}

		// Subscription deleted or disabled between enqueue and send.
		if (!isset($row['subscription_status']) || $row['subscription_status'] === null || (int)$row['subscription_status'] !== 1) {
			$db->query("UPDATE `" . DB_PREFIX . "dockercart_webhook_delivery` SET
				`status` = '" . Delivery::STATUS_CANCELLED . "',
				`locked_until` = NULL,
				`last_error` = 'subscription_disabled',
				`date_modified` = NOW()
			 WHERE `delivery_id` = '" . (int)$row['delivery_id'] . "'");

			$failed++;

			continue;
		}

		$secret = Secret::decrypt((string)$row['secret_encrypted'], $secret_key);

		if ($secret === null) {
			webhook_fail($db, $delivery, $row, [
				'ok'          => false,
				'status'      => 0,
				'body'        => '',
				'error'       => 'secret_unavailable',
				'duration_ms' => 0,
			], $attempt, $max_attempts, $disable_after);

			$failed++;

			continue;
		}

		$timestamp = (string)Signature::now();
		$signature = Signature::compute($timestamp, (string)$row['payload'], $secret);

		$headers = [
			'Content-Type: application/json; charset=utf-8',
			'Accept: application/json',
			'Expect:',
			'X-DockerCart-Event: ' . (string)$row['event_type'],
			'X-DockerCart-Event-Id: ' . (string)$row['event_id'],
			'X-DockerCart-Delivery-Id: ' . (int)$row['delivery_id'],
			Signature::TIMESTAMP_HEADER . ': ' . $timestamp,
			Signature::HEADER . ': ' . $signature,
			'X-DockerCart-Attempt: ' . $attempt,
		];

		try {
			$result = $sender->send((string)$row['url'], $headers, (string)$row['payload'], $timeout);
		} catch (\Throwable $e) {
			$result = [
				'ok'          => false,
				'status'      => 0,
				'body'        => '',
				'error'       => 'worker_error',
				'duration_ms' => 0,
			];
		}

		if ($result['ok']) {
			$db->query("UPDATE `" . DB_PREFIX . "dockercart_webhook_delivery` SET
				`status` = '" . Delivery::STATUS_DELIVERED . "',
				`locked_until` = NULL,
				`response_code` = '" . (int)$result['status'] . "',
				`response_body` = " . ((string)$result['body'] !== '' ? "'" . $db->escape((string)$result['body']) . "'" : 'NULL') . ",
				`duration_ms` = '" . (int)$result['duration_ms'] . "',
				`last_error` = '',
				`date_modified` = NOW()
			 WHERE `delivery_id` = '" . (int)$row['delivery_id'] . "'");

			if ((int)$row['is_test'] === 0) {
				$db->query("UPDATE `" . DB_PREFIX . "dockercart_webhook_subscription` SET
					`consecutive_failures` = '0',
					`last_success_at` = NOW(),
					`date_modified` = NOW()
				 WHERE `subscription_id` = '" . (int)$row['subscription_id'] . "'");
			}

			$delivered++;

			continue;
		}

		webhook_fail($db, $delivery, $row, $result, $attempt, $max_attempts, $disable_after);
		$failed++;
	}

	fwrite(STDOUT, '[webhook-deliver] due=' . count($due->rows)
		. ' delivered=' . $delivered
		. ' failed=' . $failed
		. ' deferred=' . $deferred
		. ' skipped=' . $skipped
		. ' elapsed=' . (time() - $started_at) . "s\n");

	exit(0);
} catch (\Throwable $e) {
	fwrite(STDERR, '[webhook-deliver] FATAL: ' . $e->getMessage() . "\n");
	exit(1);
}
