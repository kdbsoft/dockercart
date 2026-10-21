<?php
/**
 * Migration: schema, settings and scheduler wiring (static SQL checks).
 *
 * `make migrate` re-runs every *.sql file, so the migration must be idempotent.
 * These checks catch the two classic regressions: a missing `IF NOT EXISTS` and
 * a settings insert that would duplicate rows on the second run.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

const WEBHOOK_MIGRATION = 'docker/mysql/migrations/20261025_webhooks.sql';

function webhook_migration_sql(): string {
	return repo_file(WEBHOOK_MIGRATION);
}

return [
	'migration: creates both tables with IF NOT EXISTS' => static function (): void {
		$sql = webhook_migration_sql();

		foreach (['oc_dockercart_webhook_subscription', 'oc_dockercart_webhook_delivery'] as $table) {
			Assert::contains('CREATE TABLE IF NOT EXISTS `' . $table . '`', $sql, $table . ' must be created idempotently');
		}
	},

	'migration: uses InnoDB + utf8mb4' => static function (): void {
		Assert::same(2, substr_count(webhook_migration_sql(), 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'), 'both tables must use InnoDB + utf8mb4');
	},

	'migration: the delivery outbox enforces one row per subscription and event' => static function (): void {
		$sql = webhook_migration_sql();

		Assert::contains('UNIQUE KEY `uniq_webhook_delivery_event` (`subscription_id`, `event_id`)', $sql);
		Assert::contains('KEY `idx_webhook_delivery_due` (`status`, `next_attempt_at`)', $sql, 'the worker claims due rows by (status, next_attempt_at)');
	},

	'migration: the outbox stores the exact body and the retry state' => static function (): void {
		$sql = webhook_migration_sql();

		foreach (['`payload` MEDIUMTEXT NOT NULL', '`attempts`', '`next_attempt_at`', '`locked_until`', '`response_code`', '`response_body`', '`last_error`', '`occurred_at`', '`is_test`'] as $column) {
			Assert::contains($column, $sql);
		}
	},

	'migration: the subscription keeps the secret encrypted plus a hint' => static function (): void {
		$sql = webhook_migration_sql();

		Assert::contains('`secret_encrypted`', $sql);
		Assert::contains('`secret_hint`', $sql);
		Assert::notContains('`secret` ', $sql, 'the plain secret must not have a column');
		Assert::notContains('secret VARCHAR', $sql);
	},

	'migration: subscription state supports auto-disable and counters' => static function (): void {
		$sql = webhook_migration_sql();

		foreach (['`consecutive_failures`', '`disabled_reason`', '`disabled_at`', '`last_success_at`', '`last_failure_at`', '`created_by_api_key_id`', '`created_by_user_id`', '`full_payload`'] as $column) {
			Assert::contains($column, $sql);
		}
	},

	'migration: inserts the five settings idempotently' => static function (): void {
		$sql = webhook_migration_sql();

		$settings = [
			'config_webhook_status',
			'config_webhook_timeout',
			'config_webhook_max_attempts',
			'config_webhook_disable_after',
			'config_webhook_log_retention_days',
		];

		foreach ($settings as $key) {
			Assert::contains("'" . $key . "'", $sql);
		}

		// One guarded INSERT ... SELECT per setting: re-running cannot duplicate.
		Assert::same(count($settings), substr_count($sql, "INSERT INTO `oc_setting`"), 'one guarded insert per setting');
		Assert::same(count($settings), substr_count($sql, 'WHERE NOT EXISTS ('), 'every setting insert needs its guard');
	},

	'migration: registers both scheduler tasks as hidden system tasks' => static function (): void {
		$sql = webhook_migration_sql();

		Assert::contains("('webhook_deliver', 'Webhook delivery'", $sql);
		Assert::contains("('webhook_cleanup', 'Webhook log cleanup'", $sql);
		Assert::contains('php /var/www/html/bin/dockercart_webhook_deliver.php', $sql);
		Assert::contains('php /var/www/html/bin/dockercart_webhook_cleanup.php', $sql);
		Assert::same(2, substr_count($sql, 'ON DUPLICATE KEY UPDATE'), 'both task inserts must be upserts');
		Assert::same(2, substr_count($sql, '`is_system` = 1'));
		Assert::contains("'* * * * *'", $sql, 'the delivery worker must run every minute');
		Assert::contains("'daily'", $sql);
	},

	'migration: the worker scripts referenced by the tasks exist' => static function (): void {
		Assert::true(is_file(UPLOAD_DIR . '/bin/dockercart_webhook_deliver.php'));
		Assert::true(is_file(UPLOAD_DIR . '/bin/dockercart_webhook_cleanup.php'));
	},

	'migration: does not touch core tables destructively' => static function (): void {
		$sql = webhook_migration_sql();

		foreach (['DROP TABLE', 'TRUNCATE', 'DELETE FROM', 'ALTER TABLE'] as $statement) {
			Assert::notContains($statement, $sql, 'the webhook migration must be additive');
		}
	},

	'migration: the panel settings are saved with updateSetting (never editSetting)' => static function (): void {
		$controller = repo_file('upload/admin/controller/setting/webhook.php');

		Assert::contains("updateSetting('config'", $controller);
		Assert::notContains('->editSetting(', $controller, 'editSetting would wipe the other store settings');
	},

	'migration: the worker reads the same settings keys the migration seeds' => static function (): void {
		$worker = repo_file('upload/bin/dockercart_webhook_deliver.php');

		foreach (['config_webhook_max_attempts', 'config_webhook_timeout', 'config_webhook_disable_after', 'config_webhook_status'] as $key) {
			Assert::contains($key, $worker);
		}

		Assert::contains('config_webhook_log_retention_days', repo_file('upload/bin/dockercart_webhook_cleanup.php'));
	},
];
