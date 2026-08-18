<?php
declare(strict_types=1);

class ModelToolUpdate extends Model {
	private const UPDATE_DIR = 'dockercart_update/';
	private const DEFAULT_REMOTE = 'https://github.com/kdbsoft/dockercart';
	private const DEFAULT_BRANCH = 'main';
	private const STALE_GRACE = 30; // seconds before an unstarted run counts as stale

	private function updateDir(): string {
		return rtrim(DIR_STORAGE, '/') . '/' . self::UPDATE_DIR;
	}

	private function statusFile(): string {
		return $this->updateDir() . 'status.json';
	}

	private function lockFile(): string {
		return $this->updateDir() . 'lock';
	}

	public function getLocalVersion(): string {
		if (defined('DOCKERCART_VERSION')) {
			return (string)DOCKERCART_VERSION;
		}

		if (defined('VERSION')) {
			return (string)VERSION;
		}

		return '';
	}

	public function getRemoteVersion(string $remote, string $branch, int $timeout = 30): ?string {
		// VERSION lives in upload/ since the bind-mount refactor; fall back to
		// the repo root for backwards compatibility with older releases/forks.
		$paths = ['/upload/VERSION', '/VERSION'];

		foreach ($paths as $path) {
			$version = $this->fetchRemoteFile(rtrim($remote, '/') . '/raw/' . $branch . $path, $timeout);

			if ($version !== null) {
				return $version;
			}
		}

		return null;
	}

	public function getRemoteChangelog(string $remote, string $branch, int $timeout = 5): ?string {
		return $this->fetchRemoteFile(rtrim($remote, '/') . '/raw/' . $branch . '/CHANGELOG.md', $timeout);
	}

	private function fetchRemoteFile(string $url, int $timeout): ?string {
		$ch = curl_init($url);

		if ($ch === false) {
			return null;
		}

		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_TIMEOUT        => $timeout,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_USERAGENT      => 'DockerCart-SystemUpdate'
		]);

		$response = curl_exec($ch);
		$http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		if ($response === false || $http_code < 200 || $http_code >= 300) {
			return null;
		}

		$content = trim((string)$response);

		return $content === '' ? null : $content;
	}

	/**
	 * Probes the remote repo's VERSION and CHANGELOG endpoints and returns a
	 * human-readable explanation for the caller's diagnostics/logging. Used by
	 * the scheduled worker so a failure to fetch (e.g. no network) is visible
	 * instead of silently ending up with an empty changelog.
	 *
	 * @return array{version: string, changelog: string}
	 */
	public function getRemoteDiagnostics(string $remote, string $branch): array {
		$diagnostics = [
			'version'   => $this->probeRemoteUrl(rtrim($remote, '/') . '/raw/' . $branch . '/upload/VERSION'),
			'changelog' => $this->probeRemoteUrl(rtrim($remote, '/') . '/raw/' . $branch . '/CHANGELOG.md'),
		];

		return $diagnostics;
	}

	/**
	 * Attempts a single HTTP GET and returns why it succeeded or failed.
	 */
	private function probeRemoteUrl(string $url): string {
		$ch = curl_init($url);

		if ($ch === false) {
			return 'curl_init failed';
		}

		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_TIMEOUT        => 8,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_USERAGENT      => 'DockerCart-SystemUpdate'
		]);

		$response   = curl_exec($ch);
		$http_code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curl_error = curl_error($ch);

		curl_close($ch);

		if ($response === false) {
			return $curl_error !== '' ? $curl_error : 'request failed';
		}

		if ($http_code < 200 || $http_code >= 300) {
			return 'HTTP ' . $http_code;
		}

		$content = trim((string)$response);

		if ($content === '') {
			return 'empty body';
		}

		return 'OK';
	}

	/**
	 * Extract the changelog section for a given version (or the first section)
	 * from a Keep-a-Changelog style markdown document.
	 */
	public function parseChangelog(string $content, string $version): string {
		$lines = preg_split('/\r?\n/', $content);

		if ($lines === false) {
			return '';
		}

		$blocks = [];
		$current_version = '';
		$current = null;

		foreach ($lines as $line) {
			if (preg_match('/^#{1,3}\s*(?:\[?v?(\d+\.\d+\.\d+[^\s\]]*)\]?)/i', $line, $m)) {
				if ($current !== null) {
					$blocks[$current_version] = trim($current);
				}

				$current_version = $m[1];
				$current = '';
			} elseif ($current !== null) {
				$current .= $line . "\n";
			}
		}

		if ($current !== null) {
			$blocks[$current_version] = trim($current);
		}

		$result = '';

		if ($version !== '' && isset($blocks[$version])) {
			$result = $blocks[$version];
		} elseif (!empty($blocks)) {
			$result = reset($blocks);
		} else {
			$result = trim($content);
		}

		if (mb_strlen($result) > 2000) {
			$result = mb_substr($result, 0, 2000) . "\n…";
		}

		return $result;
	}

	public function getConfig(): array {
		$remote = $this->config->get('dockercart_update_remote');
		$branch = $this->config->get('dockercart_update_branch');

		return [
			'remote' => $remote ? (string)$remote : self::DEFAULT_REMOTE,
			'branch' => $branch ? (string)$branch : self::DEFAULT_BRANCH
		];
	}

	public function saveConfig(string $remote, string $branch): void {
		// Defense in depth: the worker executes code from this repository.
		if (!preg_match('#^https://#i', $remote)) {
			throw new InvalidArgumentException('Repository URL must start with https://');
		}

		$this->load->model('setting/setting');

		$data = [
			'dockercart_update_remote' => $remote,
			'dockercart_update_branch' => $branch
		];

		$this->model_setting_setting->updateSetting('dockercart_update', $data);
	}

	public function isRunning(): bool {
		$lock = $this->lockFile();

		if (!is_file($lock)) {
			return false;
		}

		// If the lock file exists but no process holds the lock, treat as not running.
		$fp = @fopen($lock, 'r');

		if ($fp === false) {
			return false;
		}

		$locked = !flock($fp, LOCK_EX | LOCK_NB);
		flock($fp, LOCK_UN);
		fclose($fp);

		return $locked;
	}

	public function getStatus(): array {
		$file = $this->statusFile();

		if (!is_file($file)) {
			return ['running' => false, 'done' => false, 'error' => null, 'pid' => null];
		}

		$content = @file_get_contents($file);
		$status = null;

		// The worker writes status.json atomically (tmp + rename), but a
		// transient read (or a leftover from a non-atomic writer) can still
		// yield invalid JSON — retry briefly before declaring corruption so a
		// single bad read never kills the progress polling in the admin.
		for ($i = 0; $i < 5; $i++) {
			$status = json_decode((string)$content, true);

			if (is_array($status)) {
				break;
			}

			usleep(100000);
			$content = @file_get_contents($file);
		}

		if (!is_array($status)) {
			return ['running' => false, 'done' => false, 'error' => 'Corrupted status file', 'pid' => null];
		}

		$status['running'] = empty($status['done']) && empty($status['error']);

		// A run is stale when the status says it is in progress but nobody
		// holds the lock anymore (the worker died: container restart, OOM...).
		// Grace period so the brief window between "start() seeded queued" and
		// "worker acquired the lock" does not trigger a false positive.
		if ($status['running'] && !$this->isRunning()) {
			$started = (int)strtotime((string)($status['started_at'] ?? ''));

			if ($started > 0 && (time() - $started) > self::STALE_GRACE) {
				$status['stale'] = true;
			}
		}

		// Once a run is done, drop the cached remote-version check so the next
		// page view re-fetches from the network instead of serving the stale
		// pre-update comparison for the remainder of the TTL.
		if (!empty($status['done'])) {
			$this->load->model('setting/setting');
			$this->model_setting_setting->updateSetting('dockercart_update', ['dockercart_update_last_check' => '0']);
		}

		return $status;
	}

	/**
	 * Recovers from a dead update run: restores the maintenance mode value the
	 * worker saved before switching it on, and removes leftover status/tmp
	 * files so a fresh update can be started.
	 */
	public function reset(): void {
		$updateDir = $this->updateDir();

		// The worker writes maintenance_prev BEFORE switching maintenance on,
		// so a missing file means the dead run never touched it — in that case
		// leave the current DB value alone instead of forcing it to '0'.
		$saved = @file_get_contents($updateDir . 'maintenance_prev');

		if ($saved !== false && preg_match('/^[01]$/', trim($saved))) {
			$prefix = defined('DB_PREFIX') ? DB_PREFIX : 'oc_';

			$this->db->query("UPDATE `{$prefix}setting` SET `value`='" . $this->db->escape(trim($saved)) . "' WHERE `code`='config' AND `key`='config_maintenance'");

			@unlink($updateDir . 'maintenance_prev');
		}

		@unlink($this->statusFile());

		$this->removeDirectory($updateDir . 'tmp/');
	}

	private function removeDirectory(string $dir): void {
		if (!is_dir($dir)) {
			return;
		}

		$iter = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($iter as $file) {
			if ($file->isDir()) {
				@rmdir($file->getPathname());
			} else {
				@unlink($file->getPathname());
			}
		}

		@rmdir($dir);
	}

	/**
	 * Returns the cached update info without hitting the network. Used by the
	 * header and the update page so loading the admin never blocks on a remote
	 * request; the cache is kept warm by the scheduler worker and refreshed
	 * on demand by the AJAX force-check.
	 */
	public function getCachedUpdateInfo(): array {
		$local = $this->getLocalVersion();
		$cachedVersion = (string)$this->config->get('dockercart_update_remote_version');
		$lastCheck = (int)$this->config->get('dockercart_update_last_check');

		return [
			'update_available' => $cachedVersion !== '' && version_compare($cachedVersion, $local, '>'),
			'local'            => $local,
			'remote'           => $cachedVersion,
			'changelog'        => (string)$this->config->get('dockercart_update_changelog'),
			'checked_at'       => $lastCheck,
			'cached'           => true,
			'fetch_failed'     => ($cachedVersion === '')
		];
	}

	/**
	 * Performs a network fetch of the remote version/changelog and persists the
	 * result to the cache. Used by the manual "Check" button (forced) so the
	 * client can pull fresh data on demand; page/header renders must instead use
	 * getCachedUpdateInfo() so they never block on the remote repo.
	 */
	public function refreshUpdateInfo(): array {
		$local = $this->getLocalVersion();
		$cfg    = $this->getConfig();
		$remote = $this->getRemoteVersion($cfg['remote'], $cfg['branch'], 10);
		$changelog = '';

		if ($remote !== null) {
			$raw = $this->getRemoteChangelog($cfg['remote'], $cfg['branch'], 5);

			if ($raw !== null) {
				$changelog = $this->parseChangelog($raw, $remote);
			}
		}

		// Persist even on failure so the header/auto-check keep serving a
		// (possibly stale) cached snapshot rather than refetching every load.
		$effectiveRemote = $remote ?? (string)$this->config->get('dockercart_update_remote_version');
		$this->saveCheckCache($effectiveRemote, $changelog);

		return [
			'update_available' => $effectiveRemote !== '' && version_compare($effectiveRemote, $local, '>'),
			'local'            => $local,
			'remote'           => $effectiveRemote,
			'changelog'        => $changelog,
			'cached'           => false,
			'fetch_failed'     => ($remote === null)
		];
	}

	public function saveCheckCache(string $remoteVersion, string $changelog): void {
		$this->load->model('setting/setting');

		$data = [
			'dockercart_update_last_check'    => (string)time(),
			'dockercart_update_remote_version' => $remoteVersion,
			'dockercart_update_changelog'     => $changelog
		];

		$this->model_setting_setting->updateSetting('dockercart_update', $data);
	}
}
