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

function robots_bash_available(): bool {
	if (!function_exists('exec') || is_file(REPO_ROOT . '/docker/store-url.sh') === false) {
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
		];

		foreach (['https://store.dockercart.net', 'http://dockercart.local:8080', ''] as $store_url) {
			foreach ($cases as $index => $body) {
				$expected = Robots::normalise($body, $store_url);
				[$status, $actual] = robots_bash_fix($body, $store_url);

				$changed = $expected !== $body;
				Assert::same($changed ? 0 : 1, $status, 'bash must report whether it rewrote the file (case ' . $index . ', url "' . $store_url . '")');
				Assert::same($expected, $actual, 'bash and PHP must produce the same robots.txt (case ' . $index . ', url "' . $store_url . '")');
			}
		}
	},

	'robots: the boot repair runs on an existing file, the admin follows the library' => static function (): void {
		$entrypoint = repo_file('docker/entrypoint.sh');

		// The file is the admin's, so the entrypoint must not rewrite it wholesale —
		// only repair the placeholder Sitemap line before giving up.
		Assert::contains('store_robots_fix_sitemap "$robots_file" "$url"', $entrypoint, 'the entrypoint must repair a placeholder sitemap');
		Assert::contains('. /usr/local/bin/store-url.sh', $entrypoint, 'the entrypoint must source the shared resolver that ships the repair');
		Assert::contains('COPY docker/store-url.sh /usr/local/bin/store-url.sh', repo_file('Dockerfile'), 'the repair must ship in the image');

		$controller = repo_file('upload/admin/controller/setting/indexing.php');

		Assert::contains('use Dockercart\\Seo\\Robots;', $controller, 'the admin page must use the shared rule');
		Assert::contains('Robots::normalise($robots, $this->storeUrl())', $controller, 'saving must normalise the Sitemap host');
		Assert::contains('Robots::normalise((string)$data[\'config_indexing_robots\'], $this->storeUrl())', $controller, 'the editor must show what saving would write');

		// start.sh drops a stale placeholder URL from .env instead of only warning.
		$start = repo_file('start.sh');

		Assert::contains('heal_placeholder_env_urls', $start, 'start.sh must drop a leftover placeholder URL from .env');
		Assert::contains('store_url_is_placeholder "$value" "$domain"', $start, 'only the placeholder host may be dropped — a real explicit URL is operator-owned');
	},
];
