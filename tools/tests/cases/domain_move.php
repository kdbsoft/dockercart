<?php
/**
 * Domain-move guards — local stand → server with a real domain.
 *
 * Moving a store changes the derived URL everywhere automatically (config.php,
 * config_url/config_ssl, robots.txt), but three things are baked in and used to
 * be forgotten:
 *
 *   1. `sitemap.xml` — absolute <loc>/hreflang entries, gitignored, and no
 *      scheduler task exists by default, so it kept the old host for months.
 *      `ensure_sitemap_matches_url()` now regenerates it when the effective store
 *      URL changes (state in storage/, not oc_setting — see the comment there).
 *   2. The port of the local stand (":8080") — the wizard only fills the ports
 *      when they are empty, so a copied .env keeps them.
 *   3. Content/settings that hold absolute URLs: the stored robots body, a
 *      multistore URL, webhook endpoints, pasted links.
 *
 * `scripts/domain-check.sh` reports 2 and 3 (and a stale robots/sitemap) without
 * changing anything; `make retarget-check` / `make doctor` run it.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

/**
 * Run a shell command and return [exit status, combined output].
 *
 * @param	array<string, string>	$env	extra variables (PATH keeps the caller's)
 * @return	array{0: int, 1: string}
 */
function dm_run(string $command, string $cwd, array $env = []): array {
	$assignments = '';
	$path = $env['PATH'] ?? (getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin');

	unset($env['PATH']);

	foreach ($env as $key => $value) {
		$assignments .= $key . '=' . escapeshellarg($value) . ' ';
	}

	$output = [];
	$status = 0;

	// `env -i` on purpose: `make test` exports .env (DOCKERCART_COMPOSE_FILES,
	// ports, the SSL flag), and a case that inherited those would be testing the
	// developer's own configuration instead of its sandbox.
	exec(
		'cd ' . escapeshellarg($cwd) . ' && env -i PATH=' . escapeshellarg($path) . ' ' . $assignments
		. ' bash -c ' . escapeshellarg($command) . ' 2>&1',
		$output,
		$status
	);

	return [$status, implode("\n", $output)];
}

/**
 * PATH with the sandbox stubs first, so `php`/`mysql` are the fakes.
 */
function dm_path(string $dir): string {
	return $dir . '/bin:' . (getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin');
}

/**
 * Strip ANSI colours — the checks paint their output.
 */
function dm_plain(string $output): string {
	return (string)preg_replace('/\x1b\[[0-9;]*m/', '', $output);
}

function dm_bash_available(): bool {
	if (!function_exists('exec')) {
		return false;
	}

	$disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));

	if (in_array('exec', $disabled, true)) {
		return false;
	}

	$output = [];
	$status = 0;

	exec('command -v bash', $output, $status);

	return $status === 0;
}

/**
 * Temporary directory that is removed when the process ends.
 */
function dm_tmpdir(): string {
	$dir = sys_get_temp_dir() . '/dc-domain-' . bin2hex(random_bytes(6));

	if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
		throw new TestFailure('cannot create a temp dir');
	}

	register_shutdown_function(static function () use ($dir): void {
		exec('rm -rf ' . escapeshellarg($dir));
	});

	return $dir;
}

/**
 * Create an executable stub (`php`, `mysql`, …) in <dir>/bin.
 */
function dm_stub(string $dir, string $name, string $body): void {
	if (!is_dir($dir . '/bin')) {
		mkdir($dir . '/bin', 0777, true);
	}

	file_put_contents($dir . '/bin/' . $name, "#!/bin/sh\n" . $body . "\n");
	chmod($dir . '/bin/' . $name, 0755);
}

/**
 * Copy the shell files the checks under test source.
 */
function dm_copy_sources(string $dir): void {
	foreach (['scripts/load-env.sh', 'scripts/domain-check.sh', 'docker/store-url.sh'] as $file) {
		$target = $dir . '/' . $file;

		if (!is_dir(dirname($target))) {
			mkdir(dirname($target), 0777, true);
		}

		copy(REPO_ROOT . '/' . $file, $target);
		chmod($target, 0755);
	}
}

/**
 * `ensure_sitemap_matches_url()` extracted from the entrypoint, plus the driver
 * that calls it. The function itself is the unit under test — the entrypoint
 * only sources store-url.sh and calls it once.
 */
function dm_entrypoint_runner(string $dir): string {
	$entrypoint = repo_file('docker/entrypoint.sh');

	$matched = preg_match('/^ensure_sitemap_matches_url\(\) \{.*?^\}$/ms', $entrypoint, $matches);

	if ($matched !== 1) {
		throw new TestFailure('ensure_sitemap_matches_url() not found in docker/entrypoint.sh');
	}

	$script = <<<'BASH'
set -u
. ./docker/store-url.sh
STORE_HTTP_URL="http://dockercart.local:8080/"
STORE_HTTPS_URL="https://dockercart.local/"
STORE_SSL_ENABLED="false"
BASH;

	$script .= "\n" . $matches[0] . "\n";
	$script .= <<<'BASH'

export FAKE_LOG
echo "-- first"
ensure_sitemap_matches_url
sleep 0.5
echo "-- again"
ensure_sitemap_matches_url
sleep 0.5
echo "-- tls on"
STORE_SSL_ENABLED="true"
ensure_sitemap_matches_url
sleep 0.5
echo "-- runs: $(wc -l < "$FAKE_LOG" 2>/dev/null || echo 0)"
echo "-- marker: $(cat "$SITEMAP_URL_MARKER" 2>/dev/null)"
BASH;

	file_put_contents($dir . '/runner.sh', $script);

	return $dir . '/runner.sh';
}

return [
	'domain move: sitemap.xml is regenerated once per store-URL change' => static function (): void {
		if (!dm_bash_available()) {
			throw new SkippedTest('bash is not available in this environment');
		}

		$dir = dm_tmpdir();
		dm_copy_sources($dir);
		dm_stub($dir, 'php', 'echo generated >> "$FAKE_LOG"');
		dm_stub($dir, 'mysql', 'echo 1');

		$runner = dm_entrypoint_runner($dir);

		// The generator must look like a file for the guard to consider it installed.
		file_put_contents($dir . '/gen.php', "<?php\n");

		[$status, $output] = dm_run('bash runner.sh', $dir, [
			'PATH' => dm_path($dir),
			'FAKE_LOG' => $dir . '/runs.log',
			'SITEMAP_URL_MARKER' => $dir . '/marker',
			'SITEMAP_GENERATOR' => $dir . '/gen.php',
		]);

		Assert::same(0, $status, 'the runner must succeed: ' . $output);
		Assert::contains("Store URL changed ('<none>' -> 'http://dockercart.local:8080')", $output, 'the first boot must regenerate');
		Assert::contains("Store URL changed ('http://dockercart.local:8080' -> 'https://dockercart.local')", $output, 'switching TLS must regenerate again');
		Assert::contains('-- runs: 2', $output, 'the generator must run once per URL change, not on every boot');
		Assert::contains('-- marker: https://dockercart.local', $output, 'the applied URL must be recorded (without a trailing slash)');
	},

	'domain move: a disabled sitemap feed is skipped but still recorded' => static function (): void {
		if (!dm_bash_available()) {
			throw new SkippedTest('bash is not available in this environment');
		}

		$dir = dm_tmpdir();
		dm_copy_sources($dir);
		dm_stub($dir, 'php', 'echo generated >> "$FAKE_LOG"');
		dm_stub($dir, 'mysql', 'echo 0');

		file_put_contents($dir . '/gen.php', "<?php\n");

		[$status, $output] = dm_run('bash ' . escapeshellarg(dm_entrypoint_runner($dir)), $dir, [
			'PATH' => dm_path($dir),
			'FAKE_LOG' => $dir . '/runs.log',
			'SITEMAP_URL_MARKER' => $dir . '/marker',
			'SITEMAP_GENERATOR' => $dir . '/gen.php',
		]);

		Assert::same(0, $status, 'the runner must succeed: ' . $output);
		Assert::contains('sitemap feed disabled, skip regeneration', $output, 'a disabled feed must not be regenerated');
		Assert::contains('-- runs: 0', $output, 'nothing may be generated while the feed is disabled');
		Assert::contains('-- marker: https://dockercart.local', $output, 'the state must still move forward (no retry loop, no noise)');
	},

	'domain move: the boot hook runs after the store URL is synced' => static function (): void {
		$entrypoint = repo_file('docker/entrypoint.sh');

		Assert::contains('ensure_sitemap_matches_url() {', $entrypoint, 'the entrypoint must carry the regeneration hook');

		$call = strpos($entrypoint, "\nensure_sitemap_matches_url\n");
		$sync = strpos($entrypoint, "\nensure_store_url\n");

		Assert::true($call !== false && $sync !== false, 'both calls must exist');
		Assert::true($call > $sync, 'the sitemap must be regenerated after config_url/config_ssl are synced (the generator reads them)');
		Assert::contains('store_effective_url', $entrypoint, 'the hook must compare the effective store URL');
	},

	'domain move: the check reports a migrated copy (ports, robots, sitemap)' => static function (): void {
		if (!dm_bash_available()) {
			throw new SkippedTest('bash is not available in this environment');
		}

		$dir = dm_tmpdir();
		dm_copy_sources($dir);
		mkdir($dir . '/upload', 0777, true);

		// Exactly the copy a developer makes: real domain, local ports, local files.
		file_put_contents($dir . '/.env', implode("\n", [
			'DOCKERCART_DOMAIN=store.example.com',
			'DOCKERCART_URL=http://dockercart.local:8080',
			'DOCKERCART_HTTP_PORT=8080',
			'DOCKERCART_HTTPS_PORT=443',
			'DOCKERCART_SSL_ENABLED=false',
			'DOCKERCART_COMPOSE_FILES=docker-compose.traefik.yml docker-compose.traefik.le.yml',
			'',
		]));
		file_put_contents($dir . '/upload/robots.txt', "User-agent: *\nSitemap: http://dockercart.local:8080/sitemap.xml\n");
		file_put_contents(
			$dir . '/upload/sitemap.xml',
			'<?xml version="1.0"?><urlset><url><loc>http://dockercart.local:8080/a</loc></url>'
			. '<url><loc>http://dockercart.local:8080/b</loc></url></urlset>'
		);

		[$status, $output] = dm_run('./scripts/domain-check.sh', $dir);
		$output = dm_plain($output);

		Assert::same(1, $status, 'findings must fail the check: ' . $output);
		Assert::contains('store URL: https://store.example.com/', $output, 'the domain + TLS override must drive the expectation');
		Assert::contains('public ports 8080/443', $output, 'the local :8080 leftover must be reported');
		Assert::contains('is the template placeholder — start.sh comments it out automatically', $output, 'a placeholder URL is auto-healed and only a note');
		Assert::contains('robots.txt Sitemap http://dockercart.local:8080/sitemap.xml points at another host', $output, 'the stale robots must be reported');
		Assert::contains('sitemap.xml: 2 <loc> entries on http://dockercart.local:8080', $output, 'the stale sitemap must be reported');
	},

	'domain move: the check is quiet on a consistent stand' => static function (): void {
		if (!dm_bash_available()) {
			throw new SkippedTest('bash is not available in this environment');
		}

		$dir = dm_tmpdir();
		dm_copy_sources($dir);
		mkdir($dir . '/upload', 0777, true);

		file_put_contents($dir . '/.env', implode("\n", [
			'DOCKERCART_DOMAIN=dockercart.local',
			'DOCKERCART_URL=http://dockercart.local:8080',
			'DOCKERCART_HTTP_PORT=8080',
			'DOCKERCART_HTTPS_PORT=443',
			'',
		]));
		file_put_contents($dir . '/upload/robots.txt', "User-agent: *\nSitemap: http://dockercart.local:8080/sitemap.xml\n");
		file_put_contents(
			$dir . '/upload/sitemap.xml',
			'<?xml version="1.0"?><urlset><url><loc>http://dockercart.local:8080/a</loc></url></urlset>'
		);

		[$status, $output] = dm_run('./scripts/domain-check.sh', $dir);
		$output = dm_plain($output);

		Assert::same(0, $status, 'a consistent local stand must pass: ' . $output);
		Assert::contains('Nothing points at another host', $output, 'the summary must say so');
		Assert::contains('non-default ports 8080/443 (fine for a local host)', $output, 'local ports must stay a note, not a finding');
	},

	'domain move: the check is wired into make and CI' => static function (): void {
		$makefile = repo_file('Makefile');

		Assert::contains('retarget-check:', $makefile, 'make retarget-check must exist');
		Assert::contains('./scripts/domain-check.sh', $makefile, 'the target must run the check script');

		$doctor = '';

		if (preg_match('/^doctor:.*?(?=\n[a-z-]+:)/ms', $makefile, $matches) === 1) {
			$doctor = $matches[0];
		}

		Assert::contains('./scripts/domain-check.sh || rc=1', $doctor, 'make doctor must include the domain check');

		$lint = repo_file('.github/workflows/Lint.yml');

		Assert::contains('scripts/*.sh', $lint, 'the syntax job must keep globbing scripts/*.sh (that covers domain-check.sh)');
		Assert::contains('scripts/domain-check.sh', $lint, 'CI must shellcheck the new script too');
	},
];
