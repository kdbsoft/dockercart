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

The default deployment. Nginx binds to `DOCKERCART_HTTP_PORT` (80) and optionally `DOCKERCART_HTTPS_PORT` (443). `start.sh` derives `DOCKERCART_URL` from `DOCKERCART_DOMAIN` + the HTTP port automatically, so a non-default port (e.g. `8080`) just works — internal links, `config_url`, `robots.txt` and `config.php` all include the port. On a clean install the default port is 80 (no prompt). To use a non-default port, set `DOCKERCART_HTTP_PORT` in `.env` and run `make restart`; `start.sh` re-derives `DOCKERCART_URL` so everything stays consistent.

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

- **Clean repo required (with one exception):** modified tracked files outside `upload/` block the update. Commit or stash them first, or use `ALLOW_DIRTY=1 make update` to skip the check. The GUI updater (`admin/cli/dockercart_update.php`) copies `upload/` directly into the bind mount without advancing git HEAD, which leaves those paths "dirty" — `make update` automatically reconciles (resets) exactly those GUI-managed paths before pulling, so **running the GUI updater and then `make update` works without manual cleanup**.
- **Detached HEAD:** not supported — you must be on a branch.
- **Diverged branches:** if local and remote have diverged, manual intervention is required.

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
| `shippingMethodTitle($order)` | universal `name` + `delivery_time` (same format as checkout quotes); `dockercart_novapost.{branch\|locker\|courier}` → `delivery_branch/delivery_locker/delivery_courier` language keys | stored `shipping_method` |
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
