<?php
/**
 * Deployment config guards — Traefik ACME resolver name and nginx log directory.
 *
 * Two silent failure modes cost a full debugging session on a clean install, and
 * neither is visible from the application code:
 *
 *   - The router's `tls.certresolver` must name a resolver the *external*
 *     Traefik defines. Traefik's real-world default is `letsencrypt`; a
 *     mismatch makes it serve its own self-signed certificate, which the
 *     browser rejects and Cloudflare reports as error 526. Nothing crashes.
 *   - `./docker/nginx/logs` is a host bind mount, and git does not record
 *     ownership. On a fresh clone the directory belongs to the deploy user, so
 *     the root nginx master cannot create `error.log` unless it either has
 *     CAP_DAC_OVERRIDE or owns the directory — nginx then restart-loops.
 *
 * The checks here are static (files only, no Docker, no DB) so they run inside
 * the normal `make test` suite and fail the build when someone "simplifies" the
 * fixes away. They guard the *contract*: compose defaults, the documented
 * default, .env.example and the preflight in start.sh must stay in step.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

const DEPLOY_RESOLVER_VAR = 'TRAEFIK_CERTRESOLVER';

/**
 * Every docker-compose*.yml of the repository, content keyed by file name.
 *
 * @return array<string, string>
 */
function deploy_compose_files(): array {
	$files = [];

	foreach (glob(REPO_ROOT . '/docker-compose*.yml') ?: [] as $path) {
		$files[basename($path)] = (string)file_get_contents($path);
	}

	ksort($files);

	return $files;
}

/**
 * Default the compose files fall back to: `${TRAEFIK_CERTRESOLVER:-<name>}`.
 *
 * @return array<string, array<int, string>> file name => resolver names
 */
function deploy_compose_resolver_defaults(string $contents): array {
	preg_match_all(
		'/tls\.certresolver=\$\{' . DEPLOY_RESOLVER_VAR . ':-([A-Za-z0-9_-]+)\}/',
		$contents,
		$matches
	);

	return $matches[1];
}

/**
 * Default documented in docs/guide.md (the environment-variable table).
 */
function deploy_documented_resolver_default(): string {
	$guide = repo_file('docs/guide.md');

	if (!preg_match('/^\|\s*`' . DEPLOY_RESOLVER_VAR . '`\s*\|\s*`([^`]+)`/m', $guide, $match)) {
		throw new TestFailure('docs/guide.md has no `' . DEPLOY_RESOLVER_VAR . '` table row');
	}

	return $match[1];
}

/**
 * The uncommented value from .env.example, '' when it is commented out.
 */
function deploy_env_example_resolver(): string {
	if (!preg_match('/^' . DEPLOY_RESOLVER_VAR . '=(\S*)/m', repo_file('.env.example'), $match)) {
		return '';
	}

	return trim($match[1]);
}

return [
	'compose: every router uses the documented ACME resolver name' => static function (): void {
		$documented = deploy_documented_resolver_default();
		$found = [];

		foreach (deploy_compose_files() as $name => $contents) {
			foreach (deploy_compose_resolver_defaults($contents) as $resolver) {
				Assert::same(
					$documented,
					$resolver,
					$name . ' defaults ' . DEPLOY_RESOLVER_VAR . ' to a name docs/guide.md does not document'
				);
				$found[] = $name;
			}
		}

		// The LE override is the one that actually asks for a certificate.
		Assert::contains('docker-compose.traefik.le.yml', implode(',', $found), 'no compose file sets tls.certresolver');
		Assert::same('letsencrypt', $documented, 'letsencrypt is what real Traefik configs call their resolver');
	},

	'.env.example: the resolver is set explicitly, not left to the fallback' => static function (): void {
		$value = deploy_env_example_resolver();

		Assert::true($value !== '', DEPLOY_RESOLVER_VAR . ' must be uncommented in .env.example');
		Assert::same(
			deploy_documented_resolver_default(),
			$value,
			'.env.example and docs/guide.md disagree about the ' . DEPLOY_RESOLVER_VAR . ' default'
		);
	},

	'nginx: every compose file that mounts the log dir keeps it writable for root' => static function (): void {
		$checked = 0;

		foreach (deploy_compose_files() as $name => $contents) {
			if (strpos($contents, 'docker/nginx/logs:/var/log/nginx') === false) {
				continue;
			}

			$checked++;

			// git does not store ownership, so the fix must live in the container:
			// the directory is re-owned before the nginx master starts, and log
			// files created by an earlier run are repaired too.
			Assert::contains('chown 0:0 /var/log/nginx', $contents, $name . ' must take ownership of the log directory');
			Assert::contains('chown 0:0 /var/log/nginx/*.log', $contents, $name . ' must also repair existing log files');
			Assert::contains('exec /docker-entrypoint.sh nginx', $contents, $name . ' must still run the image entrypoint');
			Assert::contains('- CHOWN', $contents, $name . ' needs CAP_CHOWN for that chown');
		}

		Assert::true($checked >= 2, 'expected the log bind mount in docker-compose.yml and docker-compose.traefik.yml');
	},

	'start.sh: resolver preflight, nginx readiness and cert post-check stay wired' => static function (): void {
		$start = repo_file('start.sh');
		$shared = repo_file('scripts/traefik-resolver.sh');

		// Shared with health-check.sh so `make doctor` reports the same thing.
		Assert::contains('scripts/traefik-resolver.sh', $start, 'start.sh must source the shared resolver helper');
		Assert::contains('check_traefik_resolver', $start, 'start.sh must verify the resolver before starting');
		Assert::contains('detect_traefik_resolvers()', $shared, 'the shared helper must discover resolvers');
		Assert::contains('check_traefik_resolver()', $shared, 'the shared helper must verify resolvers');

		// nginx answers the users: waiting only for mariadb+apache reports success
		// while nginx restart-loops.
		Assert::contains('wait_for_healthy "$NGINX_CT"', $start, 'start.sh must wait for nginx to become healthy');
		Assert::contains('assert_edge_cert', $start, 'start.sh must verify the certificate the edge serves');
		Assert::contains('Cert:', $start, 'the start summary must show the certificate state');
	},

	'health-check.sh: restart loops, resolver and edge certificate are checked' => static function (): void {
		$health = repo_file('health-check.sh');

		Assert::contains('scripts/traefik-resolver.sh', $health, 'health-check.sh must reuse the resolver helper');
		Assert::contains('.RestartCount', $health, 'a crash-looping container must not pass as healthy');
		Assert::contains('TRAEFIK DEFAULT CERT', $health, 'the served certificate must be inspected');
		Assert::contains('nonexistent certificate resolver', $health, 'the Traefik log mismatch must be checked');
		Assert::contains('traefik.le', $health, 'the TLS checks must be gated on the Traefik LE mode');
	},
];
