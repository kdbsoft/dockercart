<?php
/**
 * DockerCart test bootstrap.
 *
 * Dependency-free harness: no PHPUnit, no database, no network, nothing outside
 * the repository. It only loads the pure library classes under test and defines
 * the few OpenCart constants they touch (DBPREFIX, DIR_LOGS) plus a fake DB.
 *
 * Run the suite with `php tools/tests/run.php` (or `make test`).
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "The test suite must be run from the CLI.\n");
	exit(1);
}

define('TESTS_DIR', __DIR__);
define('REPO_ROOT', dirname(__DIR__, 2));
define('UPLOAD_DIR', REPO_ROOT . '/upload');
define('DIR_SYSTEM', UPLOAD_DIR . '/system/');
define('DIR_CONFIG', DIR_SYSTEM . 'config/');
define('DIR_APPLICATION', UPLOAD_DIR . '/admin/');
define('DIR_CATALOG', UPLOAD_DIR . '/catalog/');
define('DB_PREFIX', 'oc_');
define('DIR_LOGS', sys_get_temp_dir() . '/');

// OCMOD shim: with no modifications installed `modification()` is the identity.
if (!function_exists('modification')) {
	function modification($filename) {
		return $filename;
	}
}

require_once DIR_SYSTEM . 'engine/registry.php';
require_once DIR_SYSTEM . 'library/log.php';
require_once DIR_SYSTEM . 'library/encryption.php';
require_once DIR_SYSTEM . 'engine/model.php';
require_once DIR_SYSTEM . 'library/config.php';

/**
 * Mirrors the `library()` autoloader of system/startup.php for the classes this
 * suite exercises (Dockercart\Webhook\* => system/library/dockercart/webhook/*).
 */
spl_autoload_register(static function (string $class): void {
	if (strpos($class, 'Dockercart\\') !== 0) {
		return;
	}

	$parts = explode('\\', $class);
	$class_name = array_pop($parts);
	$namespace = strtolower(implode('/', $parts));
	$snake = strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $class_name));
	$short = substr($snake, strlen('dockercart_'));

	$candidates = [
		DIR_SYSTEM . 'library/' . $namespace . '/' . $snake . '.php',
		DIR_SYSTEM . 'library/dockercart/' . $short . '.php',
	];

	foreach ($candidates as $file) {
		if (is_file($file)) {
			require_once $file;

			return;
		}
	}
});

final class TestFailure extends \RuntimeException {
}

final class Assert {
	public static function true($value, string $message = ''): void {
		if ($value !== true) {
			throw new TestFailure(($message !== '' ? $message . ' — ' : '') . 'expected true, got ' . self::describe($value));
		}
	}

	public static function false($value, string $message = ''): void {
		if ($value !== false) {
			throw new TestFailure(($message !== '' ? $message . ' — ' : '') . 'expected false, got ' . self::describe($value));
		}
	}

	public static function same($expected, $actual, string $message = ''): void {
		if ($expected !== $actual) {
			throw new TestFailure(($message !== '' ? $message . ' — ' : '') . 'expected ' . self::describe($expected) . ', got ' . self::describe($actual));
		}
	}

	public static function notSame($unexpected, $actual, string $message = ''): void {
		if ($unexpected === $actual) {
			throw new TestFailure(($message !== '' ? $message . ' — ' : '') . 'did not expect ' . self::describe($actual));
		}
	}

	public static function contains(string $needle, string $haystack, string $message = ''): void {
		if (strpos($haystack, $needle) === false) {
			throw new TestFailure(($message !== '' ? $message . ' — ' : '') . 'missing "' . $needle . '" in ' . self::describe($haystack, 240));
		}
	}

	public static function notContains(string $needle, string $haystack, string $message = ''): void {
		if (strpos($haystack, $needle) !== false) {
			throw new TestFailure(($message !== '' ? $message . ' — ' : '') . 'unexpected "' . $needle . '" in ' . self::describe($haystack, 240));
		}
	}

	public static function count(int $expected, $value, string $message = ''): void {
		$actual = is_array($value) || $value instanceof \Countable ? count($value) : -1;

		if ($actual !== $expected) {
			throw new TestFailure(($message !== '' ? $message . ' — ' : '') . 'expected count ' . $expected . ', got ' . $actual);
		}
	}

	/**
	 * @param class-string<\Throwable> $class
	 */
	public static function throws(string $class, callable $callback, string $message = ''): void {
		try {
			$callback();
		} catch (\Throwable $e) {
			if ($e instanceof $class) {
				return;
			}

			throw new TestFailure(($message !== '' ? $message . ' — ' : '') . 'expected ' . $class . ', got ' . get_class($e) . ': ' . $e->getMessage());
		}

		throw new TestFailure(($message !== '' ? $message . ' — ' : '') . 'expected ' . $class . ' to be thrown');
	}

	private static function describe($value, int $trim = 120): string {
		if (is_bool($value)) {
			$text = $value ? 'true' : 'false';
		} elseif (is_string($value)) {
			$text = '"' . $value . '"';
		} elseif (is_array($value)) {
			$text = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		} elseif ($value === null) {
			$text = 'null';
		} else {
			$text = (string)$value;
		}

		return strlen($text) > $trim ? substr($text, 0, $trim) . '…' : $text;
	}
}

/**
 * Minimal stand-in for the DB class: records every statement and answers SELECTs
 * from a queue, so the outbox code can be exercised without MariaDB.
 */
final class FakeDb {
	/** @var array<int, string> every executed statement, in order */
	public array $queries = [];

	/** @var array<int, array<int, array<string, mixed>>> queued SELECT answers */
	private array $results = [];

	/** @var array<int, string> SQL => simulated exception */
	private array $failures = [];

	private int $affected = 0;
	private int $last_id = 1;

	/**
	 * Queues a row set for the next SELECT-like query.
	 *
	 * @param array<int, array<string, mixed>> $rows
	 */
	public function pushResult(array $rows): void {
		$this->results[] = $rows;
	}

	/** Makes any statement containing the fragment throw. */
	public function failOn(string $fragment, string $message = 'simulated database error'): void {
		$this->failures[$fragment] = $message;
	}

	public function query(string $sql) {
		$this->queries[] = $sql;

		foreach ($this->failures as $fragment => $message) {
			if (strpos($sql, (string)$fragment) !== false) {
				throw new \Exception($message);
			}
		}

		// INSERT IGNORE / ON DUPLICATE KEY of an existing row affects nothing.
		$this->affected = stripos($sql, 'INSERT IGNORE') !== false ? 1 : 0;

		if (stripos(ltrim($sql), 'SELECT') === 0 || stripos(ltrim($sql), 'SHOW') === 0) {
			$rows = array_shift($this->results) ?: [];

			$result = new \stdClass();
			$result->rows = $rows;
			$result->num_rows = count($rows);
			$result->row = $rows[0] ?? [];

			// A SELECT never consumes the id of a previous INSERT.
			$this->affected = 0;

			return $result;
		}

		$this->last_id++;

		return true;
	}

	public function escape($value) {
		return addslashes((string)($value === null ? '' : $value));
	}

	public function countAffected(): int {
		return $this->affected;
	}

	public function getLastId(): int {
		return $this->last_id;
	}

	/** Asserts that no statement opened/closed a transaction. */
	public function hasTransactionStatement(): bool {
		foreach ($this->queries as $sql) {
			if (preg_match('/^\s*(START TRANSACTION|COMMIT|ROLLBACK|BEGIN)\b/i', $sql)) {
				return true;
			}
		}

		return false;
	}

	/** Asserts that no HTTP-ish work was attempted from the emitter. */
	public function hasHttpStatement(): bool {
		foreach ($this->queries as $sql) {
			if (preg_match('/\b(curl|http|webhook_subscription\s+SET\s+`last_)/i', $sql)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return array<int, string>
	 */
	public function inserts(string $fragment = ''): array {
		return array_values(array_filter($this->queries, static function (string $sql) use ($fragment): bool {
			return stripos($sql, 'INSERT') === 0 || stripos($sql, 'INSERT') !== false
				? ($fragment === '' || strpos($sql, $fragment) !== false)
				: false;
		}));
	}
}

/**
 * Reads a PHP language file into an array of keys (fake global $_ scope).
 *
 * @return array<string, string>
 */
function read_language_file(string $path): array {
	if (!is_file($path)) {
		return [];
	}

	$_ = [];

	include $path;

	return $_;
}

/**
 * @return array<string, string>
 */
function repo_file(string $relative): string {
	$path = REPO_ROOT . '/' . ltrim($relative, '/');

	if (!is_file($path)) {
		throw new TestFailure('missing file: ' . $relative);
	}

	return (string)file_get_contents($path);
}

final class SkippedTest extends \RuntimeException {
}
