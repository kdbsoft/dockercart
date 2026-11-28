<?php
/**
 * Store URL derivation guards (docker/store-url.sh and its consumers).
 *
 * A store was deployed with a real domain but `.env` still carried the
 * `.env.example` placeholder (`DOCKERCART_URL=http://dockercart.local`,
 * `DOCKERCART_SSL_ENABLED=false`). The certificate was valid, yet the storefront
 * emitted 62 `http://dockercart.local` images on an HTTPS page (mixed content →
 * "Not secure"), CSS from `https://dockercart.local` and links to a host no
 * visitor can resolve: the app URL, `oc_setting.config_url/config_ssl` and the
 * Traefik labels disagreed about the store.
 *
 * Three things had to be true for that to happen, and each is guarded here:
 *
 *   1. `.env.example` shipped concrete URL/SSL values, so the wizard's domain
 *      never reached the URL ("explicit wins" kept the placeholder);
 *   2. `DOCKERCART_SSL_ENABLED` was never derived from the start mode, so a
 *      `--traefik --le` deployment kept generating http URLs;
 *   3. `ensure_store_url()` compared only `config_url` and wrote the same http
 *      value into `config_ssl` — the key every asset URL is built from.
 *
 * The resolver itself is exercised through bash when the interpreter is
 * available (the suite is otherwise pure PHP); a sandbox without bash skips that
 * case instead of failing.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

/**
 * Run `docker/store-url.sh` with the given environment and return the resolved
 * values as an associative array.
 *
 * `env -i` on purpose: the process environment is not empty (the Makefile
 * exports .env, which carries DOCKERCART_HTTP_PORT etc.), so a test that
 * inherited it would silently test the developer's own configuration.
 *
 * @param array<string, string> $env KEY=value pairs, everything else unset
 * @return array<string, string>
 */
function store_url_run(array $env): array {
	$assignments = '';
	foreach ($env as $key => $value) {
		$assignments .= $key . '=' . escapeshellarg($value) . ' ';
	}

	$path = getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin';

	$script = 'env -i PATH=' . escapeshellarg($path) . ' ' . $assignments
		. ' bash -c \'. ./docker/store-url.sh; resolve_store_urls; printf "%s\\n%s\\n%s\\n%s" '
		. '"$STORE_HTTP_URL" "$STORE_HTTPS_URL" "$STORE_SSL_ENABLED" "$STORE_URLS_DERIVED"\'';

	$output = [];
	$status = 0;
	exec('cd ' . escapeshellarg(REPO_ROOT) . ' && ' . $script . ' 2>&1', $output, $status);

	if ($status !== 0) {
		throw new TestFailure('docker/store-url.sh failed: ' . implode("\n", $output));
	}

	$lines = array_pad($output, 4, '');

	return [
		'http' => $lines[0],
		'https' => $lines[1],
		'ssl' => $lines[2],
		'derived' => $lines[3],
	];
}

/**
 * Is bash usable for the behavioural checks?
 */
function store_url_bash_available(): bool {
	if (!function_exists('exec')) {
		return false;
	}

	$disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));

	if (in_array('exec', $disabled, true) || is_file(REPO_ROOT . '/docker/store-url.sh') === false) {
		return false;
	}

	exec('command -v bash', $output, $status);

	return $status === 0;
}

return [
	'.env.example: the URL and SSL flag are not pre-filled' => static function (): void {
		$template = repo_file('.env.example');

		foreach (['DOCKERCART_URL', 'DOCKERCART_HTTPS_URL', 'DOCKERCART_SSL_ENABLED'] as $key) {
			// A concrete value here is what pinned every fresh install to
			// http://dockercart.local regardless of the domain the wizard asked for.
			Assert::false(
				(bool)preg_match('/^' . $key . '=/m', $template),
				$key . ' must stay commented out in .env.example — it is derived (see docker/store-url.sh)'
			);
		}

		Assert::contains('DOCKERCART_DOMAIN=dockercart.local', $template, '.env.example must still ship the domain default');
	},

	'compose: URLs are derived in the app, domain and ports are passed in' => static function (): void {
		foreach (['docker-compose.yml', 'docker-compose.traefik.yml'] as $file) {
			$compose = repo_file($file);

			Assert::contains('DOCKERCART_URL=${DOCKERCART_URL:-}', $compose, $file . ' must not default the URL to a placeholder host');
			Assert::contains('DOCKERCART_HTTPS_URL=${DOCKERCART_HTTPS_URL:-}', $compose, $file . ' must not default the https URL to a placeholder host');
			Assert::contains('DOCKERCART_DOMAIN=${DOCKERCART_DOMAIN:-', $compose, $file . ' must pass the domain');
			Assert::contains('DOCKERCART_HTTP_PORT=${DOCKERCART_HTTP_PORT:-', $compose, $file . ' must pass the public http port');
			Assert::contains('DOCKERCART_HTTPS_PORT=${DOCKERCART_HTTPS_PORT:-', $compose, $file . ' must pass the public https port');

			// Every role that regenerates config.php inside the shared ./upload
			// bind mount needs the same inputs, or it writes its own host into it.
			$scheduler_starts = substr_count($compose, 'DOCKERCART_ROLE=scheduler');
			Assert::true($scheduler_starts >= 1, $file . ' lost the scheduler role');

			$backup_starts = substr_count($compose, 'DOCKERCART_ROLE=backup');
			if ($backup_starts > 0) {
				Assert::true(
					substr_count($compose, 'DOCKERCART_DOMAIN=${DOCKERCART_DOMAIN:-') >= 3,
					$file . ': apache, scheduler and backup-worker must all receive DOCKERCART_DOMAIN'
				);
			}
		}
	},

	'compose: TLS overrides force the app into https URL generation' => static function (): void {
		$checked = 0;

		foreach (glob(REPO_ROOT . '/docker-compose*.yml') ?: [] as $path) {
			$name = basename($path);
			$contents = (string)file_get_contents($path);

			$is_tls = strpos($contents, 'tls=true') !== false
				|| strpos($contents, 'dockercart.selfsigned.conf') !== false
				|| strpos($contents, 'dockercart.ssl.conf') !== false
				|| strpos($name, 'traefik.le') !== false
				|| strpos($name, 'traefik.ssl') !== false;

			if (!$is_tls) {
				continue;
			}

			$checked++;

			// Literal on purpose: the mode is authoritative, so a stale
			// DOCKERCART_SSL_ENABLED=false in an existing .env cannot turn
			// https URL generation off again.
			Assert::contains('DOCKERCART_SSL_ENABLED=true', $contents, $name . ' must set DOCKERCART_SSL_ENABLED=true for the app');
			Assert::notContains('DOCKERCART_SSL_ENABLED=${DOCKERCART_SSL_ENABLED', $contents, $name . ' must not let .env override the TLS mode');
		}

		Assert::true($checked >= 4, 'expected the four TLS compose overrides (ssl, le, traefik.ssl, traefik.le)');
	},

	'entrypoint: resolves the URL once, injects it, and syncs BOTH setting rows' => static function (): void {
		$entrypoint = repo_file('docker/entrypoint.sh');

		Assert::contains('. /usr/local/bin/store-url.sh', $entrypoint, 'the entrypoint must source the shared resolver');
		Assert::contains('COPY docker/store-url.sh /usr/local/bin/store-url.sh', repo_file('Dockerfile'), 'the resolver must ship in the image');
		Assert::contains('apply_store_url "$root_config" "$admin_config"', $entrypoint, 'generated configs must receive the resolved URL');

		// Guard the regression directly: reading the URL from the environment in
		// the generated config is what let config.php disagree with the entrypoint.
		Assert::notContains("env('DOCKERCART_URL'", $entrypoint, 'config.php must carry the resolved URL, not re-read a possibly stale env var');
		Assert::notContains("env('DOCKERCART_SSL_ENABLED'", $entrypoint, 'the SSL flag must come from the resolver');

		// config_ssl is what builds every asset URL on an https page, so the
		// drift check must compare it, not only config_url.
		Assert::contains('config_ssl', $entrypoint, 'config_ssl must be written');
		Assert::contains('COALESCE(MAX(CASE WHEN \`key\` = \'config_ssl\'', $entrypoint, 'config_ssl must take part in the drift check');
		Assert::contains('"${url}|${ssl_url}"', $entrypoint, 'both URLs must be compared before skipping the update');
	},

	'start.sh and health-check.sh use the same resolver' => static function (): void {
		$start = repo_file('start.sh');

		Assert::contains('. ./docker/store-url.sh', $start, 'start.sh must source the shared resolver');
		Assert::contains('resolve_store_urls', $start, 'start.sh must resolve the URL instead of re-implementing it');
		Assert::contains('export DOCKERCART_SSL_ENABLED=true', $start, 'a TLS start mode must export the SSL flag');

		// The old inline derivation is what silently lost the port suffix and the
		// placeholder rule; it must not come back.
		Assert::notContains('DOCKERCART_URL_PORT_SUFFIX', $start, 'start.sh must not carry its own URL derivation');

		$health = repo_file('health-check.sh');
		Assert::contains('. ./docker/store-url.sh', $health, 'health-check.sh must probe the URL the store is configured with');
		Assert::contains('$(store_effective_url)', $health, 'health-check.sh must use the resolved URL');
	},

	'resolver: a leftover placeholder never wins over a real domain' => static function (): void {
		if (!store_url_bash_available()) {
			throw new SkippedTest('bash is not available in this environment');
		}

		// Exactly the broken deployment: real domain, placeholder URL + SSL off.
		$broken = store_url_run([
			'DOCKERCART_DOMAIN' => 'store.dockercart.net',
			'DOCKERCART_URL' => 'http://dockercart.local',
			'DOCKERCART_HTTPS_URL' => 'https://dockercart.local',
			'DOCKERCART_SSL_ENABLED' => 'false',
		]);

		Assert::same('http://store.dockercart.net/', $broken['http'], 'the placeholder http URL must be dropped');
		Assert::same('https://store.dockercart.net/', $broken['https'], 'the placeholder https URL must be dropped');
		Assert::same('1', $broken['derived'], 'the result must be reported as derived');

		// Local development: domain and placeholder agree, so the URL stays as is.
		$local = store_url_run([
			'DOCKERCART_DOMAIN' => 'dockercart.local',
			'DOCKERCART_URL' => 'http://dockercart.local:8080',
			'DOCKERCART_HTTPS_URL' => 'https://dockercart.local',
			'DOCKERCART_HTTP_PORT' => '8080',
		]);

		Assert::same('http://dockercart.local:8080/', $local['http'], 'an explicit local URL must be kept');
		Assert::same('0', $local['derived'], 'explicit URLs must not be reported as derived');

		// Derivation from the domain + public ports.
		$derived = store_url_run([
			'DOCKERCART_DOMAIN' => 'shop.example.com',
			'DOCKERCART_HTTP_PORT' => '8080',
			'DOCKERCART_HTTPS_PORT' => '8443',
			'DOCKERCART_SSL_ENABLED' => 'TRUE',
		]);

		Assert::same('http://shop.example.com:8080/', $derived['http'], 'http URL must carry the public port');
		Assert::same('https://shop.example.com:8443/', $derived['https'], 'https URL must carry the public port');
		Assert::same('true', $derived['ssl'], 'the SSL flag must be normalised');

		// Defaults omit the standard ports.
		$defaults = store_url_run(['DOCKERCART_DOMAIN' => 'shop.example.com']);

		Assert::same('http://shop.example.com/', $defaults['http'], 'port 80 must be omitted from the URL');
		Assert::same('https://shop.example.com/', $defaults['https'], 'port 443 must be omitted from the URL');
		Assert::same('false', $defaults['ssl'], 'the SSL flag must default to false');
	},
];
