# 📖 DockerCart Developer Guide

---

## Table of Contents

1. [Getting Started](#1-getting-started)
2. [Architecture](#2-architecture)
3. [Configuration](#3-configuration)
4. [Deployment](#4-deployment)
5. [Development](#5-development)
6. [Scheduler](#6-scheduler)
7. [Backup](#7-backup)
8. [Release Workflow](#8-release-workflow)
9. [Core Updates](#9-core-updates)
10. [Security](#10-security)

---

## 1. Getting Started

### Prerequisites

- Docker & Docker Compose v2 (or Podman Compose)
- Git
- Make

### Installation

```bash
git clone https://github.com/kdbsoft/dockercart.git
cd dockercart
cp .env.example .env
```

Edit `.env` to match your environment — at minimum set `DOCKERCART_URL`, `DB_PASSWORD`, and `ADMIN_PASSWORD`.

### First Boot

```bash
make up
```

The entrypoint script (`docker/entrypoint.sh`) runs the following on first start:

1. **Config generation** — writes `upload/config.php` and `upload/admin/config.php` from environment variables
2. **Database seeding** — creates the schema (`docker/mysql/init.sql`) and applies pending migrations
3. **OCMOD refresh** — rebuilds modification cache
4. **Search indexing** — builds the Manticore full-text index
5. **Permission setup** — ensures correct ownership on storage directories

No web installer is involved — there is no `/install` directory.

### Access

- **Storefront:** `http://dockercart.local`
- **Admin panel:** `http://dockercart.local/admin`
- **Default credentials:** `admin` / `admin123` (set in `.env`)

### Add-ons

Ready-made extensions for DockerCart are available in the official store:
**https://store.dockercart.net**

Install via admin panel: **Extensions → Installer → upload `.ocmod.zip`**.

---

## 2. Architecture

### Services Overview

| Container | Image | Role |
|---|---|---|
| `dockercart_nginx` | nginx:alpine | Reverse proxy, TLS termination, static caching, gzip |
| `dockercart_apache` | PHP 8.5 + Apache | Application server, runs DockerCart |
| `dockercart_mariadb` | mariadb:11 | Primary database |
| `dockercart_redis` | redis:7-alpine | Object cache and session store |
| `dockercart_manticore` | manticoresearch:15 | Full-text search engine |
| `dockercart_scheduler` | PHP 8.5 + Apache | Cron dispatcher daemon |

Additional optional services: `dockercart_ftp` (vsftpd), `dockercart_certbot` (Let's Encrypt).

### Network

All containers communicate over a shared bridge network (`dockercart-network`). Nginx is the only container with exposed ports — Apache has no public interface.

### Directory Layout

```
dockercart/
├── docker/                     Docker service configs
│   ├── apache.conf             Apache VirtualHost
│   ├── php.ini                 PHP runtime config
│   ├── entrypoint.sh           Container startup script
│   ├── mysql/
│   │   ├── init.sql            Schema + seed data
│   │   └── migrations/         Incremental SQL migrations
│   ├── manticore/              Manticore Search configs
│   └── nginx/                  Nginx configs
├── storage/                    Runtime files (outside webroot)
│   ├── logs/                   Application error logs
│   ├── cache/                  Cache files
│   └── ...                     Session, download, modification, upload
├── upload/                     Application source (mounted as /var/www/html)
│   ├── admin/                  Admin panel (MVC)
│   ├── catalog/                Storefront (MVC)
│   ├── bin/                    CLI scripts
│   ├── system/                 Framework libraries
│   └── ...
├── .env.example                Environment template
├── docker-compose.yml          Default stack (standalone)
├── docker-compose.*.yml        SSL, LE, Traefik overrides
├── Dockerfile                  PHP 8.5 + Apache image
└── Makefile                    All commands
```

### Storage Paths

All runtime data lives outside the webroot:

| What | Container path | Host path |
|---|---|---|
| Webroot | `/var/www/html` | `./upload` |
| Logs / Cache / Sessions | `/var/www/storage/*` | `./storage/*` |
| Images | `/var/www/html/image` | `./upload/image` |

---

## 3. Configuration

### Environment Variables

All settings are defined in `.env` (copy from `.env.example`).  
`upload/config.php` and `upload/admin/config.php` are **generated at container start** by `docker/entrypoint.sh` — never edit them directly.

#### Database

| Variable | Default | Description |
|---|---|---|
| `DB_HOSTNAME` | `mariadb` | Database host |
| `DB_USERNAME` | `dockercart` | Database user |
| `DB_PASSWORD` | — | Database password |
| `DB_DATABASE` | `dockercart` | Database name |
| `DB_PORT` | `3306` | Database port |
| `DB_PREFIX` | `oc_` | Table prefix |
| `MARIADB_ROOT_PASSWORD` | — | Root password (initial setup) |
| `MARIADB_CONFIG_SIZE` | `s` | InnoDB profile: `s` (4GB), `m` (8GB), `l` (12GB) |

#### Application

| Variable | Default | Description |
|---|---|---|
| `DOCKERCART_URL` | `http://dockercart.local` | Store base URL |
| `DOCKERCART_HTTPS_URL` | — | HTTPS URL (when SSL enabled) |
| `DOCKERCART_SSL_ENABLED` | `false` | Enable SSL mode |
| `ADMIN_USERNAME` | `admin` | Default admin username |
| `ADMIN_PASSWORD` | `admin123` | Default admin password |
| `ADMIN_EMAIL` | `admin@example.com` | Default admin email |
| `PHP_MEMORY_LIMIT` | `256M` | PHP memory limit |
| `PHP_UPLOAD_MAX_FILESIZE` | `100M` | Max upload size |
| `PHP_POST_MAX_SIZE` | `100M` | Max POST size |
| `PHP_MAX_EXECUTION_TIME` | `300` | Max execution time (s) |
| `IMAGE_MAX_DIMENSION` | `2560` | Max image dimension (px); `0` disables |
| `TZ` | `UTC` | Timezone |

#### Cache & Redis

| Variable | Default | Description |
|---|---|---|
| `CACHE_ENGINE` | `redis` | `redis` or `file` |
| `SESSION_ENGINE` | `redis` | `redis` or `file` |
| `REDIS_HOSTNAME` | `redis` | Redis host |
| `REDIS_PORT` | `6379` | Redis port |
| `REDIS_PASSWORD` | — | Redis password |
| `REDIS_MAXMEMORY` | `256mb` | Redis maxmemory limit |

#### SSL / Let's Encrypt

| Variable | Default | Description |
|---|---|---|
| `SSL_DOMAIN` | — | Domain for LE certificate |
| `SSL_EMAIL` | — | Email for LE registration |
| `LETSENCRYPT_ENABLED` | `false` | Enable LE mode |
| `LETSENCRYPT_DATA_DIR` | `./docker/letsencrypt` | ACME state persistence |

#### Scheduler

| Variable | Default | Description |
|---|---|---|
| `SCHEDULER_ENABLED` | `true` | Enable scheduler daemon |
| `SCHEDULER_POLL_INTERVAL` | `60` | Poll interval (seconds) |
| `SCHEDULER_MEM_LIMIT` | `512M` | Worker memory limit |
| `SCHEDULER_CPUS` | `1.0` | Worker CPU limit |
| `SCHEDULER_WORKER_TIMEOUT` | `3600` | Max worker runtime (s) |

#### S3 Backup

| Variable | Default | Description |
|---|---|---|
| `BACKUP_S3_ENABLED` | `false` | Enable S3 backup |
| `BACKUP_S3_SCHEDULE` | `0 2 * * *` | Cron schedule |
| `BACKUP_S3_PROVIDER` | — | rclone provider (AWS, Minio, etc.) |
| `BACKUP_S3_ENDPOINT` | — | S3-compatible endpoint |
| `BACKUP_S3_BUCKET` | — | Bucket name |
| `BACKUP_S3_ACCESS_KEY_ID` | — | Access key |
| `BACKUP_S3_SECRET_ACCESS_KEY` | — | Secret key |
| `BACKUP_S3_PATH` | `dockercart/backups` | Object key prefix |
| `BACKUP_S3_RETENTION_DAYS` | `7` | Retention period |
| `BACKUP_S3_REGION` | — | S3 region |
| `BACKUP_S3_INSECURE` | `false` | Skip TLS verification |

#### FTP (optional)

| Variable | Default | Description |
|---|---|---|
| `FTP_PORT` | `21` | FTP control port |
| `FTP_USER` | `images` | FTP username |
| `FTP_PASS` | — | FTP password |
| `FTP_PASV_ADDRESS` | — | Passive mode address (public IP/domain) |

### robots.txt

`robots.txt` is auto-generated on every container start from the `ensure_robots_txt()` heredoc in `docker/entrypoint.sh`. Sitemap URL is populated from `DOCKERCART_URL` or `DOCKERCART_HTTPS_URL`. To customize, edit the heredoc and restart the container.

---

## 4. Deployment

### Makefile Targets

| Command | Action |
|---|---|
| `make up` / `make dev` | Start standalone HTTP |
| `make ssl` / `make dev-ssl` | Start standalone + self-signed HTTPS |
| `make le` / `make prod` | Start standalone + Let's Encrypt HTTPS |
| `make traefik` | Start with Traefik reverse proxy |
| `make traefik-ssl` | Traefik + self-signed HTTPS |
| `make traefik-le` | Traefik + Let's Encrypt HTTPS |
| `make ftp` | Attach FTP to running stack |
| `make down` | Stop containers |
| `make restart` | Restart all containers |
| `make logs` | View logs |
| `make logs-follow` | Tail logs |
| `make shell` | Bash into app container |
| `make mariadb` | MariaDB CLI |
| `make migrate` | Apply SQL migrations |
| `make backup` | Dump database |
| `make restore` | Restore database dump |
| `make clean` | Remove all volumes (destructive) |

### Standalone Mode

The default deployment. Nginx binds to `DOCKERCART_HTTP_PORT` (80) and optionally `DOCKERCART_HTTPS_PORT` (443).

```bash
make up     # HTTP on port 80
make ssl    # HTTPS with self-signed certificate
make le     # HTTPS with Let's Encrypt (requires public DNS + port 80/443 access)
```

### Traefik Mode

Use when you already run Traefik as an external reverse proxy. Requires an existing `traefik` Docker network.

```bash
make traefik       # HTTP
make traefik-ssl   # HTTPS (self-signed)
make traefik-le    # HTTPS (Let's Encrypt)
```

### FTP Add-on

The FTP server (vsftpd) provides chrooted access to `./upload/image` for external image management.

```bash
make ftp
```

---

## 5. Development

### MVC Conventions

The codebase follows OpenCart 3 MVC patterns:

| Layer | Catalog (frontend) | Admin |
|---|---|---|
| Controller | `catalog/controller/{section}/{name}.php` | `admin/controller/extension/module/{name}.php` |
| Model | `catalog/model/{section}/{name}.php` | `admin/model/extension/module/{name}.php` |
| View | `catalog/view/theme/dockercart/template/{section}/{name}.twig` | `admin/view/template/extension/module/{name}.twig` |
| Language | `catalog/language/en-gb/{section}/{name}.php` | `admin/language/en-gb/extension/module/{name}.php` |

Language files must be kept in sync across all locales (`en-gb`, `ru-ua`, `uk-ua`, etc.).

### Code Style (PHP)

- **Indentation:** tabs (not spaces) — enforced by `.php-cs-fixer.php`
- **Strict types:** `declare(strict_types=1);` at the top of new files
- **Model loading:** use `$this->load->model()` — never instantiate directly
- **Language loading:** use `$this->language->load()` before `$this->language->get()`
- **Database:** access via `$this->db->query()` — never raw `mysqli_*` or PDO

### Static Analysis

```bash
# PHP syntax check (CI: runs on push to main + PRs)
find upload -type f -name "*.php" ! -path 'storage/vendor/*' -print0 | xargs -0 -P4 php -l -n

# PHPStan (level 1)
./storage/vendor/bin/phpstan analyze -a ./storage/vendor/autoload.php --no-progress

# PHP-CS-Fixer (tabs)
./storage/vendor/bin/php-cs-fixer fix --dry-run --diff
```

### Frontend

- **JavaScript:** ES6+ vanilla JS — no jQuery in storefront code
- **CSS:** Tailwind CSS 3 — build with `npm run build:css`, watch with `npm run watch:css`
- **Icons:** Lucide icons — not Font Awesome
- Compiles to `upload/catalog/view/theme/dockercart/stylesheet/tailwind.css`

### Database Migrations

- **Location:** `docker/mysql/migrations/`
- **Naming:** `YYYYMMDD_short_description.sql`
- **Idempotency:** use `CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`
- **Charset:** `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`
- **Apply:** `make migrate` (runs against running MariaDB container)
- **Regenerate base schema:** `make dump-init`

### Testing

Tests use PHPUnit. The bootstrap loads the Composer autoloader — minimal setup.

**Install all dev tools (first time after clone):**

```bash
composer install
```

**Run all tests:**

```bash
./storage/vendor/bin/phpunit
```

Tests live in `tests/`. Smoke tests are in `tests/Unit/`.

**CI:** PHPUnit runs on every push to `main` and every PR, in parallel with PHPStan, CS-Fixer, and syntax checks.

### Custom Files & Upgrade Safety

To keep `make update` working smoothly, your git repo must stay clean. For your own files (controllers, models, templates, language files, etc.) use prefixes that are already ignored by `.gitignore`:

| Prefix | Example |
|---|---|
| `custom_` | `custom_checkout.twig`, `custom_controller.php` |
| `dockercart_custom_` | `dockercart_custom_shipping.php` |
| `dc_custom_` | `dc_custom_payment.php` |

These files won't appear in `git status` — the repo stays clean and `make update` won't block.

**Alternative:** for files you can't rename, use `.git/info/exclude` — a local-only gitignore that never enters the repository:

```bash
echo "my-custom-extension.php" >> .git/info/exclude
```

**Important:** `make update` only checks `--untracked-files=no`, so untracked (new) files are fine. Modified tracked files (core edits) will cause an error — don't edit core files directly, use OCMOD or event hooks instead.

---

## 6. Scheduler

The scheduler daemon (`upload/bin/dockercart_scheduler.php`) is a generic cron dispatcher. It reads scheduled tasks from the `oc_dockercart_scheduler_task` table and spawns workers on schedule — there are no hardcoded handler classes.

### How It Works

1. The daemon polls `oc_dockercart_scheduler_task` every `SCHEDULER_POLL_INTERVAL` seconds
2. When a task is due, it forks a worker process with the configured `workerCommand`
3. Workers run with `SCHEDULER_WORKER_TIMEOUT` max execution time
4. Task execution history is logged

### Registration API

Extensions register tasks at install time via the `DockercartScheduler` library:

```php
$this->load->library('dockercart/scheduler');

// Singleton task
$this->dockercart_scheduler->registerTask(
    'currency_refresh',
    'Currency Refresh',
    'php /var/www/html/bin/currency_refresh.php',
    '0 */6 * * *',
    true
);

// Per-profile task (e.g., per-profile import)
$this->dockercart_scheduler->registerProfileTask(
    'import_profile',
    5,
    'Import Profile #5',
    'php /var/www/html/bin/import.php --profile=%d',
    '0 3 * * *',
    true
);
```

| Method | Use case |
|---|---|
| `registerTask($type, $name, $workerCommand, $schedule, $enabled)` | Singleton tasks |
| `unregisterTask($type)` | Remove all rows for a type |
| `registerProfileTask($type, $sourceId, $name, $workerCommand, $schedule, $enabled)` | Per-profile tasks |
| `unregisterProfileTask($type, $sourceId)` | Remove a specific profile row |

The `%d` placeholder in `workerCommand` is substituted with `source_id` at runtime.

### Management

```bash
make scheduler-logs       # Follow scheduler logs
make scheduler-restart    # Restart scheduler container
make scheduler-reload     # SIGHUP — reload code without restart
make scheduler-status     # Check if running
```

---

## 7. Backup

### Local Backups

```bash
make backup   # Dumps database to a timestamped .sql file
make restore  # Restores from a dump file
```

### S3 Backup (optional)

Off by default. Provides one-shot `tar.gz` backup of the database, uploaded images, downloads, and modifications to S3 or S3-compatible storage.

**Architecture:**

- **Worker:** `upload/bin/dockercart_backup_s3.php` — PHP CLI, runs in the `backup-worker` Compose service (profile: `backup`, never started by `make up`)
- **Trigger:** host cron → `COMPOSE_PROFILES=backup docker compose run --rm --no-deps backup-worker`
- **S3 client:** rclone (installed in Dockerfile), config at `/var/www/storage/.rclone.conf` generated at container start
- **Staging:** `/var/www/storage/backup/` — local tar.gz deleted after successful upload
- **Retention:** deletes S3 objects older than `BACKUP_S3_RETENTION_DAYS` (default 7)

**Setup:**

1. Set `BACKUP_S3_ENABLED=true` and all `BACKUP_S3_*` vars in `.env`
2. `make up` (builds image with rclone)
3. `sudo ./install-backup-cron.sh` (writes `/etc/cron.d/dockercart-backup`)

```bash
# Manual one-shot
make backup-s3
```

---

## 8. Release Workflow

Releases are automated from `main` via `semantic-release`.

### Conventional Commits

Commit messages must follow the [Conventional Commits](https://www.conventionalcommits.org/) specification:

```
feat: add product label field
fix: resolve cache invalidation on price update
feat!: breaking API change
```

### Automated Release

On push to `main`, semantic-release:

1. Determines the next version from commit messages
2. Generates `CHANGELOG.md`
3. Updates `VERSION` and `package.json`
4. Creates a Git tag (`vX.Y.Z`)
5. Publishes a GitHub Release

### Preview

```bash
npm run release:dry-run
```

---

## 9. Core Updates

The built-in `make update` command pulls new code and applies migrations — a single command to upgrade your store.

### What It Does

```bash
make update
```

1. **Lock** — prevents concurrent updates via `.update.lock`
2. **Git fetch + fast-forward pull** — pulls changes from `origin` into your current branch
3. **Apache recreation** — recreates the container to refresh bind mounts (`VERSION`, configs)
4. **OCMOD refresh** — rebuilds modification cache for the new code
5. **SQL migrations** — applies any pending migrations from `docker/mysql/migrations/`

### Safety Checks

- **Clean repo required:** modified tracked files block the update. Commit or stash your changes first, or use `ALLOW_DIRTY=1 make update` to skip the check.
- **Detached HEAD:** not supported — you must be on a branch.
- **Diverged branches:** if local and remote have diverged, manual intervention is required.

### Skip Migrations

If you only want to pull code without touching the database:

```bash
SKIP_MIGRATIONS=1 make update
```

### What to Check After Update

1. Review `VERSION` to confirm the new version
2. Check the [changelog](https://dockercart.net/changelog) for breaking changes
3. Verify the storefront and admin panel load correctly

Full script: `update.sh`

---

## 10. Security

### Reporting Vulnerabilities

Report security issues via email to **security@dockercart.net**.  
You will receive an acknowledgement within 48 hours.

See [`SECURITY.md`](../SECURITY.md) for the full policy.

### Hardening Checklist

- Change all default passwords in `.env` before production use
- Restrict admin panel (`/admin`) by IP allowlist or VPN
- Remove MariaDB port mapping in production (or bind to `127.0.0.1`)
- Always use HTTPS in production
- Keep base images updated: `docker compose pull && docker compose up -d`
- Ensure `./storage/` is not publicly accessible
- Image execution is disabled in Apache — do not remove this restriction
