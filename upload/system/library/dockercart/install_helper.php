<?php

declare(strict_types=1);

class DockercartInstallHelper {

	public static function getSourcePath(string $path): string {
		if (substr($path, 0, 5) == 'admin') {
			return DIR_APPLICATION . substr($path, 6);
		}

		if (substr($path, 0, 7) == 'catalog') {
			return DIR_CATALOG . substr($path, 8);
		}

		if (substr($path, 0, 5) == 'image') {
			return DIR_IMAGE . substr($path, 6);
		}

		if (substr($path, 0, 6) == 'system') {
			return DIR_SYSTEM . substr($path, 7);
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
}
