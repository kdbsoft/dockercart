<?php

declare(strict_types=1);

class DockercartInstallHelper {

	public static function getSourcePath(string $path): string {
		// Installed paths must never traverse outside their prefix. The classic
		// installer rejects ".." during validation and the store flow guards it
		// separately — keep the helper itself safe for untrusted input too.
		if (strpos($path, '..') !== false) {
			return '';
		}

		if ($path == 'admin' || substr($path, 0, 6) == 'admin/') {
			return DIR_APPLICATION . substr($path, 6);
		}

		if ($path == 'catalog' || substr($path, 0, 8) == 'catalog/') {
			return DIR_CATALOG . substr($path, 8);
		}

		if ($path == 'image' || substr($path, 0, 6) == 'image/') {
			return DIR_IMAGE . substr($path, 6);
		}

		if ($path == 'system' || substr($path, 0, 7) == 'system/') {
			return DIR_SYSTEM . substr($path, 7);
		}

		// bin/ and cron/ carry CLI scripts and live in the webroot
		if ($path == 'bin' || substr($path, 0, 4) == 'bin/') {
			return dirname(DIR_SYSTEM) . '/bin/' . substr($path, 4);
		}

		if ($path == 'cron' || substr($path, 0, 5) == 'cron/') {
			return dirname(DIR_SYSTEM) . '/cron/' . substr($path, 5);
		}

		return '';
	}

	public static function isDirEmpty(string $dir): bool {
		if (!is_dir($dir)) {
			return false;
		}

		foreach (scandir($dir) as $file) {
			if (!in_array($file, array('.', '..'), true)) {
				return false;
			}
		}

		return true;
	}

	public static function syncGitExclude(array $paths, string $action): void {
		$exclude_file = getenv('GIT_EXCLUDE_FILE') ?: (defined('GIT_EXCLUDE_FILE') ? GIT_EXCLUDE_FILE : '');

		if (!$exclude_file || !is_file($exclude_file)) {
			return;
		}

		$fh = fopen($exclude_file, 'c+');

		if (!$fh) {
			return;
		}

		if (!flock($fh, LOCK_EX)) {
			fclose($fh);

			return;
		}

		$lines = file($exclude_file) ?: array();

		$marker = '# --- DockerCart installer managed entries ---';

		$patterns = array();
		foreach ($paths as $path) {
			$source = self::getSourcePath($path);
			$pattern = '/upload/' . $path;

			if ($source && is_dir($source)) {
				$pattern .= '/**';
			}

			$patterns[] = $pattern;
		}

		$preamble = array();
		$managed = array();
		$past_marker = false;

		foreach ($lines as $line) {
			$trimmed = rtrim($line, "\r\n");

			if ($trimmed === $marker) {
				$past_marker = true;

				continue;
			}

			if ($past_marker) {
				if ($trimmed !== '') {
					$managed[] = $trimmed;
				}
			} else {
				$preamble[] = $line;
			}
		}

		if ($action === 'add') {
			$managed = array_values(array_unique(array_merge($managed, $patterns)));
		} else {
			$managed = array_values(array_diff($managed, $patterns));
		}

		$content = rtrim(implode('', $preamble)) . "\n";

		if (!empty($managed)) {
			$content .= "\n" . $marker . "\n";

			foreach ($managed as $entry) {
				$content .= $entry . "\n";
			}
		} else {
			$content .= "\n";
		}

		ftruncate($fh, 0);
		fwrite($fh, $content);
		fflush($fh);
		flock($fh, LOCK_UN);
		fclose($fh);
	}

	public static function checkWritable(string $source): array {
		$not_writable = array();

		if (!is_dir($source)) {
			return $not_writable;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ($iterator as $item) {
			if ($item->isDir()) {
				continue;
			}

			$relative = substr($item->getPathname(), strlen($source) + 1);
			$parts = explode('/', $relative, 2);

			if (count($parts) < 2) {
				continue;
			}

			$dest = self::getSourcePath($parts[0] . '/' . $parts[1]);

			if ($dest === '') {
				continue;
			}

			if (is_file($dest)) {
				if (!is_writable($dest)) {
					$not_writable[] = $parts[0] . '/' . $parts[1];
				}
			} else {
				$parent = dirname($dest);

				while ($parent !== '/' && !is_dir($parent)) {
					$parent = dirname($parent);
				}

				if (!is_dir($parent) || !is_writable($parent)) {
					$not_writable[] = $parts[0] . '/' . $parts[1];
				}
			}
		}

		return $not_writable;
	}
}
