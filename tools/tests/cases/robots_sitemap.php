<?php
/**
 * robots.txt Sitemap guards — the sitemap host follows the store URL.
 *
 * A first boot that ran before the operator's real domain was in place seeded
 * `Sitemap: http://dockercart.local:8080/sitemap.xml`. The file is then owned by
 * the admin (System -> Indexing) and never rewritten by the entrypoint, so the
 * placeholder outlived every later fix: the storefront answered correctly on
 * the real domain while robots.txt (and the Indexing page, which showed the
 * stored body) kept pointing search engines at a host nobody can resolve.
 *
 * Two implementations exist because two processes need the same rewrite:
 *
 *   - `Dockercart\Seo\Robots` — admin page (display + save), PHP;
 *   - `store_robots_fix_sitemap()` in `docker/store-url.sh` — container boot, bash.
 *
 * They must agree byte for byte, so the parity test below feeds the same bodies
 * through both. The static tests guard the wiring that makes the repair happen
 * without anyone opening the admin.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

use Dockercart\Seo\Robots;

/**
 * Run the bash helper on a temp robots.txt and return [status, contents].
 *
 * @param	string	$body		robots.txt contents
 * @param	string	$store_url	store URL handed to the helper
 * @return	array{0: int, 1: string}
 */
function robots_bash_fix(string $body, string $store_url): array {
	$file = tempnam(sys_get_temp_dir(), 'robots');

	if ($file === false) {
		throw new TestFailure('could not create a temp file');
	}

	file_put_contents($file, $body);

	$path = getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin';

	$script = 'env -i PATH=' . escapeshellarg($path)
		. ' bash -c \'. ./docker/store-url.sh; store_robots_fix_sitemap "$1" "$2"\' -- '
		. escapeshellarg($file) . ' ' . escapeshellarg($store_url);

	$output = [];
	$status = 0;
	exec('cd ' . escapeshellarg(REPO_ROOT) . ' && ' . $script . ' 2>&1', $output, $status);

	$contents = (string)file_get_contents($file);

	@unlink($file);

	return [$status, $contents];
}

function robots_bash_available(): bool {	if (!function_exists('exec') || is_file(REPO_ROOT . '/docker/store-url.sh') === false) {
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

const ROBOTS_PLACEHOLDER_BODY = "User-agent: *\nAllow: /\n\nSitemap: http://dockercart.local:8080/sitemap.xml\n";

return [
	'robots: a placeholder Sitemap host is rewritten to the store URL' => static function (): void {
		$fixed = Robots::normalise(ROBOTS_PLACEHOLDER_BODY, 'https://store.dockercart.net/');

		Assert::contains('Sitemap: https://store.dockercart.net/sitemap.xml', $fixed, 'the placeholder host must be replaced');
		Assert::notContains('dockercart.local', $fixed, 'nothing may keep pointing at the placeholder');
		// Everything that is not the Sitemap line stays exactly as it was.
		Assert::contains("User-agent: *\nAllow: /\n", $fixed, 'the rest of the body must survive untouched');
	},

	'robots: a real Sitemap host chosen by the operator is left alone' => static function (): void {
		$body = "User-agent: *\nSitemap: https://cdn.example.net/sitemap.xml\n";

		Assert::same($body, Robots::normalise($body, 'https://store.dockercart.net'), 'a real host is operator-owned');
	},

	'robots: the Sitemap of an HTTPS store is never advertised over http' => static function (): void {
		// The store's own host is normalised to the effective URL: a line written
		// over http (or before TLS was switched on, or with a stale port) sends
		// every crawler through a redirect. config_url is always the http base, so
		// the admin page must derive this from the https one.
		foreach (['http://store.dockercart.net/sitemap.xml', 'http://store.dockercart.net:8080/sitemap.xml', 'https://store.dockercart.net:8443/sitemap.xml'] as $line) {
			$fixed = Robots::normalise('Sitemap: ' . $line . "\n", 'https://store.dockercart.net');

			Assert::same("Sitemap: https://store.dockercart.net/sitemap.xml\n", $fixed, 'the store host must carry the effective scheme and port: ' . $line);
		}

		// An http-only store keeps http.
		Assert::same(
			"Sitemap: http://store.dockercart.net/sitemap.xml\n",
			Robots::normalise("Sitemap: https://store.dockercart.net/sitemap.xml\n", 'http://store.dockercart.net'),
			'an http-only store must not advertise https'
		);
	},

	'robots: an already canonical line is reported as "nothing to do"' => static function (): void {
		$body = "Sitemap: https://store.dockercart.net/sitemap.xml\n";

		Assert::same($body, Robots::normalise($body, 'https://store.dockercart.net'), 'normalising twice must be a no-op');
	},

	'robots: nothing changes on a local install or without a Sitemap line' => static function (): void {
		// The placeholder *is* the store there: a local store must keep its own host.
		Assert::same(ROBOTS_PLACEHOLDER_BODY, Robots::normalise(ROBOTS_PLACEHOLDER_BODY, 'http://dockercart.local:8080'), 'a local install must keep the placeholder');
		Assert::same(ROBOTS_PLACEHOLDER_BODY, Robots::normalise(ROBOTS_PLACEHOLDER_BODY, ''), 'no store URL, no rewrite');

		$no_sitemap = "User-agent: *\nDisallow: /admin/\n";

		Assert::same($no_sitemap, Robots::normalise($no_sitemap, 'https://store.dockercart.net'), 'a body without a Sitemap line must not gain one');
	},

	'robots: every placeholder Sitemap line is rewritten, other hosts are not' => static function (): void {
		$body = "Sitemap: http://dockercart.local/sitemap.xml\nSitemap: https://cdn.example.net/other.xml\n";

		$fixed = Robots::normalise($body, 'https://shop.example.com');

		Assert::same(
			"Sitemap: https://shop.example.com/sitemap.xml\nSitemap: https://cdn.example.net/other.xml\n",
			$fixed,
			'only the placeholder line is rewritten'
		);
	},

	'robots: bash boot repair and the PHP library agree' => static function (): void {
		if (!robots_bash_available()) {
			throw new SkippedTest('bash is not available in this environment');
		}

		$cases = [
			ROBOTS_PLACEHOLDER_BODY,
			"User-agent: *\nSitemap: https://cdn.example.net/sitemap.xml\n",
			"User-agent: *\nDisallow: /admin/\n",
			"Sitemap: http://dockercart.local/sitemap.xml\nSitemap: https://cdn.example.net/other.xml\n",
			"Sitemap: http://dockercart.local/custom-sitemap.xml\n",
			"Sitemap: http://store.dockercart.net/sitemap.xml\n",
			"Sitemap: http://store.dockercart.net:8080/sitemap.xml\n",
			"Sitemap: https://store.dockercart.net/sitemap.xml\n",
		];

		foreach (['https://store.dockercart.net', 'http://dockercart.local:8080', ''] as $store_url) {
			foreach ($cases as $index => $body) {
				$expected = Robots::normalise($body, $store_url);
				[$status, $actual] = robots_bash_fix($body, $store_url);

				$changed = $expected !== $body;
				Assert::same($changed ? 0 : 1, $status, 'bash must report whether it rewrote the file (case ' . $index . ', url "' . $store_url . '")');
				Assert::same($expected, $actual, 'bash and PHP must produce the same robots.txt (case ' . $index . ', url "' . $store_url . '")');

				// Rewriting a canonical file must stay a no-op (the entrypoint runs on
				// every boot and must not touch the file's mtime or owner).
				[$again] = robots_bash_fix($expected, $store_url);
				Assert::same(1, $again, 'a canonical file must not be rewritten again (case ' . $index . ', url "' . $store_url . '")');
			}
		}
	},

	'robots: the boot repair runs on an existing file, the admin follows the library' => static function (): void {
		$entrypoint = repo_file('docker/entrypoint.sh');

		// The file is the admin's, so the entrypoint must not rewrite it wholesale —
		// only repair the placeholder Sitemap line before giving up.
		Assert::contains('store_robots_fix_sitemap "$robots_file" "$url"', $entrypoint, 'the entrypoint must repair a placeholder sitemap');		Assert::contains('. /usr/local/bin/store-url.sh', $entrypoint, 'the entrypoint must source the shared resolver that ships the repair');
		Assert::contains('COPY docker/store-url.sh /usr/local/bin/store-url.sh', repo_file('Dockerfile'), 'the repair must ship in the image');

		$controller = repo_file('upload/admin/controller/setting/indexing.php');

		Assert::contains('use Dockercart\\Seo\\Robots;', $controller, 'the admin page must use the shared rule');
		Assert::contains('Robots::normalise($robots, $this->storeUrl())', $controller, 'saving must normalise the Sitemap host');
		Assert::contains('Robots::normalise((string)$data[\'config_indexing_robots\'], $this->storeUrl())', $controller, 'the editor must show what saving would write');

		// config_url is the http base; advertising the sitemap over http on a TLS
		// store is the bug this guards (entry_url/entry_ssl in setting/store.php).
		Assert::contains("stripos(\$ssl, 'https://')", $controller, 'the Sitemap URL must prefer the https base when the store has one');

		// start.sh drops a stale placeholder URL from .env instead of only warning.
		$start = repo_file('start.sh');

		Assert::contains('heal_placeholder_env_urls', $start, 'start.sh must drop a leftover placeholder URL from .env');
		Assert::contains('store_url_is_placeholder "$value" "$domain"', $start, 'only the placeholder host may be dropped — a real explicit URL is operator-owned');

		// The generated sitemap advertises the same effective URL: config_url is
		// the http base, so using it put http:// <loc> entries in an HTTPS store.
		$sitemap = repo_file('upload/bin/dockercart_sitemap_generate.php');

		Assert::contains("stripos(\$ssl, 'https://')", $sitemap, 'the sitemap must prefer the https base when the store has one');
		Assert::contains('$sitemap_url = $store_url();', $sitemap, 'the sitemap index must use the effective URL');
		Assert::notContains("new Url(\$config->get('config_url')", $sitemap, 'the sitemap must not be built from the http base');

		// The seed branch reads settings (config_indexing_status) and the resolved
		// URL, so it must run after wait_for_mysql/ensure_store_url — before them it
		// would silently fall back to "indexing enabled".
		$seed_call = strpos($entrypoint, "\nensure_robots_txt\n");
		$store_sync = strpos($entrypoint, "\nensure_store_url\n");

		Assert::true($seed_call !== false && $store_sync !== false, 'both entrypoint calls must exist');
		Assert::true($seed_call > $store_sync, 'robots.txt must be seeded after the store URL is synced');
		Assert::contains('config_indexing_status', $entrypoint, 'the seed must honour the indexing switch');
	},

	'robots: a reseed honours the indexing switch' => static function (): void {
		if (!robots_bash_available()) {
			throw new SkippedTest('bash is not available in this environment');
		}

		$entrypoint = repo_file('docker/entrypoint.sh');

		if (preg_match('/^ensure_robots_txt\(\) \{.*?^\}$/ms', $entrypoint, $matches) !== 1) {
			throw new TestFailure('ensure_robots_txt() not found in docker/entrypoint.sh');
		}

		$dir = sys_get_temp_dir() . '/dc-robots-' . bin2hex(random_bytes(6));

		if (!mkdir($dir . '/bin', 0777, true)) {
			throw new TestFailure('cannot create a temp dir');
		}

		register_shutdown_function(static function () use ($dir): void {
			exec('rm -rf ' . escapeshellarg($dir));
		});

		// `mysql` answers the settings query; `php`/`chown`/`chmod` are not needed
		// here (the function only reads one row and writes the file).
		file_put_contents($dir . '/bin/mysql', "#!/bin/sh\necho \"\${FAKE_STATUS-1}\"\n");
		chmod($dir . '/bin/mysql', 0755);

		$runner = "set -u\n"
			. ". " . escapeshellarg(REPO_ROOT . '/docker/store-url.sh') . "\n"
			. "STORE_HTTP_URL='https://store.dockercart.net/'\n"
			. "STORE_HTTPS_URL='https://store.dockercart.net/'\n"
			. "STORE_SSL_ENABLED='true'\n"
			. "resolve_owner() { :; }\n"
			. $matches[0] . "\n"
			. "ensure_robots_txt\n"
			. "cat \"\$ROBOTS_FILE\"\n";

		file_put_contents($dir . '/run.sh', $runner);

		$run = static function (string $status) use ($dir): string {
			$file = $dir . '/robots.txt';

			@unlink($file);

			$path = $dir . '/bin:' . (getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin');
			$output = [];
			$exit = 0;

			exec(
				'cd ' . escapeshellarg($dir) . ' && env -i PATH=' . escapeshellarg($path)
				. ' FAKE_STATUS=' . escapeshellarg($status)
				. ' ROBOTS_FILE=' . escapeshellarg($file)
				. ' bash run.sh 2>&1',
				$output,
				$exit
			);

			Assert::same(0, $exit, 'the seed must succeed: ' . implode("\n", $output));

			return (string)file_get_contents($file);
		};

		// Indexing switched off in the admin: the file must keep saying so — a
		// reseed with the allow template would silently re-enable indexing.
		Assert::same("User-agent: *\nDisallow: /\n", $run('0'), 'a disabled indexer must be reseeded as Disallow: /');

		// Enabled (or never saved): the default template with the resolved URL.
		foreach (['1', ''] as $status) {
			$body = $run($status);

			Assert::contains("User-agent: *", $body, 'the allow template must be seeded (status "' . $status . '")');
			Assert::contains("Sitemap: https://store.dockercart.net/sitemap.xml", $body, 'the seeded sitemap must use the effective URL');
			Assert::contains("Disallow: /admin/", $body, 'the default rules must be present');
		}
	},
];
