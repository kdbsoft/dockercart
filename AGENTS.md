# DockerCart — AGENTS.md
Critical facts an agent would likely miss. Skip anything obvious.
Keep your answers short and to the point. Don't create unnecessary documentation unless asked.
## General Guidelines
- **Surgical Updates:** Always prefer surgical, partial file edits using appropriate tools rather than overwriting files entirely. This preserves existing logic, reduces risk, and maintains context efficiency.
- **Skip obvious:** Keep your answers short and to the point. Don't create unnecessary documentation unless asked.
---
## Config Generation
`upload/config.php` and `upload/admin/config.php` are **generated at container start**
by `docker/entrypoint.sh` from environment variables — never edit or commit these files.
All runtime settings flow through `.env` (copy from `.env.example`). Never commit `.env`.
---
## OpenCart MVC Conventions
Strict OpenCart 3 patterns — never deviate.
| Type | Catalog path | Admin path |
|---|---|---|
| Controller | `upload/catalog/controller/{section}/{name}.php` | `upload/admin/controller/extension/module/{name}.php` |
| Model | `upload/catalog/model/{section}/{name}.php` | `upload/admin/model/extension/module/{name}.php` |
| View (Twig) | `upload/catalog/view/theme/dockercart/template/{section}/{name}.twig` | `upload/admin/view/template/extension/module/{name}.twig` |
| Language | `upload/catalog/language/en-gb/{section}/{name}.php` | `upload/admin/language/en-gb/extension/module/{name}.php` |
### JavaScript
- **Catalog (frontend):** ES6+ vanilla JS, Tailwind CSS 3 + Lucide icons — no jQuery
- **Admin panel:** jQuery (OpenCart built-in).
### Code style (PHP)
- **Indentation: tabs** (not spaces) — enforced by `.php-cs-fixer.php`
- `declare(strict_types=1);` at file top for new files
- Load models via `$this->load->model()` — never instantiate directly
- Load language via `$this->language->load()` before `$this->language->get()`
### Multi-language rule
- **Always update ALL language files** when adding/changing language strings. Check `upload/admin/language/*/` and `upload/catalog/language/*/` for all variants (en-gb, ru-ua, uk-ua, etc.). Never update only one language.
### Database rules
- Table prefix always `oc_` — DockerCart tables use `oc_dockercart_`
- DB driver: `mysqli` — access via `$this->db->query()`, never raw `mysqli_*` or PDO
---
## Storage Paths
Use `/var/www/storage/` (host: `./storage/`), NOT `/var/www/html/system/storage/`.
| What | Container path | Host path |
|---|---|---|
| Webroot | `/var/www/html` | `./upload` |
| Logs / Cache / Sessions | `/var/www/storage/*` | `./storage/*` |
| Images | `/var/www/html/image` | `./upload/image` |
---
## Database Migrations
- Location: `docker/mysql/migrations/`
- Naming: `YYYYMMDD_short_description.sql`
- Always idempotent: `CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`
- Charset: `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`
- Run via: `make migrate` (applies to running MariaDB container)
- Base schema: `docker/mysql/init.sql` — regenerate with `make dump-init`
---
## Scheduler Registration
The scheduler daemon (`upload/bin/dockercart_scheduler.php`) is a **generic** cron dispatcher.
It reads **only** `oc_dockercart_scheduler_task` — there is no hardcoded handler list.
Every module that needs cron must call `registerTask()` / `unregisterTask()` in its install/uninstall handlers via `upload/system/library/dockercart/scheduler.php`.

**Registration API** (load via `$this->load->library('dockercart/scheduler')` if Registry is available, or `new DockercartScheduler($registry)`):
| Method | Use case |
|---|---|
| `registerTask($type, $name, $workerCommand, $schedule, $enabled)` | Singleton tasks (e.g. novapost_sync, currency_refresh) |
| `unregisterTask($type)` | Remove all rows for a type |
| `registerProfileTask($type, $sourceId, $name, $workerCommand, $schedule, $enabled)` | Per-profile tasks (e.g. import_yml profile #5) |
| `unregisterProfileTask($type, $sourceId)` | Remove a specific profile's row |

**workerCommand** is the CLI invocation string. Use `%d` as placeholder for `source_id` — the daemon substitutes it at runtime.
Example: `registerTask('novapost_sync', 'NovaPost Sync', 'php /var/www/html/bin/novapost-sync.php', '0 2 * * *')`.

**Never** hardcode handler classes, task_type literals, or worker commands in `bin/dockercart_scheduler.php`.
---
## Backup to S3 (optional)
Scheduled `tar.gz` backup of **DB + `./upload/image` + `./storage/download` + `./storage/modification`** to S3 / S3-compatible storage. Off by default.

- **Worker**: `upload/bin/dockercart_backup_s3.php` — registered as singleton scheduler task `backup_s3` via migration `20260706_register_backup_s3_task.sql`. Disabled by default; user toggles schedule in admin **System → Scheduler**.
- **S3 client**: `rclone` (installed in Dockerfile). Config `/var/www/storage/.rclone.conf` is generated at container start by `ensure_rclone_config()` in `docker/entrypoint.sh` from `BACKUP_S3_*` env vars. `RCLONE_CONFIG` env is exported so the worker (spawned by the scheduler daemon) inherits it.
- **Credentials stay in `.env`** — never written to the database. Worker reads them via `getenv()`.
- **Staging dir**: `/var/www/storage/backup/` (host `./storage/backup/`). Local tar.gz is deleted immediately after successful upload (kept only on upload failure, for manual recovery).
- **Retention**: worker deletes S3 objects older than `BACKUP_S3_RETENTION_DAYS` (default 7) under `BACKUP_S3_PATH`. Only `dockercart_*.tar.gz` files are deleted — other objects in the prefix are left alone.
- **Status**: worker writes JSON to `oc_dockercart_scheduler_task.last_result` (status, size, s3_key, retention_deleted). Worker log: `/var/www/storage/logs/scheduler/worker_backup_s3_<taskId>.log`.
- **To enable**: set `BACKUP_S3_ENABLED=true` + credentials in `.env` → `make scheduler-restart` (and `make up` for apache) → enable the "Backup to S3" task in admin UI.

**Required env vars** (see `.env.example`): `BACKUP_S3_ENABLED`, `BACKUP_S3_PROVIDER`, `BACKUP_S3_ENDPOINT`, `BACKUP_S3_REGION`, `BACKUP_S3_BUCKET`, `BACKUP_S3_ACCESS_KEY_ID`, `BACKUP_S3_SECRET_ACCESS_KEY`, `BACKUP_S3_PATH`, `BACKUP_S3_RETENTION_DAYS`, `BACKUP_S3_INSECURE`.
---
## Frontend (DockerCart Theme)
- **Tailwind CSS 3** + **Lucide icons** (not Font Awesome) + **ES6+** vanilla JS
- Build: `npm run build:css` — compiles to `upload/catalog/view/theme/dockercart/stylesheet/tailwind.css`
- Watch: `npm run watch:css`
- Do NOT use jQuery/Bootstrap/Font Awesome in new DockerCart theme code
- Do NOT modify `upload/catalog/view/theme/default/` (OpenCart built-in)
---
## Static Analysis & Lint
```bash
# PHP syntax check (CI: runs on push to main + PRs)
find upload -type f -name "*.php" ! -path 'storage/vendor/*' -print0 | xargs -0 -P4 php -l -n
# PHPStan (level 1, config: phpstan.neon)
./storage/vendor/bin/phpstan analyze -a ./storage/vendor/autoload.php --no-progress
# PHP-CS-Fixer (tabs, config: .php-cs-fixer.php)
./storage/vendor/bin/php-cs-fixer fix --dry-run --diff
```
---
## Docker Compose Modes
| Make target | Use case |
|---|---|
| `make dev` / `make up` | Standalone HTTP (port 80) — default |
| `make dev-ssl` / `make ssl` | Standalone + self-signed HTTPS |
| `make prod` / `make le` | Standalone + Let's Encrypt HTTPS |
| `make traefik` | Traefik + external reverse proxy |
| `make traefik-ssl` | Traefik + self-signed HTTPS |
| `make traefik-le` | Traefik + Let's Encrypt HTTPS |
| `make ftp` | Enable FTP (images only) |
Traefik is optional — standalone is the default.
Container names: `dockercart_apache`, `dockercart_nginx`, `dockercart_mariadb`, `dockercart_redis`, `dockercart_manticore`, `dockercart_scheduler`
---
## Commit Conventions
Conventional Commits enforced by commitlint + semantic-release.
```
feat: add product label field
fix: resolve cache invalidation on price update
feat!: breaking API change
```
Preview release: `npm run release:dry-run`
---
## What NOT To Do
- Do NOT use OpenCart web installer or create `/install` directory
- Do NOT edit `upload/config.php` or `upload/admin/config.php` — generated at startup
- Do NOT store state in `/var/www/html/system/storage/` — use `/var/www/storage/`
- Do NOT write inline SQL schema in PHP — always use a migration
- `robots.txt` is generated by `entrypoint.sh` on every boot — rules live in the `ensure_robots_txt()` heredoc. Never commit `robots.txt` (already in `.gitignore`).
- Do NOT commit `.env` files
---
## Key Commands
```bash
make migrate # Apply DB migrations
npm run build:css # Rebuild Tailwind CSS
make shell # Bash into app container
make backup / make restore
npm run release:dry-run # Preview next release
```
