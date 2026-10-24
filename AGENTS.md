# AGENTS.md

Canonical developer docs: [`docs/guide.md`](docs/guide.md). Trust it + executable config over prose.

## What this is

PHP 8.5 e-commerce platform, OpenCart 3 MVC fork, shipped as a Docker Compose stack
(nginx → apache/php → mariadb + redis + manticore + scheduler) behind an external Traefik.
App source is in `upload/` (bind-mounted as `/var/www/html`); runtime data lives in
`storage/` (bind-mounted, outside the webroot). All application code and assets live in
these host-side bind mounts — **changes on the host are immediately visible inside
containers, no restart needed**. DB table prefix is `oc_`. Current version is in `upload/VERSION`.

Three applications boot from `upload/system/config/*.php`: `catalog` (`upload/index.php`),
`admin` (`upload/admin/index.php`) and `api` (`upload/api/index.php`). The `api` app reuses
the admin MVC tree (`DIR_APPLICATION` = `upload/admin/`) with its own pre-actions
(`startup/api` instead of `startup/login` + `startup/permission`) — see `docs/guide.md` §17.

## Tooling paths — easy to get wrong

Composer `vendor-dir` is **`./storage/vendor/`** (set in `composer.json`), not `vendor/`.
All PHP tool binaries therefore live at:

- `./storage/vendor/bin/phpstan`
- `./storage/vendor/bin/php-cs-fixer`

First-time setup: `composer install` (deps land in `storage/vendor/`). The container
entrypoint auto-runs `composer install --no-dev` when `composer.lock` changes, so dev
deps only exist after a local `composer install`.

## Verifying changes (mirror CI — `.github/workflows/Lint.yml`)

CI runs four parallel jobs on push to `main` and on PRs (`.github/workflows/Lint.yml`):
`syntax`, `tests`, `phpstan`, and `cs-fixer`. Run these locally before pushing:

```bash
# 1. PHP syntax (skips generated config.php files)
find upload -type f -name "*.php" ! -path 'upload/config.php' ! -path 'upload/admin/config.php' ! -path 'upload/api/config.php' -print0 \
  | xargs -0 -P4 php -l -n

# 2. Test suite (no DB, no network, no composer — plain PHP)
make test            # or: php tools/tests/run.php [case-filter]

# 3. PHPStan (level 1, scans ./upload/ only — see phpstan.neon)
./storage/vendor/bin/phpstan analyze -a ./storage/vendor/autoload.php --no-progress --memory-limit=512M

# 4. PHP-CS-Fixer (dry-run)
./storage/vendor/bin/php-cs-fixer fix --dry-run --diff
```

`tools/tests/` is a dependency-free harness: `run.php` loads every
`tools/tests/cases/*.php` file (each returns `name => callable`) and exits non-zero on
failure. `tools/tests/bootstrap.php` defines the OpenCart constants the library needs
(`DB_PREFIX`, `DIR_SYSTEM`, `DIR_LOGS`), a `modification()` no-op shim, an autoloader for
`Dockercart\*`, and `FakeDb` (records statements, queues SELECT answers) so the outbox and
the dispatcher can be tested without MariaDB. Add a case file when you touch the webhook
code — the suite also guards localization parity (`en-gb`/`ru-ua`/`uk-ua`), the
routes↔OpenAPI parity and the emit anchors, so those regressions fail the build.

PHPStan `-a ./storage/vendor/autoload.php` is required (registers the custom extension in
`tools/phpstan/` via composer.json autoload). `reportUnmatchedIgnoredErrors: false`, so stale
`ignoreErrors` entries won't fail the build — don't add new ignores casually.

## Generated files — do not hand-edit

These are written at container start by `docker/entrypoint.sh` from `.env` and overwritten on
every boot:

- `upload/config.php`, `upload/admin/config.php`, `upload/api/config.php` — app configs
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
  `make start` first. **`make migrate` does NOT track applied migrations** — it re-runs every
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

## Product pricing & multi-currency

- Products are **multi-currency aware**: `oc_product.currency_id` holds the currency a
  product's `price` — and all of its variant, option and customer-group prices — is
  denominated in. `0`/`NULL` means the store default currency (`config_currency`).
- **Always normalise to the base currency** before formatting, summing or comparing:
  `Cart\Currency::convertProductPrice($amount, $product_currency_id)`
  (`upload/system/library/cart/currency.php`). `Currency::format()` treats its input as
  base currency. The storefront, the Manticore index (`dockercart_search`) and order line
  math (`ModelSaleOrder`) all follow this rule — do the same in new pricing code.
- The admin REST API accepts `currency_id` on `POST`/`PUT`/`PATCH /v1/products` and returns
  it on every product resource (`Product` schema) and on `GET /v1/products/{id}/variants`
  (`VariantList`). `GET /v1/meta/currencies` maps the id to `code`/`value`. API prices are
  returned **as stored, in the product's own currency** (4-decimal strings) — the API never
  converts them.

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

## Webhooks (outbound events)

- Subscriptions live in `oc_dockercart_webhook_subscription`, queue rows in
  `oc_dockercart_webhook_delivery` (the **outbox**). Queue rows are written by
  `Dockercart\Webhook\Dispatcher::emit()` **inside the business transaction**, next to
  the data change — never send HTTP from a request.
- The dispatcher must stay transaction-free and exception-free (it logs to
  `storage/logs/webhook.log`): a webhook problem may never roll back an order or a
  stock movement. It never opens its own `START TRANSACTION`.
- Delivery is the scheduler task `webhook_deliver` (`bin/dockercart_webhook_deliver.php`,
  every minute, hidden from the scheduler UI). Retries: 1 m / 5 m / 30 m / 2 h / 12 h;
  `410` disables the subscription, 4 consecutive failures auto-disable it.
- `stock.changed` is emitted from `DockercartWarehouse::journalMovement()` — the only
  writer of `oc_warehouse_stock_movement`. Do **not** insert movement rows directly
  (that skips the event); call `journalMovement()` instead.
- Subscription secrets are encrypted with `config_encryption` and returned exactly once
  (create/rotate). Webhook URLs are SSRF-checked at save **and** re-resolved at send
  (`Dockercart\Webhook\Ssrf`); `WEBHOOK_ALLOW_PRIVATE=1` is a development-only env
  override for the worker.
- Adding an event: flip it to `implemented` in `webhook/events.php`, call `emit()` before
  the `COMMIT` with the version id captured from `getLastId()` at that moment, and
  document it in `docs/guide.md` §18. Then extend `tools/tests/cases/webhook_emit_anchors.php`
  so the new anchor is guarded too.
- Retry classification (`Delivery::isPermanent()`), the backoff table and the global limits
  (`Delivery::limits()`) live in the library, not in the worker — that is what makes them
  testable. Keep the worker as a thin send/journal loop.

## Admin permissions & roles

- `oc_user_group.permission` holds two route lists: `access` (page opens) and `modify`
  (changes save). `startup/permission.php` gates routes on `access`.
- **Administrator (`user_group_id = 1`) is an implicit superuser** — `Cart\User::hasPermission()`
  always returns `true`. Do **not** write migrations that append new routes to the admin
  group; they are unnecessary.
- Standard roles are defined once in `upload/system/library/dockercart/roles.php`
  (`DockercartRoles`): sales manager, support operator, content manager, marketer, courier,
  storekeeper, accountant. **Added an admin controller? Add its route to the matching
  template(s)** there — that is the replacement for the old "grant route to Administrator"
  migrations. Groups are created on demand (Users → User Groups → "Create standard roles",
  idempotent via `oc_user_group.template_code`).
- Action-level permissions are capability pseudo routes stored in the `modify` list and
  checked with `Controller::userCanModify('sale/order', 'sale/order/status')` (parent route
  OR capability). Current capabilities: `sale/order/status`, `sale/order/shipment`. A third
  JSON key is **not** an option — the JSON repair migrations rebuild the document as
  `{access, modify}` and would drop it.
- Read-only/limited roles must not see editing UI: pass `can_edit` / `can_status` /
  `can_shipment` flags to the template instead of letting actions fail server-side.
- **Admin REST API** (`/api/v1/…`) has no permission model of its own: each key
  borrows the group's `access` (read) / `modify` (write) permissions. Endpoints are
  only reachable if they are listed in `Dockercart\Api\Routes::TABLE` together with
  the required route — adding a controller there *is* the permission grant.
  A key either belongs to an admin user (`oc_api_key.user_id > 0` — then it acts
  with the **current** group of that owner, and a disabled/deleted owner invalidates
  it) or is shared (`user_id = 0` — it uses the stored `user_group_id`).
  Keys are managed under **System → API → Keys** (sidebar: *System → API →
  Keys / Request log / Documentation* — the documentation is a **standalone
  page**: its own document, no panel chrome, but the panel session is still
  required) and per user in the **user form**
  (`user/user/api_key*` actions, gated by the `setting/api_key` permission).
  Errors are RFC 9457 problem documents (`application/problem+json`); single-resource
  reads emit `ETag` (validated via `If-Match` on writes, `Controller::assertPreconditions()`)
  and POST supports `Idempotency-Key` (`oc_api_idempotency`). `GET /v1/openapi.json`
  is the one public endpoint (documented data, no credentials); everything else needs a
  key. No settings endpoint exists by design (a partial
  `editSetting('config', …)` wipes ~100 options) and stock is only written through
  `DockercartWarehouse::setTotalQuantity()`; order status changes never accept `override`
  (a refused transition is `409 invalid_transition`).

## Code conventions (differ from typical PHP defaults)

- **Indentation: tabs** (enforced by `.php-cs-fixer.php` `setIndent("\t")`). Spaces will fail CI.
- `declare(strict_types=1);` at the top of new PHP files.
- OpenCart 3 MVC: load models with `$this->load->model('foo/bar')` — never instantiate models
  directly. DB access only via `$this->db->query()` — never raw `mysqli_*` or PDO. Load language
  with `$this->language->load()` before `$this->language->get()`.
- MVC paths:
  - Catalog: `upload/catalog/{controller,model,view/theme/dockercart/template,language}/{section}/{name}.{php,twig}`
  - Admin:   `upload/admin/{controller,model,view/template,language}/{section}/{name}.{php,twig}`
  - Admin REST API: `upload/admin/controller/api/{section}/{name}.php` (routed by the
    `api` app, wired in `upload/system/library/dockercart/api/routes.php`)
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

- **Never restart containers for code changes.** All app code lives in bind-mounted volumes
  (`upload/`, `storage/`). Edits on the host are instantly visible inside the running
  containers. Restarting is only needed after `docker-compose*.yml` changes, image rebuilds,
  or `.env` modifications — not for PHP/Twig/JS/CSS edits.

- **Ask before irreversible changes.** Always confirm with the user before performing actions
  that are hard or impossible to undo: `make clean`, database drops/deletes, force-pushing, deleting tracked files, etc. Propose the action,
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
`upload/VERSION`, `package.json`, `package-lock.json`, `CHANGELOG.md`, then tags `vX.Y.Z` and publishes
a GitHub Release. **Do not manually edit `upload/VERSION` or bump `package.json` version** —
semantic-release will conflict.

Commit messages must follow Conventional Commits — `commitlint` runs on PRs and on pushes to
`main` (`.github/workflows/commitlint.yml`, `commitlint.config.cjs`). Release mapping:
`feat:` → minor, `fix:`/`perf:`/`refactor:` → patch, `feat!:` / breaking → major.
Preview with `npm run release:dry-run`.

## Make commands worth remembering

- `make start` — interactive startup: picks Standalone/Traefik × none/self-signed/Let's Encrypt (remembers last mode in `.env`). Pass `start.sh` flags non-interactively: `make start ARGS="--traefik --le"`
- `make ftp` — attach optional FTP server (chrooted to `./upload/image`)
- `make shell` — bash into the `apache` container (where the app runs)
- `make scheduler-reload` — SIGHUP the scheduler for code reload without container restart
- `make logs-follow`, `make restart`, `make stop`, `make clean` (destructive: removes volumes)
- Container names are prefixed `dockercart_` (e.g. `dockercart_apache`, `dockercart_mariadb`).

## Container logs — never tail stdout/stderr symlinks

- `nginx` official image symlinks `/var/log/nginx/access.log` → `/dev/stdout` and
  `error.log` → `/dev/stderr`. `apache` does the same: `ErrorLog /dev/stderr` +
  `CustomLog /dev/stdout` (`docker/apache.conf`), plus `/var/log/apache2/*.log` symlinks.
- `tail`/`cat` on such a path **blocks forever**: it waits for EOF on a pipe that never
  closes. Same reason `tail -f` without need hangs automation.
- Read container output via Docker instead: `make logs`, `make logs-follow`,
  `docker compose logs --tail=100 <service>`, `docker logs --tail=50 <container>`.
- Safe real files you MAY `tail` via exec: `/var/log/nginx/dockercart_*.log`
  (see `docker/nginx/conf.d/*.conf`) and app logs in `storage/logs/`.
- Before `tail`/`cat` of any unfamiliar path inside a container, check
  `readlink <path>` / `ls -la` first — if it resolves to `/dev/stdout` or `/dev/stderr`,
  stop and use `docker logs`.
- Never pipe `docker exec` to host `head` (`docker exec ... | head` hangs on SIGPIPE
  after `head` exits) — filter inside instead:
  `docker exec <container> sh -c '... | head -n 30'`. For non-interactive commands prefer
  `docker compose exec -T <service>` (as in `Makefile`) — plain `docker exec -T <container>`
  fails on podman hosts.

## Notes

- **New API endpoint checklist**: add it to `upload/system/library/dockercart/api/routes.php`
  (method, path, route, action, permission, `public` flag only for the OpenAPI document),
  implement the action under `upload/admin/controller/api/`, build single resources with
  `$this->respondResource()` and guard writes with `$this->assertPreconditions()` (so
  ETag/If-Match stay consistent), document it in `upload/api/openapi.json`, and reuse the
  admin model for the work (do not call HTML form controllers as API handlers).
  `upload/api/config.php` is generated by the entrypoint, so `docker/entrypoint.sh` and
  `docker/apache.conf` changes need `make restart`; nginx config is bind-mounted (reload only).
- **New webhook event checklist**: catalogue entry in
  `upload/system/library/dockercart/webhook/events.php`, `emit()` inside the existing
  transaction right after the data write (version = `getLastId()` captured there), and a
  mention in `docs/guide.md` §18. The API surface (`/v1/webhooks`) needs no change — the
  catalogue feeds `GET /v1/webhooks/events`.
- `build.xml` is a leftover OpenCart ant build file — not used by this project; ignore it.
- `.opencode/`, `node_modules/`, `plan/`, `.cache/`, `.phpunit.cache/` are tool/local-only. Multi-step
  agent plans are written to `.opencode/plans/` (or the legacy `plan/` dir) — these are local
  scratch files, not part of the repo, and must never be committed.
- When a task needs 3+ steps or careful tracking, write the plan to
  `.opencode/plans/YYYYMMDD-HHMMSS-<short-slug>.md` (the date-time prefix keeps plans
  sorted chronologically). Treat it as a working scratchpad, then leave it on disk
  (gitignored) — do not commit it.
- `make start` first boot is fully unattended: generates configs, seeds DB, applies migrations,
  builds Manticore index, fixes permissions. There is **no `/install` directory** — don't add one.
  In practice, `make start ARGS="--traefik --le"` (or the interactive menu option 6) is the usual
  entry point (Traefik mode).
- Use `mariadb` command instead of `mysql`
