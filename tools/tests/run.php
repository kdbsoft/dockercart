<?php
/**
 * DockerCart test runner.
 *
 * Plain PHP, no framework: every file in tools/tests/cases/ returns an array of
 * `test name => callable`. The runner executes them, prints one line per test
 * and exits non-zero when anything fails, so it can be wired into CI.
 *
 * Usage:
 *   php tools/tests/run.php              # run everything
 *   php tools/tests/run.php webhook_ssrf # run matching case files only
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$filter = $argv[1] ?? '';

$files = glob(TESTS_DIR . '/cases/*.php') ?: [];
sort($files);

$passed = 0;
$failed = 0;
$skipped = 0;
$failures = [];
$started = microtime(true);

foreach ($files as $file) {
	$case = basename($file, '.php');

	if ($filter !== '' && strpos($case, $filter) === false) {
		continue;
	}

	$tests = require $file;

	if (!is_array($tests)) {
		$failed++;
		$failures[] = [$case . ' :: file', 'the case file must return an array of name => callable'];

		continue;
	}

	printf("%s\n", $case);

	foreach ($tests as $name => $test) {
		if (!is_callable($test)) {
			$failed++;
			$failures[] = [$case . ' :: ' . $name, 'not callable'];

			continue;
		}

		try {
			$test();
			$passed++;
			printf("  \033[32m✓\033[0m %s\n", $name);
		} catch (\SkippedTest $e) {
			$skipped++;
			printf("  \033[33m•\033[0m %s (skipped: %s)\n", $name, $e->getMessage());
		} catch (\Throwable $e) {
			$failed++;
			$failures[] = [$case . ' :: ' . $name, get_class($e) . ': ' . $e->getMessage()];
			printf("  \033[31m✗\033[0m %s\n      %s\n", $name, $e->getMessage());
		}
	}
}

$elapsed = microtime(true) - $started;

printf(
	"\n%d passed, %d failed, %d skipped in %.2fs\n",
	$passed,
	$failed,
	$skipped,
	$elapsed
);

if ($failed > 0) {
	printf("\nFailures:\n");

	foreach ($failures as [$name, $message]) {
		printf("  - %s\n      %s\n", $name, $message);
	}

	exit(1);
}

exit(0);
