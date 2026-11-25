<?php
/**
 * Data migration for 20261125_blog_advanced_description.sql.
 *
 * Executed by scripts/run-migrations.sh (start/make migrate) and update.sh
 * immediately after the schema migration. The runner records this filename in
 * oc_schema_migrations only after a successful exit.
 */

declare(strict_types=1);

use Dockercart\Blog\AdvancedDescriptionMigration;

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "This migration must run from CLI.\n");
	exit(1);
}

$app_root = '';
$upload_root = '';

foreach ([getenv('DC_APP_ROOT'), '/var/www/html', dirname(__DIR__, 3)] as $candidate) {
	if (!is_string($candidate)) {
		continue;
	}

	if (is_file($candidate . '/upload/config.php')) {
		$app_root = rtrim($candidate, '/');
		$upload_root = $app_root . '/upload';
		break;
	}

	if (is_file($candidate . '/config.php')) {
		$app_root = rtrim($candidate, '/');
		$upload_root = $app_root;
		break;
	}
}

if ($upload_root === '') {
	fwrite(STDERR, "Blog Advanced Description migration: application config not found.\n");
	exit(1);
}

require_once $upload_root . '/config.php';

if (!defined('DIR_APPLICATION')) {
	fwrite(STDERR, "Blog Advanced Description migration: DIR_APPLICATION is not defined.\n");
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
	$registry->set('event', new Event($registry));
	$registry->set('load', new Loader($registry));

	$db = new DB(
		$config->get('db_engine') ?: 'mysqli',
		$config->get('db_hostname') ?: 'mariadb',
		$config->get('db_username') ?: 'dockercart',
		$config->get('db_password') ?: 'dockercart_password',
		$config->get('db_database') ?: 'dockercart',
		$config->get('db_port') ?: '3306'
	);
	$registry->set('db', $db);

	$settings = $db->query("SELECT `key`, `value`, `serialized` FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '0'");

	foreach ($settings->rows as $setting) {
		$config->set(
			(string)$setting['key'],
			(int)$setting['serialized'] === 1 ? json_decode((string)$setting['value'], true) : (string)$setting['value']
		);
	}

	$cache = new Cache($config->get('cache_engine') ?: 'file', (int)($config->get('cache_expire') ?: 3600));
	$registry->set('cache', $cache);

	$result = AdvancedDescriptionMigration::migrate($registry);

	// Search is derived data: a Manticore outage must not fail the destructive
	// DB migration. Converted posts are re-indexed on a best-effort basis here
	// and by the regular full reindex afterwards.
	if ($result['post_ids'] !== []) {
		require_once $upload_root . '/admin/model/extension/module/dockercart_search.php';
		$search = new ModelExtensionModuleDockercartSearch($registry);
		$language_ids = array_map(static fn (array $row): int => (int)$row['language_id'], $db->query(
			"SELECT language_id FROM `" . DB_PREFIX . "language` ORDER BY language_id"
		)->rows);

		foreach ($result['post_ids'] as $post_id) {
			foreach ($language_ids as $language_id) {
				try {
					$search->indexBlogPost($post_id, $language_id);
				} catch (Throwable $e) {
					fwrite(STDERR, "Blog Advanced Description migration: search reindex warning for post {$post_id}: " . $e->getMessage() . "\n");
				}
			}
		}
	}

	fwrite(STDOUT, sprintf(
		"Blog Advanced Description migration: converted=%d preserved=%d deleted_landings=%d\n",
		$result['converted'],
		$result['preserved'],
		$result['deleted_landings']
	));
} catch (Throwable $e) {
	fwrite(STDERR, "Blog Advanced Description migration failed: " . $e->getMessage() . "\n");
	exit(1);
}
