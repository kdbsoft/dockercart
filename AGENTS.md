# AGENTS.md

Compact guidance for OpenCode sessions working in this repo.
Canonical developer docs: [`docs/guide.md`](docs/guide.md). Trust it + executable config over prose.

## What this is

PHP 8.5 e-commerce platform, OpenCart 3 MVC fork, shipped as a Docker Compose stack
(nginx → apache/php → mariadb + redis + manticore + scheduler) behind an external Traefik.
App source is in `upload/` (bind-mounted as `/var/www/html`); runtime data lives in
`storage/` (bind-mounted, outside the webroot). All application code and assets live in
these host-side bind mounts — **changes on the host are immediately visible inside
containers, no restart needed**. DB table prefix is `oc_`. Current version is in `VERSION`.

## Tooling paths — easy to get wrong

Composer `vendor-dir` is **`./storage/vendor/`** (set in `composer.json`), not `vendor/`.
All PHP tool binaries therefore live at:

- `./storage/vendor/bin/phpunit`
- `./storage/vendor/bin/phpstan`
- `./storage/vendor/bin/php-cs-fixer`

First-time setup: `composer install` (deps land in `storage/vendor/`). The container
entrypoint auto-runs `composer install --no-dev` when `composer.lock` changes, so dev
deps only exist after a local `composer install`.

## Verifying changes (mirror CI — `.github/workflows/Lint.yml`)

CI runs five parallel jobs on push to `main` and on PRs (`.github/workflows/Lint.yml`):
`syntax`, `phpstan`, `cs-fixer`, `phpunit` (pure unit, no DB), and `phpunit-db`
(DB integration — spins up a MariaDB 11.8 service, loads `docker/mysql/init.sql`
+ all migrations, runs `--testsuite unit` with `DB_*` env vars). Run these locally
before pushing:

```bash
# 1. PHP syntax (skips generated config.php files)
find upload -type f -name "*.php" ! -path 'upload/config.php' ! -path 'upload/admin/config.php' -print0 \
  | xargs -0 -P4 php -l -n

# 2. PHPStan (level 1, scans ./upload/ only — see phpstan.neon)
./storage/vendor/bin/phpstan analyze -a ./storage/vendor/autoload.php --no-progress --memory-limit=512M

# 3. PHP-CS-Fixer (dry-run)
./storage/vendor/bin/php-cs-fixer fix --dry-run --diff

# 4. PHPUnit (suite "unit" -> tests/Unit/)
./storage/vendor/bin/phpunit --no-coverage
```

Run a single test: `./storage/vendor/bin/phpunit --filter TestName tests/Unit/Path/ToTest.php`
PHPUnit bootstrap (`tests/bootstrap.php`) only loads the Composer autoloader — no DB/container.

PHPStan `-a ./storage/vendor/autoload.php` is required (registers the custom extension in
`tools/phpstan/` via composer.json autoload). `reportUnmatchedIgnoredErrors: false`, so stale
`ignoreErrors` entries won't fail the build — don't add new ignores casually.

## Generated files — do not hand-edit

These are written at container start by `docker/entrypoint.sh` from `.env` and overwritten on
every boot:

- `upload/config.php`, `upload/admin/config.php` — app configs
- `robots.txt`, `sitemap.xml`
- `storage/vendor/` — Composer deps
Change behavior by editing `.env` (then `make restart`) or the entrypoint heredoc / Tailwind
input (`tools/tailwind/tailwind-input.css`), not the generated files. For code changes
(Twig, PHP, JS, CSS), just edit the files — no restart needed.

`tailwind.css` is built from `tools/tailwind/tailwind-input.css` via `npm run build:css`
and **is tracked in git** — commit it alongside any CSS/Twig changes that introduce new
Tailwind utilities so the built output stays in sync.

## Database & migrations

- Base schema: `docker/mysql/init.sql` (regenerate from a running DB via `make dump-init` —
  it strips `DEFINER=...` and the `config_encryption` value; creates a `.bak.*` first).
  **Do not edit `init.sql` by hand** — it is a generated dump, always regenerated from a
  live DB with `make dump-init`. Apply changes via migrations instead.
- Migrations: `docker/mysql/migrations/YYYYMMDD_short_description.sql`.
- Migrations **must be idempotent** (`CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`)
  and use `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`.
- Apply migrations with `make migrate` — runs against a **running** `mariadb` container, so
  `make up` first. **`make migrate` does NOT track applied migrations** — it re-runs every
  `*.sql` file each time, so it depends entirely on the idempotency rule above.
  `make update` (→ `update.sh`) instead records applied files in `oc_schema_migrations`
  and skips repeats; use it for normal upgrades.
- DB CLI: `make mariadb`. Backup/restore: `make backup`, `make restore`.
- Configurable product variants use a denormalized `oc_product_variant.variant_hash`
  column (unique per `product_id`) for O(1) `resolveVariant()` lookups. The hash is
  built by `ProductConfigurable::buildVariantHash()` / `buildVariantHashFromValues()` from
  option_value IDs ordered by option_id; `rebuildVariantHashes($product_id)` regenerates
  them after `setConfigurableOptions`. Changing the hash format is a **breaking change**.
  See `docs/guide.md` §11 for the full data model.

## Store settings (`oc_setting`) — save semantics

- `editSetting($code, $data)` **deletes ALL rows with that `code` first**, then re-inserts
  only the POSTed keys — so it is safe ONLY when `$data` carries the complete key set for
  that code (the full store-settings form in `setting/setting.php` / `setting/store.php`).
- For partial saves (a settings page that manages a subset of `config_*` keys, e.g.
  `catalog/review_setting.php`), use `updateSetting($code, $data)` from
  `upload/admin/model/setting/setting.php` — it upserts only the given keys and leaves
  everything else intact.
- **Never call `editSetting('config', ...)` with a subset of config keys** — it wipes ~100
  store settings (`config_limit_admin`, `config_currency`, `config_language`, ...). The
  first symptom is blank 500s on every admin list page (`DivisionByZeroError` in
  pagination from the missing `config_limit_admin`). Restore from `docker/mysql/init.sql`
  if it happens (dump uses mysqldump escaping: `\"` in the dump = plain `"` in the DB).
- Error-display toggles live in `oc_setting` (`code='config'`): `config_error_display`,
  `config_error_log`, `config_error_filename` — set them in the DB (Settings → Server tab
  or the admin "Display Errors" checkbox), not in code. PHP `display_errors` in the image
  is `Off` (`docker/php.prod.ini`, baked into the image).

## Code conventions (differ from typical PHP defaults)

- **Indentation: tabs** (enforced by `.php-cs-fixer.php` `setIndent("\t")`). Spaces will fail CI.
- `declare(strict_types=1);` at the top of new PHP files.
- OpenCart 3 MVC: load models with `$this->load->model('foo/bar')` — never instantiate models
  directly. DB access only via `$this->db->query()` — never raw `mysqli_*` or PDO. Load language
  with `$this->language->load()` before `$this->language->get()`.
- MVC paths:
  - Catalog: `upload/catalog/{controller,model,view/theme/dockercart/template,language}/{section}/{name}.{php,twig}`
  - Admin:   `upload/admin/{controller,model,view/template,language}/{section}/{name}.{php,twig}`
- Locales to keep in sync: `en-gb`, `ru-ua`, `uk-ua` (catalog and admin).
  Mirror any language string changes across all matching `{catalog,admin}/language/{locale}/` files.

## Frontend

- Tailwind CSS 3: `npm run build:css` (minified) or `npm run watch:css` (watch).
  Input `tools/tailwind/tailwind-input.css` → output `upload/catalog/view/theme/dockercart/stylesheet/tailwind.css`.
- ES6+ vanilla JS, **no jQuery** in storefront. Icons: Lucide (not Font Awesome).
- **Tooltips: always use Bootstrap `data-toggle="tooltip"`** with the hint text in `title`.
  A bare `title` (native browser tooltip) is **not** acceptable — it shows nothing here because
  the admin theme only initialises tooltips for `[data-toggle="tooltip"]` elements
  (`upload/admin/view/javascript/common.js`). Wrap the trigger in a `<span>` when it carries a
  Lucide icon:
  ```html
  <span data-toggle="tooltip" title="{{ help_text }}"><i data-lucide="help-circle" width="14" height="14" class="text-muted"></i></span>
  ```

## Custom files & `make update`

`make update` runs `git pull` then re-applies migrations. It **aborts on modified tracked files**
(it checks `--untracked-files=no`, so new untracked files are fine). Don't edit core files
directly — use OCMOD or event hooks. For your own files, use a gitignored prefix so the repo
stays clean: `custom_*`, `dockercart_custom_*`, or `dc_custom_*`. For files you can't rename,
add them to `.git/info/exclude` (local-only). Override the dirty check with `ALLOW_DIRTY=1 make update`;
skip migrations with `SKIP_MIGRATIONS=1 make update`.

## Agent workflow

- **Browser testing: always use `agent-browser`** (not Playwright/Puppeteer/MCP browser
  tools) whenever a browser is needed — checking the storefront, admin, user flows,
  links, or layout. It drives Chrome/Chromium via CDP and gives accessibility-tree
  snapshots with `@eN` refs for reliable interaction. Open the storefront at:
  **http://dockercart.local:8080/** (Traefik mode; hostname must resolve — it is in
  `/etc/hosts` → `127.0.0.1`). Load the usage guide first with
  `agent-browser skills get core` and follow the snapshot-ref workflow
  (`open` → `snapshot -i` → `click @eN` / `fill @eN` → re-snapshot).
- **Never restart containers for code changes.** All app code lives in bind-mounted volumes
  (`upload/`, `storage/`). Edits on the host are instantly visible inside the running
  containers. Restarting is only needed after `docker-compose*.yml` changes, image rebuilds,
  or `.env` modifications — not for PHP/Twig/JS/CSS edits.

- **Ask before irreversible changes.** Always confirm with the user before performing actions
  that are hard or impossible to undo: `make clean`, `make down -v`, database drops/deletes, force-pushing, deleting tracked files, etc. Propose the action,
  explain consequences, and wait for approval.

When working through a `todowrite` task list, after finishing all items the agent must
verify that each task was actually completed:

- Re-read modified files to confirm changes are correct and match the task requirements.
- Re-run relevant verification commands (lint, typecheck, tests) for each changed area.
- Do not mark a task `completed` or finish the session until every item has been verified
  and all checks pass.
- If verification fails, create a follow-up task to fix the issue rather than silently
  moving on.

## Releases — semantic-release owns these files

Releases are automated from `main` via `semantic-release` (`release.config.cjs`,
`.github/workflows/release.yml`). On push to `main` it bumps and commits:
`VERSION`, `package.json`, `package-lock.json`, `CHANGELOG.md`, then tags `vX.Y.Z` and publishes
a GitHub Release. **Do not manually edit `VERSION` or bump `package.json` version** —
semantic-release will conflict.

Commit messages must follow Conventional Commits — `commitlint` runs on PRs and on pushes to
`main` (`.github/workflows/commitlint.yml`, `commitlint.config.cjs`). Release mapping:
`feat:` → minor, `fix:`/`perf:`/`refactor:` → patch, `feat!:` / breaking → major.
Preview with `npm run release:dry-run`.

## Make commands worth remembering

- `make traefik` / `make traefik-ssl` / `make traefik-le` — primary startup: Traefik HTTP / self-signed / Let's Encrypt
- `make shell` — bash into the `apache` container (where the app runs)
- `make scheduler-reload` — SIGHUP the scheduler for code reload without container restart
- `make logs-follow`, `make restart`, `make down`, `make clean` (destructive: removes volumes)
- Standalone `make up` / `make ssl` / `make le` — **rarely used**, only when no external Traefik
- Container names are prefixed `dockercart_` (e.g. `dockercart_apache`, `dockercart_mariadb`).

## Notes

- `build.xml` is a leftover OpenCart ant build file — not used by this project; ignore it.
- `.opencode/`, `node_modules/`, `plan/`, `.cache/`, `.phpunit.cache/` are tool/local-only.
- `make up` first boot is fully unattended: generates configs, seeds DB, applies migrations,
  builds Manticore index, fixes permissions. There is **no `/install` directory** — don't add one.
  In practice, `make traefik` / `make traefik-ssl` / `make traefik-le` is the usual entry
  point (Traefik mode).
- Use `mariadb` command instead of `mysql`
