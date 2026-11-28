<?php
/**
 * First-install contract between start.sh, the .env wizard and the Makefile.
 *
 * Installing the store is the one moment two questions/values exist that have
 * no meaning afterwards:
 *
 *   - the seed mode (`demo`/`clean`) — consumed by the first schema init only,
 *     so asking for it on an existing store would be a lie;
 *   - the generated admin password — it lives in `.env` and nowhere else, so
 *     the install run is the only chance to show it. Printing it on every
 *     start would leak it into CI output and `make logs`.
 *
 * Both are keyed off the same signal: no MariaDB data volume yet (a normal
 * start/stop never removes it). These checks are static (files only, no
 * Docker, no DB), so they run inside `make test` and fail the build when the
 * wiring is "simplified" back to a bare `DOCKERCART_ENV_FORCE_WIZARD` check.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

return [
	'start.sh: the wizard runs on a fresh volume, not only on an explicit flag' => static function (): void {
		$start = repo_file('start.sh');

		Assert::contains('FIRST_INSTALL=1', $start, 'start.sh must decide whether this run installs the store');
		Assert::contains('docker volume inspect "$DB_VOLUME_NAME"', $start, 'the installation signal is the missing MariaDB data volume');
		Assert::contains('export DB_VOLUME_NAME', $start, 'the volume name must be shared with the wizard');
		Assert::contains('env_file_value .env COMPOSE_PROJECT_NAME', $start, 'the volume name must follow the project name stored in .env');

		// The trigger: .env missing OR forced OR installing.
		Assert::contains('if [ ! -f .env ] || [ "$wizard_forced" = "1" ] || [ "$FIRST_INSTALL" = "1" ]; then', $start, 'a fresh volume must run the wizard');
		Assert::contains('. ./scripts/configure-env.sh', $start, 'start.sh must source the wizard');
	},

	'start.sh: no stale seed-mode prompt messages' => static function (): void {
		$start = repo_file('start.sh');

		// These claimed a prompt/behaviour that no longer exists.
		Assert::notContains('skipping install prompt', $start, 'there is no install prompt in start.sh to skip');
		Assert::notContains('No seed mode recorded', $start, 'the seed key always exists, that branch was unreachable');

		Assert::contains('Fresh install — schema will be seeded with DOCKERCART_SEED_MODE=', $start, 'an install must say which seed mode it uses');
		Assert::contains('store already installed, seed mode not used', $start, 'an existing store must say the seed mode does not apply');
	},

	'start.sh: the admin password is printed on the installation run only' => static function (): void {
		$start = repo_file('start.sh');

		Assert::contains('print_admin_credentials()', $start, 'start.sh must expose the admin credential block');
		Assert::contains('[ "${FIRST_INSTALL:-0}" = "1" ] || return 0', $start, 'the credentials must be gated on an installation run');

		// Both summary paths: the regular one and the standalone Let's Encrypt exit
		// (definition + two call sites).
		Assert::contains("echo -e \"  User:     \${GREEN}\${admin_user}\${NC}\"", $start, 'the admin user must be shown');
		Assert::contains('Password: ${GREEN}${admin_pass}${NC}', $start, 'the admin password must be shown');
		Assert::true(substr_count($start, 'print_admin_credentials') >= 3, 'both exit paths (summary + standalone LE) must print it');
	},

	'configure-env.sh: seed mode is asked when installing, not only after make clean' => static function (): void {
		$wizard = repo_file('scripts/configure-env.sh');

		Assert::contains('installing=1', $wizard, 'the wizard must detect an installation');
		Assert::contains('docker volume inspect "$db_volume"', $wizard, 'the installation signal is the missing MariaDB data volume');
		Assert::contains('if [ -z "$seed_mode" ] || [ "$installing" = "1" ]; then', $wizard, 'a first install must be asked for the seed mode');
		Assert::contains('ask_value seed_mode "Seed mode (demo/clean)"', $wizard, 'the demo/clean question must stay');

		// Non-interactive runs must not depend on a TTY to get a valid value.
		Assert::contains('DOCKERCART_SEED_MODE=demo', repo_file('.env.example'), '.env.example stays the non-interactive default');
	},

	'Makefile: clean documents the seed question it causes' => static function (): void {
		$makefile = repo_file('Makefile');

		Assert::contains('seed mode (demo/clean) again', $makefile, 'make clean must warn that the seed mode is asked again');
		Assert::contains("the wizard asks for demo/clean again", $makefile, 'the closing message must match what happens');
	},
];
