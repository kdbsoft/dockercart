<?php

declare(strict_types=1);

/**
 * Trait for DockerCart modules that support versioned updates.
 *
 * Usage in a module controller:
 *   class ControllerExtensionModuleDockercartExample extends Controller {
 *       use DockercartModuleUpdater;
 *
 *       public function install() { ... }
 *       public function uninstall() { ... }
 *       // update() is provided by the trait — override upMigrations() to declare per-version migrations
 *   }
 *
 * The trait provides:
 *   - update(array $data) — the entry point for the platform
 *   - runMigration(string $id, callable $callback) — execute an idempotent migration
 *
 * Modules call runMigration() inside upMigrations() to define what happens
 * between versions.
 */
trait DockercartModuleUpdater {
	abstract protected function getModuleCode(): string;

	public function update(array $data) {
		$from = $data['from'] ?? '0.0.0';
		$to = $data['to'] ?? '';
		$token = $data['token'] ?? '';

		if ($token !== '' && !empty($this->session)) {
			$this->session->data['install_token'] = $token;
		}

		$migration_count = 0;
		$migrations = $this->upMigrations();

		foreach ($migrations as $migration) {
			$migration_key = $migration[0] ?? '';
			$callback = $migration[1] ?? null;

			if ($migration_key === '' || !is_callable($callback)) {
				continue;
			}

			if ($this->isMigrationApplied($migration_key)) {
				continue;
			}

			$callback($this->db, $this->registry);
			$this->markMigrationApplied($migration_key);
			$migration_count++;
		}
	}

	/**
	 * Override this to define version-specific migrations.
	 *
	 * @return array of [string $id, callable $callback]
	 */
	protected function upMigrations(): array {
		return array();
	}

	protected function isMigrationApplied(string $id): bool {
		$result = $this->db->query(
			"SELECT `setting_id` FROM `" . DB_PREFIX . "setting`
			 WHERE `code` = 'module_" . $this->db->escape($this->getModuleCode()) . "'
			   AND `key` = 'module_" . $this->db->escape($this->getModuleCode()) . "_migration'
			   AND `value` = '" . $this->db->escape($id) . "'"
		);

		return $result->num_rows > 0;
	}

	protected function markMigrationApplied(string $id): void {
		$this->db->query(
			"INSERT INTO `" . DB_PREFIX . "setting`
			 SET `store_id` = 0,
			     `code` = 'module_" . $this->db->escape($this->getModuleCode()) . "',
			     `key` = 'module_" . $this->db->escape($this->getModuleCode()) . "_migration',
			     `value` = '" . $this->db->escape($id) . "',
			     `serialized` = 0"
		);
	}

	/**
	 * Convenience: run an idempotent SQL migration (IF NOT EXISTS / IF NOT FOUND patterns).
	 */
	protected function migrateSql(string $id, string $sql): void {
		if ($this->isMigrationApplied($id)) {
			return;
		}

		$statements = array_filter(
			array_map('trim', explode(';', $sql)),
			function (string $s): bool { return $s !== ''; }
		);

		foreach ($statements as $statement) {
			$this->db->query($statement);
		}

		$this->markMigrationApplied($id);
	}
}
