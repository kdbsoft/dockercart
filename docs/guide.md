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
11. [Configurable Products & Variants](#11-configurable-products--variants)
12. [Buy X Get Y (BXGY)](#12-buy-x-get-y-bxgy)
13. [Order Multilingual Display](#13-order-multilingual-display)
14. [Order Flow](#14-order-flow)
15. [Product Returns (full / partial / exchange)](#15-product-returns-full--partial--exchange)
16. [Warehouses & Dropshipping](#16-warehouses--dropshipping)
17. [Admin REST API](#17-admin-rest-api)
18. [Webhooks](#18-webhooks)
19. [Product Files (public downloads)](#19-product-files-public-downloads)

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
make start
```

On the first run `make start` generates `.env` from `.env.example`. The interactive setup asks for only the store domain and the admin email; every other value (timezone, project name, ports, admin username, seed mode) gets a sane default, and all passwords (DB, MariaDB root, Redis, admin, FTP) are generated randomly and written to `.env`. `.env.example` remains the full reference template for manual tuning — edit it (or the generated `.env`) to change anything. For fully automated installs, set `DOCKERCART_NONINTERACTIVE=1` to skip the two prompts and just generate `.env` with random secrets.

**Keep `.env` flat (no nested `${VAR}` references).** docker compose expands `MARIADB_USER=${DB_USERNAME}` recursively, but podman-compose (the `docker` shim on podman hosts) does not: the literal string lands in the container env, the mariadb healthcheck runs with an empty user, mariadb never becomes `healthy`, and `up` blocks indefinitely on `depends_on: condition: service_healthy` — the startup appears hung right after the mariadb/redis containers are listed. If that happens, fix the `MARIADB_USER`/`MARIADB_DATABASE` lines in `.env` to the plain values from `.env.example` (they must mirror `DB_USERNAME`/`DB_DATABASE`) and re-run `make start`. The mariadb healthcheck resolves its credentials from the container environment at runtime, so it does not depend on compose-side interpolation.

### First Boot

```bash
make start
```

The entrypoint script (`docker/entrypoint.sh`) runs the following on first start:

1. **Config generation** — writes `upload/config.php` and `upload/admin/config.php` from environment variables
2. **Database seeding** — creates the schema (`docker/mysql/init.sql`) and applies pending migrations
3. **OCMOD refresh** — rebuilds modification cache
4. **Search indexing** — builds the Manticore full-text index
5. **Permission setup** — keeps the bind-mounted `upload/` + `storage/` writable by everyone who touches them: owner = host user, group = `staff` (GID 50, shared with `www-data` and the FTP container), dirs `g+rwxs` (SGID, so new files inherit the group), files `g+rw`, nothing world-writable. Only drifted files are rewritten (`resolve_owner()` in `docker/entrypoint.sh`), so in steady state a restart touches no inodes. “Host user” means container root under rootless Podman and the owner of `./upload` under rootful Docker; if the host-side webroot ended up owned by real `root`, fix it once with `sudo chown -R "$USER:" upload storage`.

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
│       ├── templates/          nginx.conf.template (rendered at container start)
│       ├── conf.d/             Server blocks + snippets
│       └── ssl/                Self-signed / LE server blocks
├── storage/                    Runtime files (outside webroot)
│   ├── logs/                   Application error logs
│   ├── cache/                  Cache files
│   └── ...                     Session, download, modification, upload
├── upload/                     Application source (mounted as /var/www/html)
│   ├── admin/                  Admin panel (MVC) + REST API controllers/startup
│   ├── api/                    REST API front controller (+ generated config.php)
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

`docker/nginx/templates/nginx.conf.template` is rendered into
`/etc/nginx/nginx.conf` at container start by the official nginx image entrypoint
(`NGINX_ENTRYPOINT_LOCAL_RESOLVERS=1` and `NGINX_ENVSUBST_OUTPUT_DIR=/etc/nginx`
in the compose files). `${NGINX_LOCAL_RESOLVERS}` is read from the container's
`/etc/resolv.conf`, so the `apache` upstream resolver works unchanged under Docker
(`127.0.0.11`) and under Podman rootless (aardvark-dns at the network gateway,
e.g. `10.89.5.1`) — the addresses are runtime-specific, never hardcode them.
Editing the template needs a container recreate (`docker compose up -d nginx`),
not `nginx -s reload`: the rendered file lives in the container filesystem.

All runtime data lives outside the webroot:

| What | Container path | Host path |
|---|---|---|
| Webroot | `/var/www/html` | `./upload` |
| Logs / Cache / Sessions | `/var/www/storage/*` | `./storage/*` |
| Images | `/var/www/html/image` | `./upload/image` |

---

## 3. Configuration

### Environment Variables

All settings are defined in `.env` — generated interactively on the first `make start` (domain, timezone, passwords, admin account); missing keys are asked for on subsequent startups. `.env.example` is the full reference template for manual configuration.  
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
| `SESSION_ENGINE` | `file` | `file` (default, persistent in `storage/session`) or `redis` (cache-only Redis loses sessions on restart) |
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
| `CERTBOT_RENEW_INTERVAL` | `24h` | How often the certbot container checks for renewal |
| `RENEW_NGINX_RELOAD_INTERVAL` | `6h` | How often nginx reloads to pick up renewed certificates |

Renewal flow: the certbot container runs `certbot renew --webroot` every
`CERTBOT_RENEW_INTERVAL`; nginx has no deploy-hook (the certbot image has no
nginx/docker CLI) and instead reloads itself every `RENEW_NGINX_RELOAD_INTERVAL`,
so a renewed certificate goes live within that interval. The HTTP-01 challenge
is served on both ports 80 and 443 (`location ^~ /.well-known/acme-challenge/`
in `docker/nginx/conf.d/dockercart.conf` and `docker/nginx/ssl/dockercart.ssl.conf`),
because LE follows redirects to HTTPS.

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

`robots.txt` is managed from the admin at **System → Indexing** (`setting/indexing`).
The page has a master "Enable indexing" switch (on by default) and an editor for the
`robots.txt` contents; saving writes `/var/www/html/robots.txt` immediately. Switching
indexing off makes the file disallow the whole store (`Disallow: /`).

The file is only *seeded* with a default template by `ensure_robots_txt()` in
`docker/entrypoint.sh` on first boot (sitemap URL from `DOCKERCART_URL` /
`DOCKERCART_HTTPS_URL`); once it exists it is never overwritten on restart, so admin
edits survive. The same default template lives in
`ControllerSettingIndexing::defaultRobotsTxt()` — keep the two in sync.

---

## 4. Deployment

### Makefile Targets

| Command | Action |
|---|---|
| `make start` | Start the stack (interactive mode picker: Standalone/Traefik × none/self-signed/LE) |
| `make start ARGS="..."` | Start non-interactively, passing `start.sh` flags (e.g. `--traefik --le`) |
| `make ftp` | Attach FTP to running stack |
| `make stop` | Stop containers |
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

The default deployment. Nginx binds to `DOCKERCART_HTTP_PORT` (80) and optionally `DOCKERCART_HTTPS_PORT` (443). If `DOCKERCART_URL` is not set, `start.sh` derives it from `DOCKERCART_DOMAIN` + the HTTP port automatically, so a non-default port (e.g. `8080`) just works — internal links, `config_url`, `robots.txt` and `config.php` all include the port. On a clean install the default port is 80 (no prompt). To use a non-default port, either set `DOCKERCART_HTTP_PORT` in `.env` and run `make restart`, or set `DOCKERCART_URL` explicitly (e.g. `http://shop.local:8080`) — an explicitly set URL always wins and `start.sh` never rewrites `.env`.

```bash
make start               # interactive: pick Standalone HTTP / self-signed / Let's Encrypt
make start ARGS="--ssl"  # HTTPS with self-signed certificate
make start ARGS="--le"   # HTTPS with Let's Encrypt (requires public DNS + port 80/443 access)
```

### Traefik Mode

Use when you already run Traefik as an external reverse proxy. Requires an existing `traefik` Docker network.

```bash
make start ARGS="--traefik"        # HTTP
make start ARGS="--traefik --ssl"  # HTTPS (self-signed)
make start ARGS="--traefik --le"   # HTTPS (Let's Encrypt)
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
- **Voice search** (Web Speech API): microphone button on catalog search inputs (header + results page),
  injected script `upload/catalog/view/javascript/dockercart_voice_search.js` via the
  `catalog/view/common/header/after` event. Toggle: admin → Search module → Autocomplete → "Enable Voice Search".
  Recognized phrase fills the input and fires an `input` event (autocomplete picks it up); no auto-submit.
  Works in Chrome/Edge/Safari over HTTPS; hidden in unsupported browsers.

### Database Migrations

- **Location:** `docker/mysql/migrations/`
- **Naming:** `YYYYMMDD_short_description.sql`
- **Idempotency:** use `CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`
- **Charset:** `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`
- **Apply:** `make migrate` (runs against running MariaDB container)
- **Regenerate base schema:** `make dump-init`

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

- **Worker:** `upload/bin/dockercart_backup_s3.php` — PHP CLI, runs in the `backup-worker` Compose service (profile: `backup`, never started by `make start`)
- **Trigger:** host cron → `COMPOSE_PROFILES=backup docker compose run --rm --no-deps backup-worker`
- **S3 client:** rclone (installed in Dockerfile), config at `/var/www/storage/.rclone.conf` generated at container start
- **Staging:** `/var/www/storage/backup/` — local tar.gz deleted after successful upload
- **Retention:** deletes S3 objects older than `BACKUP_S3_RETENTION_DAYS` (default 7)

**Setup:**

1. Set `BACKUP_S3_ENABLED=true` and all `BACKUP_S3_*` vars in `.env`
2. `make start` (builds image with rclone)
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
3. **Apache recreation** — recreates the container to refresh bind mounts (single-file mounts such as configs, and to pick up the new image when the Dockerfile changed)
4. **OCMOD refresh** — rebuilds modification cache for the new code
5. **SQL migrations** — applies any pending migrations from `docker/mysql/migrations/`

### Safety Checks

- **Clean repo required (with one exception):** modified tracked files outside `upload/` block the update. Commit or stash them first, or use `ALLOW_DIRTY=1 make update` to skip the check. The GUI updater (`admin/cli/dockercart_update.php`) copies `upload/` directly into the bind mount without advancing git HEAD, which leaves those paths "dirty": modified, deleted (the GUI pruner removes files upstream still ships) and untracked (files the release adds). `make update` automatically reconciles exactly those GUI-managed paths before pulling — it resets tracked `upload/*` changes in a single `git checkout` (retrying if the IDE holds `.git/index.lock`), restores deleted tracked files, and removes untracked `upload/*` files that upstream provides. Untracked files that upstream does **not** ship are left alone, so **running the GUI updater and then `make update` works without manual cleanup**. Untracked `upload/*` files whose content differs from upstream are copied to `storage/dockercart_update/backup/reconcile-<timestamp>/` before removal; `storage/dockercart_update/manifest.json` (written by the GUI) identifies GUI copies.
- **Detached HEAD:** not supported — you must be on a branch.
- **Diverged branches:** if local and remote have diverged, manual intervention is required.

### Deployment provenance

Both update paths write a small record into `storage/dockercart_update/` (bind-mounted, visible to both the host and the container), so each side knows what the other did:

- `deployed.json` — written by whichever path deployed last: `{ "source": "gui" | "git", "remote", "branch", "sha", "version", "deployed_at" }`. The GUI worker resolves `sha` with `git ls-remote` (provider-agnostic; no local `.git` needed), so the record identifies the exact commit even when the branch has since moved.
- `host-git.json` — written by `make update` on every run: `{ "origin", "branch", "head", "version", "checked_at" }`.

`make update` reads `deployed.json` before reconciling and logs a warning when the GUI deployed from a different repository/branch than the git checkout; it also uses the recorded `sha` as an exact reference when comparing GUI-copied files. The admin **System → Update** page shows the host git checkout and warns when the configured repository/branch does not match it.

### Skip Migrations

If you only want to pull code without touching the database:

```bash
SKIP_MIGRATIONS=1 make update
```

### What to Check After Update

1. Review `upload/VERSION` to confirm the new version
2. Check the [changelog](https://dockercart.net/changelog) for breaking changes
3. Verify the storefront and admin panel load correctly

Full script: `update.sh`

### Automatic Update-Availability Check

The admin "System Update" page and the header update-bell show whether a newer version is available. The remote version/changelog are cached in `oc_setting` (keys `dockercart_update_*`) by a background scheduler task `dockercart_update_check` → `bin/dockercart_update_check.php`, running hourly.

Admin page loads and the header update-bell **only read the cache** — they never make a network request, so views are never blocked on the remote repo. The "Check" button on the update page forces a fresh network fetch (`refreshUpdateInfo()`) and updates the cache. If the scheduler is disabled, the cache simply goes stale until a manual "Check" or scheduler re-run.

If network fetching fails (e.g. no outbound internet / DNS), the worker writes the reason to its log (`docker/scheduler/...` or `make scheduler-logs`) — e.g. `Resolving timed out` — so an empty changelog is diagnosable rather than mysterious.

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

### Admin Roles & Permissions

Admin users belong to a group (`oc_user_group`). A group stores two route lists in
`oc_user_group.permission` (JSON): `access` (the page may be opened, checked by
`admin/controller/startup/permission.php`) and `modify` (changes may be saved).

**Administrator is an implicit superuser.** `Cart\User::hasPermission()` returns `true`
for `user_group_id = 1` regardless of the stored JSON, so every page — including pages
added by future updates — is available to it and no “add the route to the admin group”
migration is needed. The group cannot be deleted.

**Standard roles come from templates.** `upload/system/library/dockercart/roles.php`
(`DockercartRoles`) is the single source of truth for the ready-made role sets: sales
manager, support operator, content manager, SEO/marketer, courier, storekeeper and
accountant (read-only). Create them with **Users → User Groups → “Create standard roles”**:
the action is idempotent and matches existing groups by `oc_user_group.template_code`,
so renaming a group never produces a duplicate. A template can also be applied to any
group in its edit form (“Role Template”), after which the permissions can be tuned by hand.

**Action-level permissions (capabilities).** Instead of a third permission list, a
capability is a pseudo route stored in the `modify` list and checked with
`Controller::userCanModify()`, which accepts the parent route as an alternative:

| Capability | Route | Allows |
| --- | --- | --- |
| Order status | `sale/order/status` | `sale/order/addHistory`, `sale/order_detail/addHistory` |
| Shipments / tracking | `sale/order/shipment` | `sale/order_detail/addShipment`, `deleteShipment`, tracking-number `updateField` |

So a group holding `modify` on `sale/order` keeps full control, while a Courier group
holds only the two capabilities: it can move an order through its status flow and add a
tracking number, but cannot delete orders, edit prices, take payments or issue refunds.
Read-only roles get `access` without `modify`; the order pages then hide the editing
affordances (`can_edit` / `can_status` / `can_shipment` flags).

**When you add a new admin controller**, add its route to the templates in
`DockercartRoles::templates()` so the standard roles can reach it. Routes under
`startup/*`, `event/*` and `mail/*` are not navigable pages, are never permission
checked, and are hidden from the group form.

---

## 11. Configurable Products & Variants

Configurable products (a.k.a. "combined variants") allow a single product to have
multiple purchasable variants defined by axis options (e.g. Size × Color). This is
the DockerCart equivalent of OpenCart's product options, but with a dedicated variant
layer that holds its own SKU, price, stock, and image per combination.

### Data Model

| Table | Purpose |
|---|---|
| `oc_product_configurable` | Per-product configurable flag + `default_variant_id` (single source of truth for default) |
| `oc_product_configurable_option` | Axis options for a product (e.g. Size, Color) |
| `oc_product_variant` | One row per purchasable variant: SKU, price, quantity, image, `variant_hash` |
| `oc_product_variant_value` | Maps a variant to its axis option values (one row per axis) |
| `oc_dockercart_product_variant_customer_group_price` | B2B price override per variant + customer group |
| `oc_dockercart_product_variant_special` | Per-variant special prices (customer group, priority, date range, auto-renew) |

### variant_hash — O(1) Variant Resolution

`oc_product_variant.variant_hash` is a denormalized string built from the variant's
option value IDs, ordered by option ID and joined with `-` (e.g. `"123-456"`).

- **Built by:** `ProductConfigurable::buildVariantHash()` / `buildVariantHashFromValues()`
- **Maintained on:** `addVariant()`, `updateVariant()` (when `values` change)
- **Rebuilt by:** `rebuildVariantHashes($product_id)` — called after `setConfigurableOptions`
- **Unique index:** `ux_product_variant_hash (product_id, variant_hash)` — prevents duplicates
- **Used by:** `resolveVariant()` — single SELECT with unique index instead of N subselects

Changing the hash format is a **breaking change** that requires `rebuildVariantHashes()`
on all configurable products.

### Default Variant

The default variant is stored exclusively in `oc_product_configurable.default_variant_id`.

- Set via `setDefaultVariant($variant_id)` (admin AJAX action `setDefault`)
- When the default variant is deleted, a new default is auto-selected: first active
  variant by `sort_order ASC, variant_id ASC`
- The legacy `oc_product_variant.is_default` column was removed in migration
  `20260721_drop_is_default.sql`

### Axis Option Prices

For axis options (options used as variant axes), `product_option_value.price` is always
`0`. The real price lives on `product_variant.price`. This is enforced by:

1. `setConfigurableOptions()` zeroes existing POV prices when an option becomes an axis
2. Admin UI renders axis POV price/points/weight fields as `readonly` with a lock icon
3. `dcValidateAxisPrices()` JS handler zeroes any non-zero axis price on form submit
4. Migration `20260721_zero_axis_pov_prices.sql` cleans up legacy non-zero values

### B2B / Customer Group Prices

`ProductConfigurable::getAggregatedPriceRange($product_id, $customer_group_id)` accepts
an optional customer group ID. When provided, it `LEFT JOIN`s
`dockercart_product_variant_customer_group_price` and uses `COALESCE(cgp.price, pv.price)`.

The storefront (`product/product.php`) and cart (`cart.php`) both pass
`config_customer_group_id` so B2B customers see their negotiated variant prices.

### Variant Specials

Configurable products support per-variant special prices via the
`oc_dockercart_product_variant_special` table (managed in the admin Variants tab).

- Each variant can have multiple special price entries (different customer groups,
  priorities, date ranges).
- Specials are evaluated with `ORDER BY priority ASC, price ASC LIMIT 1` (same as
  `oc_product_special` for simple products).
- A variant special replaces the variant's effective price only when the special
  price is **lower** than the effective price (base variant price or B2B
  customer-group override).
- The storefront product page and cart both apply variant specials.

---

## 12. Buy X Get Y (BXGY)

BXGY promotions give customers a discount on a reward product when they buy a
qualifying trigger product. Rules are configured per-product in the admin
product edit form (Promotions section), similar to Gift Promotions.

### Data Model

Table `oc_product_bxgy`:

| Column | Type | Description |
|---|---|---|
| `product_bxgy_id` | INT PK | Auto-increment |
| `product_id` | INT | Trigger product (what the customer buys) |
| `reward_product_id` | INT | Reward product (gets the discount) |
| `trigger_quantity` | INT | How many trigger products to buy (default 1) |
| `discount_type` | ENUM | `free` or `percentage` |
| `discount_value` | DECIMAL | Discount value (0 for free, 50 for 50%) |
| `date_start` | DATE | Start date (0000-00-00 = always) |
| `date_end` | DATE | End date (0000-00-00 = always) |
| `auto_renew` | TINYINT | Auto-renew when expired |

### Calculation Logic

Library: `system/library/bxgy.php`

1. Collect all active BXGY rules for products currently in the cart.
2. Count total trigger product quantities.
3. For each reward product in the cart, find all applicable rules.
4. Select the **best single rule** (highest discount) — rules do not stack on
   the same reward product.
5. Apply discount: `min qualifying, trigger_sets × price` for free, or
   `min qualifying, trigger_sets × price × (value/100)` for percentage.

### Cart Integration

- `bxgy.php` library is loaded by `cart.php` controller.
- Per-item BXGY discounts are passed to the cart and checkout templates.
- Discounted items show: original price (strikethrough) + discounted price +
  BXGY badge.
- Total BXGY discount appears as a line in cart totals (via total extension).

### Admin UI

- Open product → Promotions section → Add Promotion → Buy X Get Y.
- Each BXGY card has: Reward Product (autocomplete), Trigger Quantity,
  Discount Type (Free/Percentage), Discount Value, Date Range, Auto-renew.

---

## 13. Order Multilingual Display

Orders snapshot localized strings at checkout time on the customer's language:
`oc_order.payment_method` / `shipping_method`, `oc_order_product.name`,
`oc_order_option.name` / `value`, `oc_order_total.title`. The snapshot is the
historical record (emails and invoices use it as-is) and is never rewritten —
but it is not translated when the viewer uses a different language.

### Resolution Layer (`system/library/order_localizer.php`)

`OrderLocalizer` re-resolves the snapshot into the **display language**
(`config_language_id`: admin UI language in admin, customer's current storefront
language in catalog) at render time, falling back to the stored snapshot when the
referenced entity or translation is gone:

| Method | Resolves from | Fallback |
|---|---|---|
| `paymentMethodTitle($order)` | `payment_code` `dockercart_universal.dockercart_universal_{id}` → `oc_dockercart_universal_payment_description.name` | stored `payment_method` |
| `shippingMethodTitle($order)` | universal `name` + `delivery_time` (same format as checkout quotes) | stored `shipping_method` |
| `paymentEntryTitle($payment)` | like `paymentMethodTitle` but for `oc_order_payment` rows | stored |
| `productName($order_product)` | `oc_product_description.name` by `product_id` | stored `name` |
| `optionName($order_option)` | `product_option_id` → `oc_option_description.name` | stored `name` |
| `optionValue($order_option)` | `product_option_value_id` → `oc_option_value_description.name` for select/radio/checkbox/color/image; free text (text/date/file) kept as stored | stored `value` |
| `totalTitle($total, $shipping_title)` | `shipping` → resolved shipping method; `sub_total/total/handling/low_order_fee/credit` → `text_*` keys from `extension/total/{code}` (language files live in `catalog/language/` — the resolver falls back to `DIR_CATALOG . 'language/{code}/...'` when the admin's own `Language` object cannot see them); `coupon/reward/voucher` → same key + parenthesized token re-extracted from the stored title | stored `title` |
| `countryName($order, $type)` / `zoneName($order, $type)` | `oc_country_description` / `oc_zone_description` by `{payment\|shipping}_country_id` / `_zone_id` (`COALESCE` to `oc_country.name` / `oc_zone.name`; table presence checked via `information_schema`, cached) | stored `_country` / `_zone` snapshot |
| `historyComment($entry)` | checkout-time `order_payment_method` marker (`comment_key` + `comment_params.code`) → re-renders payment method title + description from the universal method descriptions in the display language | `null` → caller uses stored comment |

Notes:

- `tax` total titles fall back to the stored snapshot: the tax rate id is not
  stored in `oc_order_total`, so the per-language `oc_tax_rate.name` is
  unreachable retroactively.
- Renamed/deleted products and options show the current translation in the
  display language, falling back to the order-time snapshot.
- Order status names were already multilingual (`oc_order_status` is
  `PRIMARY KEY (order_status_id, language_id)`, joined on the viewer's language).

### Surfaces

Resolution is applied in **controllers** (display layer) only — models keep
returning raw snapshots for reports and emails:

- Admin: `sale/order_detail` (detail, print, timeline, payments, address
  country/zone via `formatAddress`), `sale/order` (list),
  `extension/dashboard/recent` (cards + product names).
- Catalog: `account/order` (customer order info: methods, products, options,
  totals, payments, address country/zone, order history comments).
- Emails (`mail/order.php`) are unchanged — they use the order's own language.

### Structured Timeline Notes

Auto-generated admin notes («Payment received…», «Order total changed…»,
reversals) are stored with an i18n key so they render in the viewer's language:

- Migration `20260801_order_history_i18n.sql` adds
  `oc_order_history.comment_key` (varchar) and `comment_params` (mediumtext JSON)
  to `oc_order_history` — both idempotent, safe to re-run.
- `ModelSaleOrder::addOrderNote($order_id, $comment, $notify = false,
  $comment_key = '', $comment_params = [])` keeps the formatted text in
  `comment` (legacy display + email paths) and stores key + params alongside.
- Timeline rendering (`order_detail::getTimeline` →
  `renderTimelineComment()`) re-renders `sprintf(language->get($key), ...$params)`
  when a key exists; legacy rows without a key show the stored text as before.
- Note keys live in `admin/language/{en-gb,ru-ua,uk-ua}/sale/order.php`
  (`text_payment_note_*`, `text_payment_reversal_comment`,
  `text_overpayment_reversal_comment`). Keep all three locales in sync when
  adding new keys.
- Manual notes/comments typed by an admin are free text and are never
  translated.
- The checkout (`extension/payment/dockercart_universal` `confirm()`) writes the
  selected payment method as an order history entry with
  `comment_key = 'order_payment_method'` and `comment_params = {code}` (the
  formatted title+description stays in `comment` as fallback). The timeline and
  the customer's order history render it through `OrderLocalizer::historyComment()`
  in the viewer's language; legacy entries without the marker show the stored
  text. `ModelCheckoutOrder::addOrderHistory()` (catalog) accepts the extra
  `$comment_key` / `$comment_params` arguments.
- Only one status entry is written per status change: when the payment `confirm()`
  runs with the same status the checkout already recorded (the common case where
  the payment method's order status equals the store default), the payment method
  is appended as a **note** (`addOrderNote()`, `order_status_id = 0`) instead of a
  second status record — the timeline then shows the single "Pending" entry plus a
  note with the payment method, not a duplicated status.
- Admin Order Details status changes go through the order flow (see
  [Order Flow](#14-order-flow)): the flow action buttons / "Change status"
  block call `addHistory`, which appends a timeline record only when the
  transition succeeds. Editing other order fields via panel save never adds
  a status record.

## 14. Order Flow

The order flow is a **configurable status workflow** built on top of the
existing `oc_order_status` catalogue — no separate state machine engine.
Operators move orders through the flow from the order details page; the
flow itself is edited on the **System → Order Flow** page.

### Configuration (`oc_setting`, store 0)

Two JSON settings define the flow:

| Setting | Meaning | Default |
|---|---|---|
| `config_order_flow_steps` | Ordered chain of status IDs. New orders start at the first step and advance one step at a time. | `["1","132","133","128","129"]` (Pending → Confirmed → Packing → Shipped → Delivered) |
| `config_order_flow_transitions` | Extra allowed transitions, map of `from status ID → [to status IDs]`, in addition to "forward to next step". | `{"1":["130","131"],"131":["130","132"],"132":["130","134"],"133":["130","134"],"128":["130","134"],"129":["134"]}` (Awaiting Payment as a side status from Pending back to Confirmed, Cancelled from every stage, Refunded after Confirmed) |

Rules:

- A step may always move **forward to the next step**; everything else
  (cancellation, refund, skips, backwards moves) must be listed as an extra
  transition.
- A status with **no outgoing transitions** (no next step, no extras) is
  terminal — it ends the flow (e.g. Cancelled/Refunded).
- Statuses not present in the chain are not reachable by the flow at all.
- When `config_order_flow_steps` is empty/absent the flow is **disabled** and
  any transition is allowed (backwards compatible with custom setups).

### Enforcement

`admin/model/sale/order.php addOrderHistory()` validates every transition
against the configured flow (`system/library/order_flow.php`, class
`OrderFlow`) and returns `false` when the move is not allowed. The AJAX
endpoints `sale/order/addHistory` and `sale/order_detail/addHistory` surface
the rejection as `error_invalid_transition`.

- **Override**: passing `override = true` skips validation entirely (timeline
  "Add History" form, "Change status" block on the order page). This is the
  escape hatch for exceptions; it is the same mechanism as before the flow.
- Re-setting the current status is a no-op and never blocked.
- Stock/commission side effects are untouched: they still follow
  `config_processing_status` / `config_complete_status` (the migration adds
  Packing to the processing statuses so stock is subtracted when an order
  enters Packing or Shipped).

### Order page UI

A horizontal stepper bar (`sale/order_flow_stages.twig`) renders under the
page header from the configured chain, with the current stage highlighted and
buttons for every allowed transition. Clicking a button opens a small modal
(comment + notify customer). Terminal transitions (Cancelled/Refunded) ask for
explicit confirmation. The previous free-form status select is still available
in the sidebar under "Change status" with a "Force" checkbox that bypasses
validation.

### Shipments (tracking numbers)

Order tracking lives in `oc_order_shipment` / `oc_order_shipment_item`
(migration `20260802_add_order_shipments.sql`). One row = one tracking
number; items store how much of each order product goes with that number,
so **partial shipments** are just several rows for the same order:

- The **Shipments** sidebar card lists every tracking number with its items,
  plus a shipping progress bar per product (shipped / ordered).
- The **transition modal** shows a tracking-number field and per-product
  quantities (pre-filled with the remaining amount) when the target status
  equals `config_order_flow_shipping_status` (default `128` Shipped; set on
  the System → Order Flow page). Confirming creates the shipment first and
  then moves the order status.
- Additional partial shipments can be added any time from the Shipments card
  (e.g. while the order is already Shipped).
- `oc_order.tracking_number` is kept in sync as a `|`-joined aggregate of all
  shipment tracking numbers (used by the order list / print views); deleting
  a shipment or an order product rebuilds it.

### Notes

- The default chain uses statuses `1, 132, 133, 128, 129` (131 Awaiting
  Payment is a side status, not a step); 132 (Confirmed), 133 (Packing) and
  134 (Refunded) are added by migration `20260801_add_order_flow.sql`. All
  three are plain `oc_order_status` rows — they can be renamed/translated in
  Localisation → Order Statuses and even removed from the flow without code
  changes.
- Admin-created custom statuses work unchanged: they are simply not part of
  the chain, so they require override to be set.
- The configurator (`sale/order_flow` controller + `sale/order_flow.twig`)
  validates submitted statuses against `oc_order_status` and writes the
  settings idempotently (`INSERT IGNORE`-style; re-running `make migrate`
  never clobbers your customizations — the seed only applies when the keys
  are absent).

## 15. Product Returns (full / partial / exchange)

Returns are multi-item entities (`oc_return_product` per returned order line,
migration `20260803_add_return_products.sql`) with a return **type**, a
**refund amount** and a **refunded** flag:

| Type | Meaning |
|---|---|
| `full` | Everything is returned. On completion the order is moved to the Refunded order status (134) |
| `partial` | Part of the items / quantities is returned; the order keeps its status |
| `exchange` | Items are returned to stock, no money is refunded (price difference is settled manually via the order's payment journal) |

### Workflow

- **Create from the order page**: the "More actions" menu on the order
  details page links to `sale/return/add&order_id=...`, which pre-fills the
  customer data and the order's line items (checkbox + quantity per item,
  unit price from the order).
- **Refund**: a "Refund money" checkbox plus the refund amount (JS
  auto-calculates: `full` → paid amount, `partial` → sum of selected items,
  `exchange` → 0). The amount is capped at `paid_amount`.
- **Completion** (return status → Complete, 3) automatically:
  1. restocks every returned item (`oc_product.quantity`, variant-aware),
  2. creates a reversal in the order's payment journal
     (`oc_order_payment`, `addOrderRefund()`, capped at `paid_amount`) and
     marks the return `refunded`, and
  3. for `full` returns, moves the order to the Refunded order status (134)
     via `addOrderHistory(..., override=true)`.
  Re-running the completion (already Complete) does nothing — restock/refund
  are guarded by the status change and the `refunded` flag.
- The admin return form still supports the legacy single-product path
  (manual product/model/quantity) when no order items are available.

### Catalog side

`catalog/model/account/return.php addReturn()` now also writes the returned
line into `oc_return_product` (linked to `oc_order_product`, including
variant and price), so customer-created return requests show full item
details in admin.

### Notes

- The return status machine is unchanged (Pending / Awaiting Products /
  Complete) — the money movement is tied to **Complete**, not to the action.
- `oc_return_action` (Refunded / Credit Issued / Replacement Sent) remains a
  descriptive field.
- Emails to the customer still go through the `admin_mail_return` event when
  a return history entry is added with `notify = 1`.

---

## 16. Warehouses & Dropshipping

Multi-warehouse stock, self-pickup and dropshipping (migration
`20260823_00_warehouses.sql`, core library `system/library/dockercart_warehouse.php`).

### Data model

| Table | Purpose |
|---|---|
| `oc_warehouse` | Warehouse registry: type (`physical`/`virtual`/`dropship`), `is_default`, `priority`, `status`, address + geo, `prepare_days`, `low_stock`, self-pickup (`allow_pickup`, `pickup_cost`, `pickup_note`), dropship supplier block |
| `oc_warehouse_schedule` | 7-weekday open flag per warehouse (`day_of_week` 1=Mon..7=Sun) |
| `oc_warehouse_schedule_window` | Multiple pickup windows per weekday (`time_from`/`time_to`) |
| `oc_warehouse_holiday` | `warehouse_id` 0 = shared holiday for all warehouses; `is_open` marks a "working holiday" |
| `oc_warehouse_stock` | **Source of truth**: `warehouse_id` × `product_id` × `variant_id` (0 = base), `quantity`, `unlimited` (dropship), `lead_time` override |
| `oc_warehouse_stock_movement` | Journal: signed `quantity`, `type` (`inbound`/`outbound`/`adjustment`/`transfer_in`/`transfer_out`/`order_subtract`/`order_restock`/`return`), `reference`, `order_id`, `transfer_id`, `user_id`, `comment` |
| `oc_warehouse_transfer` / `_item` | Transfers between warehouses; `status` `pending → in_transit → completed/cancelled`; stock moves on `completed` |

Existing tables altered:

- `oc_stock_reservation` + `warehouse_id` — holds are scoped to the warehouse
  the line was allocated to.
- `oc_order_product` + `warehouse_id`, `warehouse_name`, `estimate_date`
  (snapshot per line), and dropship-supplier fields `supplier_status`,
  `supplier_ordered_date`, `supplier_tracking`, `supplier_cost`.

`oc_product.quantity` / `oc_product_variant.quantity` remain the denormalised
**SUM cache** across warehouses (rewritten on every mutation via
`recomputeTotals()`). All legacy reads (cart, lists, sorting, `hasStock`,
badges) keep working. A dropship `unlimited` line contributes the sentinel
`999999`.

### Allocation

`DockercartWarehouse::allocate($lines)`:

- candidates = active warehouses whose `available ≥ qty` (dropship rows
  participate only when `config_warehouse_dropship_checkout`), ordered
  `priority DESC, sort_order ASC, id ASC`.
- Default is **one warehouse per order**: the first candidate covering the
  whole cart wins.
- `config_warehouse_split_allowed=1` falls back to per-line allocation.
- A forced warehouse (pickup choice, or a seller move) is preferred.
- Returns `[line_key => warehouse_id]` + per-line estimated ship dates.

`available = stock − SUM(active holds for that warehouse)`, with dropship
rows returning `+INF`. Forced pickup (`pickup@W`) recomputes allocation onto
that warehouse.

### Schedule, holidays & estimates

- `isWorkingDay(warehouse, dt)`: a specific holiday beats a shared holiday,
  else the weekday schedule row; no row → default open Mon–Sat.
- `estimateShipDate(warehouse, line, from)`: advance `prepare_days + lead_time`
  **working days**, skipping closed days and holidays.
- `nextPickupSlot(warehouse, from)`: nearest open day + window for self-pickup.

### Checkout & orders

- Reservation holds are scoped per warehouse (`dockercart_stock_reservation`
  `reserve()` threads `warehouse_id` through each line); the availability
  check locks the stock row `FOR UPDATE`, so two concurrent checkouts of the
  last unit serialize instead of both holding it.
- Order creation (`catalog/model/checkout/order.php addOrder()` / `editOrder()`)
  snapshots `warehouse_id`/`name`/`estimate_date` into `oc_order_product`.
- Stock is **subtracted / restocked via `adjustStock()`** on the order line's
  warehouse (processing/complete → subtract, reversal → restock; returns use
  the original `oc_order_product.warehouse_id`). This is the **single write
  path** — the legacy direct `oc_product`/`oc_product_variant` cache updates
  were removed, so the caches always equal the warehouse sums and can never
  drift (the daily audit only confirms the invariant). Non-tracked lines
  (`subtract = 0`, e.g. preorders) are skipped via
  `DockercartWarehouse::tracksStock()`.
- Admin can move a line to another warehouse in order detail → `moveStock()`
  + a movement journal entry (reference = order number) + updated snapshot.
- Enabling `config_warehouse_enabled` backfills `oc_order_product.warehouse_id
  = 0` rows onto the default warehouse so restocks/returns of pre-enable
  orders land on an explicit warehouse.

### Catalog-side quantity edits

Every catalog editor routes its quantity through the warehouse layer
**regardless of `config_warehouse_enabled`** (the flag only controls
allocation/UI, not the write path):

- product form / list inline-edit (`ModelCatalogProduct`) and variant qty
  (`ProductConfigurable::updateVariantQuantity` / `addVariant` /
  `updateVariant`) call
  `DockercartWarehouse::setTotalQuantity($product_id, $new_total, $variant_id)`
  — with warehouses backed by a single "default" row this behaves like the
  legacy field; with multiple warehouses the difference vs the current
  `oc_warehouse_stock` sum is applied as an `adjustment` movement
  (`reference` `product-form` / `product-inline` / `variant-form`) on the
  **default warehouse first**; when a negative delta saturates that row at 0
  the remainder is spread over the other warehouses, so the requested total
  is always reached (returned by the method) — never silently dropped.
  Caches recompute afterwards.
- deleting a variant purges its stock rows + holds always (no ghost
  quantities, also when warehouses are off);
- the product form shows a hint under Qty linking to the stock card modal.

Multi-warehouse distribution stays a Warehouses-section task: catalog edits
only ever move the default-warehouse line.

### Availability display

- Admin stock matrix (Warehouses → Stock) shows **Qty / Reserved / Available**
  per cell; the product card modal and the product form Qty area show the
  same three numbers (`available = quantity − active holds`, unlimited rows
  render ∞).
- The product card modal: opened from a matrix row it labels the cell as
  `On warehouse "<name>"`; opened without a warehouse context it renders a
  **per-warehouse breakdown** (simple products) or a **per-variant**
  breakdown (configurable products — their head row has no stock of its own,
  so a plain "total" would be a misleading zero).
- For simple products the modal offers **Set total stock**: routed through
  `setTotalQuantity()` (rebalance semantics) so the field in the product form
  stays in sync. The product form Qty field is **read-only for configurable
  products** (stock is managed per variant in the inventory block / variant
  matrix) and editable for simple ones.
- **Manual legacy backfill**: the stock screen's *Recalculate totals*
  materialises cache-only leftovers (simple products and active variants
  with `subtract=1` + `quantity>0` that have no `oc_warehouse_stock` rows
  yet) onto the default warehouse, journaled as `recalculate-backfill`
  movements. It runs only when a human clicks the button — read paths never
  write.
- Storefront product page subtracts other buyers' active checkout holds from
  the displayed "In stock" figure (listings keep the plain cache badge —
  holds expire within the reserve window anyway).

### Self-pickup

The `dockercart_warehouse_pickup` shipping extension offers one quote per
warehouse with `allow_pickup=1` that covers the cart. Choosing it forces the
allocation to that warehouse. Quoted title includes address/phone and the
nearest pickup slot.

### Admin section

Top-level **Warehouses** menu (ACL `warehouse/*`): warehouse CRUD (incl.
schedule + holidays + pickup + dropship), stock matrix with AJAX cell editing,
movement journal + per-position running balance, transfers, and dropship
supplier orders (deadlines + CSV export). Supplier Orders rows carry an inline
per-unit **purchase price** (`oc_order_product.supplier_cost`, store default
currency; empty = not set) saved via `updateLine()` `action=set_cost`.

The **Analytics** page has a *Dropshipping Supplier Profit* report
(`extension/report/supplier_profit`): revenue = Σ `order_product.total`,
purchase = Σ `supplier_cost × quantity` over lines with a known price; lines
without a price are excluded from purchase/profit and shown in a separate
"No Price" column. Filters: order-date range + supplier status.

### Configuration

`config_warehouse_enabled`, `_split_allowed`, `_stock_display`, `_show_pickup`,
`_estimate_enabled`, `_dropship_checkout` (seeded in the migration, editable in
Settings → Options → Warehouses). A daily scheduler task `warehouse_audit`
(`bin/dockercart_warehouse_audit.php`) recomputes the caches and self-heals
drift; the stock screen offers a manual "Recalculate totals" run.

---

## 17. Admin REST API

A bearer-token API for the admin panel: catalog (products, categories,
manufacturers), orders, customers, returns, warehouses/stock, blog and reference
data. It is an **admin** API — every request runs as an admin principal built
from an API key, so permissions, order-flow rules, warehouse movements and
everything else behave exactly like an operator in the panel.

### Public surface

```
GET    /api/v1/products?search=iphone&limit=20
POST   /api/v1/products                  { "model": "...", "descriptions": { "en-gb": { "name": "..." } } }
GET    /api/v1/orders/123                (lines, totals, history, payments)
POST   /api/v1/orders/123/history        { "order_status_id": 132, "comment": "Confirmed", "notify": true }
PATCH  /api/v1/products/42/stock         { "quantity": 25 }
GET    /api/v1/customers?email=…         (profile, addresses, order/reward aggregates)
GET    /api/v1/returns?status_id=1       (return requests)
POST   /api/v1/returns                   { "order_id": 123, "products": [{ "order_product_id": 456, "quantity": 1 }], "return_reason_id": 1 }
GET    /api/v1/warehouses                (all warehouses, disabled ones flagged)
GET    /api/v1/warehouses/1/stock        (quantity, reserved, available per product/variant)
POST   /api/v1/stock/adjustments         { "adjustments": [{ "warehouse_id": 1, "product_id": 42, "delta": -3 }] }
GET    /api/v1/meta/languages            (codes for `descriptions`)
GET    /api/v1/meta/return-statuses      (ids for returns, with `-reasons` and `-actions`)
```

Customers, returns and warehouses reuse the panel's own permission routes
(`customer/customer`, `sale/return`, `warehouse/warehouse`, `warehouse/stock`), so
the standard roles (sales manager, storekeeper, accountant, …) already grant the
matching read/write access without a second permission model. Customer
credentials (`password`, `salt`, `token`, `code`, `ip`, `cart`, `wishlist`,
`remember_token`) are never part of a representation.

Returns are created **from an order**: the client sends the order line ids and
quantities, and the contact details, product name/model, price and variant are
snapshotted from `oc_order_product` (the same auto-fill the panel form does). A
return does not move stock or money when it is created — that happens on
completion (`POST /v1/returns/{id}/history` reaching the Complete status), which
restocks the lines through the warehouse layer, refunds `amount` once and moves a
`full` return's order to Refunded. `restock` cannot be disabled from the API.

Stock is only ever written through the warehouse layer: `PATCH
/v1/products/{id}/stock` sets an absolute total, while `POST /v1/stock/adjustments`
applies **signed deltas as one atomic batch** (1–100 lines, movement type
`adjustment`, `reference`/`comment` land in `oc_warehouse_stock_movement`). If a
delta would take a warehouse row below zero the whole batch is rolled back and
answered `409 insufficient_stock` with the offending lines in `fields` — an
external system can never believe stock moved when it did not. `GET
/v1/warehouses/{id}/stock` exposes `quantity` (physical), `reserved` (active
reservations) and `available` (`null` for unlimited dropship rows).

Full reference: **System → API → Documentation** in the panel (a standalone
Swagger UI page, served from `upload/api/openapi.json`; the panel session is
required, so the API surface is not advertised publicly). `GET /v1/meta/sections` returns every
endpoint together with the permission it requires.

Conventions:

- success `{"data": …}` (collections add `"meta": {"page","limit","total","pages"}`)
- failures are **RFC 9457 problem documents** (`Content-Type: application/problem+json`):
  `{type, title, status, detail, instance, code, request_id, fields?}` — `type` is a
  stable URN (`urn:dockercart:api:error:validation_failed`), `code` repeats its last
  segment, `fields` maps 422 problems per field, `request_id` matches the journal.
  Statuses: `401` invalid/expired/revoked key, `403` permission, `404` unknown
  resource, `405` wrong method, `409` idempotency conflict, `412` stale `If-Match`,
  `422` validation, `428` missing `If-Match` (strict mode), `429` rate limit,
  `503` API disabled. A refused stock adjustment is `409 insufficient_stock` and a
  refused order transition `409 invalid_transition`.
- dates ISO-8601, money fixed point strings with 4 decimals
- multilingual fields are keyed by **language code** (`en-gb`, `ru-ua`, `uk-ua`),
  not by `language_id`
- `PUT` replaces the document (relations missing from the body are removed, like
  saving the panel form), `PATCH` merges into the current document
- order status changes always follow the configured order flow: `override` is
  rejected (`422`) and a refused transition answers `409 invalid_transition`
- pagination: `?page=1&limit=20` (max 100) + per-resource filters;
  `?language=ru-ua` switches the language used for joined descriptions

### Concurrency control (ETag / If-Match)

Every single-resource GET returns a strong `ETag` (sha1 of the exact
representation). Clients that edit data should:

1. `GET /v1/products/42` → keep `ETag`;
2. send it back as `If-Match: "<etag>"` on `PUT`/`PATCH`/`DELETE`;
3. on `412 Precondition Failed` re-read the resource and re-apply the change.

`If-Match: *` only requires the resource to exist. `If-None-Match` on GET answers
`304 Not Modified` with the current ETag (cheap polling). A missing `If-Match` is
tolerated by default for one-way integrations; set
`config_api_require_if_match=1` (System → API → Keys → API settings) to reject such
writes with `428 Precondition Required`. nginx appends `-gzip` to the ETag of a
compressed response — the API strips that suffix (and `W/`) before comparing, so
echoing the header verbatim works.

### Retries (Idempotency-Key)

POST endpoints accept an optional `Idempotency-Key` header (8–128 printable ASCII
chars, unique per API key + endpoint):

```bash
curl -X POST https://shop.example/api/v1/products   -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json"   -H "Idempotency-Key: 7b1f0c42-import-2026-10-21-01"   -d '{"model":"SKU-1","descriptions":{"en-gb":{"name":"Imported"}}}'
```

The first request executes and its answer is stored for 24 h
(`oc_api_idempotency`); a retry with the same key **and the same body** replays
that answer verbatim (`201`, `Idempotent-Replay: true`) instead of creating a
second product. The same key with a different body answers
`409 idempotency_conflict`; a concurrent retry while the first is still running
gets `409 idempotency_in_progress` (5 minute lock). 5xx answers are not stored, so
they can be retried.

### Documentation & discovery

`GET /api/v1/openapi.json` — the only unauthenticated endpoint; returns the
OpenAPI 3.0 document (cacheable: `ETag` + `max-age=300`, `304` on
`If-None-Match`). Swagger UI in the panel (**System → API → Documentation**, the
`setting/api_docs` access permission) renders exactly that document, so an
integrator and the panel never drift apart.

The documentation is a **standalone page** (its own document, no panel chrome):
Swagger UI brings its own stylesheet and does not belong into the panel
layout. The page still requires the panel session, so the API surface stays
undiscovered from the outside — only the OpenAPI document itself is public.

### Authentication & keys

Keys are managed in **System → API → Keys** (the sidebar nests them under
*System → API*):

- the plain token (`dcapi_…`) is displayed **once**, on create/rotate; only its
  SHA-256 hash is stored (`oc_api_key.token_hash`), so a database dump cannot be
  replayed;
- send it as `Authorization: Bearer <token>` (or `X-Api-Key: <token>`); tokens in
  the query string are rejected on purpose (they would leak into access logs);
- optional expiry date, IP allowlist (IPs/CIDR, v4 + v6) and per-minute rate
  limit; `0` rate limit falls back to the global value;
- revoke (`status = 0`) or rotate at any time — both take effect immediately.

**A key belongs either to an admin user or to nobody** (`oc_api_key.user_id`):

- `user_id > 0` — the key is owned by a person. It acts with the permissions of
  the **current** group of that user, so moving the user to another role
  immediately changes what the key may do. A disabled or deleted owner
  invalidates the key (`401 token_revoked`); deleting the user removes the key.
- `user_id = 0` — a shared key that uses the group stored in
  `oc_api_key.user_group_id`.

**Permissions always come from an admin user group**: GET endpoints require the
group's `access` permission on the route, write endpoints its `modify`
permission. So `oc_user_group.permission` stays the single source of truth and
the standard roles (`DockercartRoles`) can be reused as-is. Order endpoints also
honour the capabilities `sale/order/status` and `sale/order/shipment`.

The keys of a user are managed **in the user form itself** (Users → Users →
edit): the panel shows an *API keys* card with the keys of that user and
buttons to create, rotate, revoke, enable and delete them. The card appears
when the operator has the `setting/api_key` access permission (the actions need
`modify`), so a support operator can hand out a token without leaving the
user.

The panel pages of the API are **not** part of any standard role template
(`DockercartRoles`) — by default only the Administrator reaches them. Grant
`setting/api_key` explicitly (access + modify) if another role should manage
keys; that role then also gets the *API keys* card of the user form.

Global switches (**System → API → Keys → API settings**, saved with
`updateSetting('config', …)`): `config_api_status` (kill switch → `503`),
`config_api_rate_limit` (default per-key limit),
`config_api_log_retention_days` (journal retention) and
`config_api_require_if_match` (mandatory `If-Match`, strict optimistic locking).

### Request journal

Every request is journalled in `oc_api_log` (key snapshot, method, path, status,
duration, IP, request-id, error code) and shown in **System → API → Request log**.
Rows are trimmed by age by the `api_log_cleanup` scheduler task
(`bin/dockercart_api_log_cleanup.php`, daily, which also expires
`oc_api_idempotency` rows). The journal is security evidence: it is *not* removed
when a key is deleted.

### Deliberately not exposed

- **Store settings** — no `PATCH /settings` and no settings write at all: a partial
  `editSetting('config', …)` wipes ~100 store options (see AGENTS.md), so the API
  stays out of that surface. Only read-only dictionaries are published
  (`/v1/meta/…`). The panel's API key page itself saves its three options with
  `updateSetting()`.
- **Stock columns** — nothing writes `oc_product.quantity` / `oc_product_variant.quantity`
  directly. `PATCH /v1/products/{id}/stock` goes through
  `DockercartWarehouse::setTotalQuantity()` and `POST /v1/stock/adjustments` through
  `DockercartWarehouse::adjustStockBatch()` (the warehouse layer owns the value and
  the cache), and product create/update routes `quantity` through the same layer via
  `ModelCatalogProduct`.
- **Variants** — read-only (`GET /v1/products/{id}/variants`) and built with
  `ProductConfigurable` (axes, `getVariants()`, aggregated price/stock), so it matches
  the storefront (enabled variants only). The `variant_hash` is derived from the
  option axes: axes, hash rebuilds and axis-option pricing (always `0`) stay panel
  operations. `editProduct()` already excludes axis options from its
  delete/insert pass, so an integration cannot corrupt them through `options`.
- **Order-flow `override`** — not accepted (see above); the capability scope for
  status changes remains `sale/order/status`.

### Implementation map

| Path | Role |
|---|---|
| `upload/api/index.php` | front controller (`start('api')`) |
| `upload/api/config.php` | generated by `docker/entrypoint.sh` (requires `admin/config.php`, defines `DIR_API`) |
| `upload/system/config/api.php` | app config: pre-actions without `startup/login` + `startup/permission` |
| `upload/admin/controller/startup/api.php` | path → route, bearer auth, JSON body, rate limit, JSON error/fatal handlers, journal |
| `upload/admin/controller/api/**` | resource controllers (they call the admin models) |
| `upload/system/library/dockercart/api/**` | `Routes` (endpoint + permission whitelist), `KeyRepository`, `Principal`, `Response` (envelopes, ETag, 304), `Idempotency`, `Error` (RFC 9457 codes), `Validator`, `Serializer`, payload mappers |
| `upload/system/library/dockercart/product_rules.php` | product field rules shared by the panel form and the API |
| `upload/api/openapi.json` | OpenAPI 3.0 document (served at `/api/v1/openapi.json` and to the docs page) |
| `upload/admin/view/javascript/swagger-ui/` | vendored Swagger UI bundle (no CDN) |

The `api` application reuses the **admin MVC tree** (`DIR_APPLICATION` =
`upload/admin/`), so `$this->load->model('catalog/product')` resolves to the
admin models — the same code the panel runs. Panel HTML controllers are *not*
called as API handlers; only the endpoints that already have a JSON contract
(`sale/order/addHistory`, `sale/order/quickEdit`) are reused through
`$this->load->controller()`.

### Web server routing

nginx rewrites `/api/<path>` to `/api/index.php?_route_=/<path>` (`location ^~
/api/`, `rewrite … break`). Apache also maps the path at the **virtual-host**
level, because the webroot `.htaccess` (generated from the SEO settings)
overrides `<Directory>` rewrites. `upload/api/config.php` and
`docker/apache.conf`/`docker/entrypoint.sh` changes need `make restart` (image
rebuild); nginx config is bind-mounted and only needs a reload.

### Extending

1. add the endpoint + required permission to `Dockercart\Api\Routes::TABLE`;
2. add the controller action in `upload/admin/controller/api/…` and do the work
   through the admin model;
3. document it in `upload/api/openapi.json` (the docs page and
   `/api/v1/openapi.json` pick it up automatically), including the `ETag`
   header on reads and `If-Match`/`Idempotency-Key` parameters on writes;
4. if the resource is a new admin section, add the route to the matching
   `DockercartRoles` template so standard roles can reach it.

`GET /v1/meta/sections` is generated from `Routes::TABLE`, and
`upload/api/openapi.json` must list exactly the same method/path pairs — a quick
check while reviewing an endpoint change:

```bash
php -r '$s=json_decode(file_get_contents("upload/api/openapi.json"),true); echo count($s["paths"]), " paths\n";'
```


---

## 18. Webhooks

Outbound event notifications next to the pull REST API: integrations receive
`order.created`, `order.status_changed` and `stock.changed` instead of polling
(`/v1/orders`, `/v1/warehouses/{id}/stock`).

### Outbox, not inline sending

The event is written to `oc_dockercart_webhook_delivery` **inside the business
transaction** (`Dockercart\Webhook\Dispatcher::emit()`), together with the data
change. Nothing is sent from the request: a `COMMIT` without a send can be
retried, a send without a `COMMIT` loses the event.

- the dispatcher never opens a transaction (MySQL would implicitly commit the
  caller's one), never does HTTP and never throws — a webhook problem can never
  roll back an order or a stock movement (errors go to `storage/logs/webhook.log`);
- the row stores the **exact JSON body** that is sent, so a retry (or a
  subscription edit) cannot change what the receiver gets;
- fan-out (subscriptions + filters) is evaluated at emit time;
- `INSERT IGNORE` on `(subscription_id, event_id)` keeps a re-emitted event from
  being delivered twice.

### Emit anchors (single choke points)

| Event | Emitted from |
|---|---|
| `order.created` | `catalog/model/checkout/order.php::addOrder()` before `COMMIT` (never the duplicate-claim path) |
| `order.status_changed` | `admin/model/sale/order.php::addOrderHistory()` and `catalog/model/checkout/order.php::addOrderHistory()`, before `COMMIT`; only for a real `from ≠ to` change with `to > 0` |
| `stock.changed` | `DockercartWarehouse::journalMovement()` — the only place that writes `oc_warehouse_stock_movement`, so every path (order/return/transfer/adjustment/API batch/audit backfill) emits |

`stock.changed` uses `movement_id` as `version` and the admin stock matrix
(`ModelWarehouseStock::setCell()`, `applyCellUpdates()`, backfills) now journals
through the same method instead of inserting movement rows itself.

### Delivery contract

```
POST <subscription url>
Content-Type: application/json; charset=utf-8
User-Agent: DockerCart-Webhook/1.0
X-DockerCart-Event: order.status_changed
X-DockerCart-Event-Id: evt_01J…
X-DockerCart-Delivery-Id: 42
X-DockerCart-Timestamp: 1792588800
X-DockerCart-Signature: sha256=<hmac>
X-DockerCart-Attempt: 1

{"id":"evt_01J…","type":"order.status_changed","occurred_at":"2026-10-21T10:15:00Z",
 "data":{"order_id":1234,"store_id":0,"from_status_id":132,"to_status_id":133,
         "override":false,"source":"admin","is_cancelled":false,"is_final":false,
         "is_terminal":false,"version":88123}}
```

- **Signature**: `sha256=` + `hash_hmac('sha256', $timestamp . '.' . $raw_body, $secret)`
  (verify against the raw body, not a re-serialized array). Retries get a fresh
  timestamp/signature, the same body and the same `event_id`. Reject stale
  timestamps (≤ 5 min is a sane window).
- **Idempotency**: `event_id` is in the body and the header. Duplicates are
  expected; deduplicate by it.
- **Ordering is not guaranteed.** `occurred_at` plus `data.version`
  (`oc_order_history.order_history_id` / `oc_warehouse_stock_movement.movement_id`)
  lets a receiver drop stale events.
- **Thin payload by default.** With `full_payload = 1` order events add
  `data.object` (order header, products + options, totals, payments). It is
  **PII-free** by design: no name/e-mail/phone/addresses and no free-text
  comments — fetch those through `GET /api/v1/orders/{id}`, which is permission
  checked and journalled.
- **Language.** The payload contains no UI strings: the only human-readable
  text in `data.object` (product names, option values, total titles, payment
  method) is the snapshot saved when the order was created, i.e. historical data
  in the **customer's** language — it is never re-translated (that would rewrite
  an invoice). `data.object.language_id`, `language_code` and `language_name`
  tell the receiver which language that snapshot is in; show it as-is or
  localize on your side. The event *catalogue* in the panel
  (System → API → Webhooks) is translated into the operator's language, while
  `GET /v1/webhooks/events` serves the canonical English descriptions (the API
  and its OpenAPI document are English by contract).
- Wildcard `order.cancelled` does not exist: it is `order.status_changed` with
  `is_terminal: true` (and `is_cancelled` from `config_cancelled_status`).

### Retries, failures, auto-disable

Worker: `bin/dockercart_webhook_deliver.php`, system scheduler task
`webhook_deliver` (`* * * * *`, hidden from the scheduler UI). Each run recovers
stale `sending` rows, claims a bounded batch with a conditional `UPDATE`
(`status → sending`, `attempts + 1`, `locked_until`), sends sequentially and
journals status/response/duration.

| Answer | Result |
|---|---|
| `2xx` | `delivered`, `consecutive_failures` reset |
| `408`, `425`, `429`, `5xx`, timeout, DNS/connect error | retried |
| other `4xx` | `failed` at once (retrying cannot help) |
| `410 Gone` | subscription disabled immediately (`disabled_reason = gone`) |

Retry delays are `1 m / 5 m / 30 m / 2 h / 12 h`; after
`config_webhook_max_attempts` attempts (default 6 = first + 5 retries) the row
becomes `failed`. `config_webhook_disable_after` consecutive `failed`
deliveries (default 4) disable the subscription (`repeated_failures`) and cancel
its pending rows — visible in the panel with the reason. Test events
(`webhook.test`) are delivered through the same path but never count towards
auto-disable.

### SSRF protection

Subscription URLs are untrusted input:

- syntax checked at save time — `http`/`https` only, ports 80/443, no
  `user:pass@`, hostname must be a plain hostname or an IP literal (decimal/hex
  IPv4 spellings are refused);
- **re-resolved at send time** (A + AAAA); every address must be public —
  loopback, private, link-local, CGNAT, ULA, documentation and NAT64 blocks are
  rejected (`last_error = ssrf_blocked`);
- the validated address is pinned with `CURLOPT_RESOLVE` (so DNS cannot be
  re-bound between check and request), redirects are not followed, proxy env
  vars are ignored, TLS verification stays on, connect timeout 3 s and the total
  timeout comes from `config_webhook_timeout` (default 10 s).

`WEBHOOK_ALLOW_PRIVATE=1` in the worker's (and, while creating a subscription,
the panel's) environment bypasses the address *and* port checks for local
development (`php -S 127.0.0.1:9999 receiver.php`). It is never read from the
database and only the worker sends.

### Subscriptions & secrets

`oc_dockercart_webhook_subscription` holds `url`, `events` (JSON, wildcards
allowed), `filters` (JSON), `full_payload`, `status`, the failure counters and
`created_by_api_key_id` / `created_by_user_id`.

Secrets are `whsec_` + 64 hex, encrypted with `config_encryption` (AES-256-GCM,
`system/library/encryption.php`) and returned **once** — on create and on rotate.
`GET` exposes only `secret_hint` (first 10 chars). A missing/rotated encryption
key makes deliveries fail with `secret_unavailable` rather than send unsigned.

Filters are matched against top-level `data` keys at emit time: scalar = exact,
array = any-of, scalar filter against an array value = membership (e.g.
`{"warehouse_ids": 2}` matches an order created on warehouse 2). Only keys from
the event catalogue are accepted, so a typo answers `422` instead of producing a
subscription that never fires.

### Panel & API

Panel: **System → API → Webhooks** (list, create/edit, enable/disable, rotate
secret, send test event, delete, global settings) and **System → API → Webhook
log** (journal with filters, payload, receiver response and "Retry" for failed
rows). Both are administrator-only until a group is granted
`setting/webhook` / `setting/webhook_log`.

API (`/api/v1`, documented in the OpenAPI document):

```
GET    /v1/webhooks                     POST /v1/webhooks
GET    /v1/webhooks/events
GET    /v1/webhooks/{id}                PUT/PATCH/DELETE /v1/webhooks/{id}
POST   /v1/webhooks/{id}/rotate         POST /v1/webhooks/{id}/test
GET    /v1/webhooks/{id}/deliveries
GET    /v1/webhook-deliveries/{id}      POST /v1/webhook-deliveries/{id}/retry
```

The requested `webhooks:read` / `webhooks:write` scope is the panel's own
permission model: reads need the group's `access` on `setting/webhook`, writes
its `modify`. `test` and `retry` answer `202` (queued) — delivery always happens
in the worker — and `409` when the subscription is disabled or the delivery is
not `failed`.

### Settings (`oc_setting`, code `config`)

| Key | Default | Meaning |
|---|---|---|
| `config_webhook_status` | `1` | kill switch; `0` keeps queueing but sends nothing |
| `config_webhook_timeout` | `10` | total HTTP timeout per attempt (s) |
| `config_webhook_max_attempts` | `6` | attempts per delivery (first + 5 retries) |
| `config_webhook_disable_after` | `4` | consecutive failed deliveries → auto-disable |
| `config_webhook_log_retention_days` | `30` | journal retention (`0` = keep forever) |

They are saved with `updateSetting('config', …)` (never `editSetting`, which
wipes the other store settings). Journal cleanup runs daily
(`bin/dockercart_webhook_cleanup.php`, task `webhook_cleanup`) and also removes
rows whose subscription no longer exists.

### Tests

The webhook system is covered by a dependency-free suite (`make test`, no DB/network):

| Case file | Guards |
|---|---|
| `tools/tests/cases/webhook_events.php` | catalogue contract, wildcards, filter matching/normalization |
| `webhook_dispatcher.php` | fan-out, filters, thin/full payloads, swallowed DB errors, **no** nested transaction/HTTP |
| `webhook_delivery.php` | backoff table, failure classification, limit clamping |
| `webhook_ssrf.php` | URL syntax and IPv4/IPv6 blocking matrix, dev override |
| `webhook_order_payload.php` | full payload shape, no PII, snapshot language metadata |
| `webhook_secret.php` / `webhook_signature.php` | AES-GCM storage, HMAC contract |
| `webhook_ulid.php` | id format, uniqueness, time ordering |
| `webhook_model.php` | panel/API validation matrix, ciphertext never leaves the model |
| `webhook_localization.php` | `en-gb`/`ru-ua`/`uk-ua` key parity, placeholders, no English fallbacks |
| `webhook_api_routes.php` | routes↔OpenAPI parity, permissions, `$ref` resolution |
| `webhook_migration.php` | idempotent SQL, settings/scheduler wiring, additive-only |
| `webhook_emit_anchors.php` | emit before `COMMIT`, real-change guards, single stock journal writer |

### Implementation map

| Path | Role |
|---|---|
| `upload/system/library/dockercart/webhook/events.php` | event catalogue, wildcard + filter matching |
| `…/dispatcher.php` | outbox writer (`emit()`), request-level subscription cache |
| `…/delivery.php` | row format, backoff table, retry reset, test enqueue |
| `…/signature.php` | HMAC over `timestamp.body` |
| `…/sender.php` | curl POST, IP pinning, response cap |
| `…/ssrf.php` | URL and DNS/IP validation |
| `…/secret.php` | secret generation + AES-GCM storage helpers |
| `…/ulid.php` | `evt_…` event ids |
| `…/order_payload.php` | PII-free full order object |
| `upload/admin/model/setting/webhook.php` | subscriptions + journal + validation (shared by panel and API) |
| `upload/bin/dockercart_webhook_deliver.php` | delivery worker |
| `upload/bin/dockercart_webhook_cleanup.php` | journal retention |
| `docker/mysql/migrations/20261025_webhooks.sql` | tables, settings, scheduler tasks |

### Extending (roadmap events)

The catalogue in `webhook/events.php` already lists the planned events
(payments, shipments, products, returns, customers, transfers, dropship,
reviews, price, config) with `implemented => false`. Adding one:

1. flip `implemented` to `true` (and adjust `data_fields`/`filter_fields`);
2. call `(new \Dockercart\Webhook\Dispatcher($this->registry))->emit('<type>', $data, $options)`
   **inside** the transaction, right after the data write and before `COMMIT`,
   with the version id captured from `getLastId()` at that moment;
3. wrap any insert+update pair that currently has no transaction so the event
   and the data commit together;
4. document it in `docs/guide.md` and in the OpenAPI `WebhookEvent` example list.

---

## 19. Product Files (public downloads)

Attach any number of files to a product — PDF instructions, manuals, datasheets,
certificates — and show them in a public "Files" block on the product page.
Unlike the built-in OpenCart `download` (which is purchase-gated and only appears
in the customer account), these files are free for every visitor.

### Data model

| Table | Purpose |
|---|---|
| `oc_product_file` | one row per attachment: `storage_key`, `original_name`, `file_size`, `mime_type`, `sort_order`, `status`, `downloads_count`, `date_last_downloaded`, `date_added` |
| `oc_product_file_description` | localized title per `language_id` |

The physical payload lives **outside the webroot** in
`DIR_STORAGE . 'documents/product/'` under a random key
(`bin2hex(random_bytes(16)) . '.<ext>'`). It is never served by a direct URL —
`upload/catalog/controller/product/product_file.php` streams it with
`Content-Disposition: attachment` and `X-Content-Type-Options: nosniff`, and only
for an enabled file of an enabled product. A disabled file (or the file of a
disabled product) is not downloadable.

### Admin

The product form shows a **Files** field inside the **Media** panel
(`catalog/product_form.twig`):

* upload via AJAX (`catalog/product/fileUpload`) — the payload is written to
  storage immediately and the returned metadata is kept in hidden inputs; the DB
  row is written on save;
* remove via AJAX (`catalog/product/fileDelete`) — a persisted file is deleted by
  id (row + payload), a not-yet-saved upload only by storage key;
* title per language (`en-gb`/`ru-ua`/`uk-ua`), sort order and status.

Both AJAX actions reuse the `catalog/product` permission, so no new role route is
required. On save, `ModelCatalogProduct::addProduct()` / `editProduct()` delegate to
`ModelCatalogProductFile::saveFiles()`, which reconciles the submitted rows
(update kept, insert new, delete missing — payload included). Deleting a product
removes its files, and copying a product duplicates the payloads (a copy gets its
own storage key, so the two products never share a file).

Uploads use a **hard allowlist** (documents, raster images, archives and
drawings: PDF, DOC/DOCX, XLS/XLSX, PPT/PPTX, ODT/ODS/ODP, RTF, TXT, CSV,
JPG/PNG/GIF/WEBP/BMP/TIFF, ZIP/RAR/7Z, DWG/DXF, EPS/AI/PSD) — the global
`config_file_ext_allowed` / `config_file_mime_allowed` lists are intentionally
**not** used, so a permissive global setting cannot open a hole. On top of the
extension check the controller:

* detects the real content type with `finfo` (the client-sent mime is ignored)
  and rejects active content (`text/html`, `image/svg+xml`, JS, shell, executables, ...);
* rejects a `text/*` payload under a non-text extension (a renamed script);
* rejects any payload carrying a PHP tag or a shebang;
* keeps a dangerous-extension denylist as a second line of defence;
* respects `config_file_max_size`.

Product files are never served from the webroot and are always sent with
`Content-Disposition: attachment` + `X-Content-Type-Options: nosniff`, so even an
accepted document cannot execute in the browser.

### Storefront

`ModelCatalogProductFile::getFiles()` returns enabled files titled in the current
language (falling back to the original filename). The product controller exposes
them as `product_files` with a download `href`; `product.twig` renders a "Files"
tab entry and the list section. `product/product_file/download` increments
`downloads_count` and stamps `date_last_downloaded`.

### Tests

`tools/tests/cases/product_file_model.php` guards the storage-key format, the
save/reconcile contract, payload deletion (including path-traversal safety) and
the copy behaviour.
