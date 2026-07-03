# DockerCart

> A production-hardened e-commerce platform — deploy anywhere with Docker Compose.

<p align="center">
  <a href="https://demo.dockercart.net"><img src="https://img.shields.io/badge/Live%20Demo-demo.dockercart.net-6366f1?style=flat-square&logo=google-chrome&logoColor=white" alt="Live Demo"></a>
  &nbsp;
  <a href="https://dockercart.net"><img src="https://img.shields.io/badge/dockercart.net-6366f1?style=flat-square&logo=globe&logoColor=white" alt="DockerCart"></a>
</p>

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](LICENSE.md)
[![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php)](https://www.php.net/)
[![MariaDB](https://img.shields.io/badge/MariaDB-11.4-003545?logo=mariadb)](https://mariadb.org/)
[![Redis](https://img.shields.io/badge/Redis-7.0-DC3821?logo=redis)](https://redis.io/)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker)](https://docs.docker.com/compose/)

---

## Quick Start

```bash
git clone https://github.com/your-org/dockercart.git
cd dockercart
cp .env.example .env
make up
```

First boot auto-generates `config.php`, seeds the database, applies migrations, and indexes the search — no web installer, no manual steps.

Admin panel: `http://dockercart.local/admin`  
Default credentials (set in `.env` before first run): `admin` / `admin123`

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         nginx:alpine                            │
│  Reverse proxy — TLS termination, static assets, gzip, cache   │
└──────────────┬──────────────────────────────────────┬───────────┘
               │                                      │
    ┌──────────▼──────────┐              ┌────────────▼──────────┐
    │  apache (PHP 8.5)    │              │    scheduler          │
    │  Application server  │              │  daemon — cron tasks  │
    │  ┌─────────────────┐ │              │  php cron_dispatcher  │
    │  │  entrypoint.sh   │ │              └────────────┬──────────┘
    │  │  • config gen    │ │                           │
    │  │  • DB seed       │ │                           │
    │  │  • OCMOD refresh │ │                           │
    │  │  • permissions   │ │                           │
    │  └─────────────────┘ │                           │
    └──────────┬──────────┘                            │
               │                                       │
    ┌──────────▼──────────┐              ┌────────────▼──────────┐
    │       mariadb:11.8   │              │     manticore:15      │
    │  Primary database    │◄─────────────┤  Full-text search     │
    │  init.sql + auto-    │    reads     │  Reindex on boot      │
    │  migrations          │    via SQL   └───────────────────────┘
    └──────────────────────┘

    ┌──────────────────────┐
    │   redis:7-alpine     │
    │  Primary cache +     │
    │  session store       │
    └──────────────────────┘

    ┌──────────────────────┐
    │   ftp (optional)     │
    │  vsftpd — chrooted   │
    │  to ./upload/image   │
    └──────────────────────┘
```

All containers communicate over a shared bridge network (`dockercart-network`). Nginx is the only entry point — Apache has no exposed ports.

---

## Stack

| Component | Technology |
|---|---|
| Application | PHP 8.5 + Apache 2.4 |
| Reverse proxy | Nginx (alpine) |
| Database | MariaDB 11.8 |
| Cache & sessions | Redis 7 |
| Full-text search | Manticore Search 15 |
| Reverse proxy (alt) | Traefik v3 *(optional)* |
| SSL | Let's Encrypt (certbot) or self-signed |
| Frontend | ES6+ JavaScript, Tailwind CSS 3, Lucide icons |

---

## Features

### Storefront

- **One-page checkout** — streamlined flow with shipping, payment, and order review on a single page
- **Modern responsive theme** — Tailwind CSS 3 + Lucide icons, zero jQuery, vanilla ES6+
- **AJAX product filtering** — filter by attributes, price range, and custom options without page reload
- **Real-time multicurrency** — automatic rate feeds with seamless currency switching
- **SEO generator** — auto-generated SEO URLs, meta titles, and descriptions for all products
- **One-click checkout** — reduced-friction checkout to minimize cart abandonment

### Content

- **Blog** — full system with categories, authors, comments, and SEO-ready posts
- **Newsletter** — subscription form with mailing list management
- **FAQ** — structured accordion pages, easily manageable from admin
- **Responsive banners** — separate portrait and landscape images via `<picture>` art direction

### Search

- **Manticore-powered full-text search** — indexes product names, descriptions, SKUs, and attributes for fast, relevant results. Index built automatically on first boot; incremental updates via admin or scheduler.

### Shipping & Payment

- **Universal Shipping** — create unlimited shipping methods with geo-zone, weight, and price rules. Multi-language support.
- **Universal Payment** — define multiple internal payment methods using geo-zone and order total rules; exposed as a grouped `quote[]` extension
- **One-click checkout** — express checkout option with reduced friction

### Catalog Management

- **Bulk import/export** — manage products and categories via `.xlsx` files
- **YML import/export** — full Yandex Market Language support for catalog exchange
- **Google Merchant Center feed** — auto-generated product feed
- **XML sitemap** — auto-generated sitemap for search crawlers
- **Image auto-optimization** — uploaded images exceeding `IMAGE_MAX_DIMENSION` are proportionally resized and optimally recompressed (JPEG 95, PNG level 9, WebP 95)

### Admin

- **Redirect manager** — create and manage 301/302 redirects, import via CSV
- **Google Translation** — connect Google Translate for multi-language storefronts
- **Plugin architecture** — extend via OCMOD system, event hooks, and the full module lifecycle

### Caching & Performance

- **Redis** — primary object cache and session store with configurable maxmemory and LRU eviction
- **OPcache** — enabled with production-typical PHP settings
- **Nginx** — static asset caching, gzip compression, FastCGI cache layer
- **MariaDB** — three InnoDB profiles (s/m/l) selectable via `MARIADB_CONFIG_SIZE`

---

## Deployment

All modes are invoked via `make` or `./start.sh`. Container names are prefixed `dockercart_`.

### Standalone (default, no Traefik)

| Mode | Command | Access |
|---|---|---|
| HTTP | `make up` / `make dev` | `http://your-domain` |
| HTTPS (self-signed) | `make ssl` / `make dev-ssl` | `https://your-domain` |
| HTTPS (Let's Encrypt) | `make le` / `make prod` | `https://shop.example.com` |
| LE + FTP | `make le-ftp` / `make prod-ftp` | HTTPS + FTP on port 21 |

### Traefik (external reverse proxy)

| Mode | Command |
|---|---|
| HTTP | `make traefik` |
| HTTPS (self-signed) | `make traefik-ssl` |
| HTTPS (Let's Encrypt) | `make traefik-le` |

Requires an external `traefik` Docker network.

### FTP (optional)

Attach FTP to any running mode:

```bash
make ftp
```

FTP user is chrooted to `./upload/image` with extended privileges. Configure in `.env`.

---

## Developer Guide

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
│   ├── manticore/              Manticore Search config
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

### Makefile Reference

```bash
make up/down/restart      # Container lifecycle
make logs/logs-follow     # View logs
make shell                # Bash into app container
make mariadb              # MariaDB CLI
make backup/restore       # Database dumps
make clean                # Destructive: remove all volumes
make migrate              # Apply SQL migrations
```

### Configuration

All runtime settings are in `.env` (copy from `.env.example`). `config.php` and `admin/config.php` are **generated at container start** by `entrypoint.sh` — never edit them directly.

Key variables:

```dotenv
DOCKERCART_URL=http://dockercart.local    # Store URL
DB_PASSWORD=...                           # Database password
ADMIN_USERNAME=admin                      # Admin credentials
ADMIN_PASSWORD=admin123
CACHE_ENGINE=redis                        # redis (primary) or file
REDIS_MAXMEMORY=256mb
PHP_MEMORY_LIMIT=256M
```

For Let's Encrypt production:

```dotenv
SSL_DOMAIN=shop.example.com
SSL_EMAIL=admin@example.com
LETSENCRYPT_ENABLED=true
LETSENCRYPT_DATA_DIR=./docker/letsencrypt
```

### Database Migrations

- Location: `docker/mysql/migrations/`
- Naming: `YYYYMMDD_short_description.sql`
- Always idempotent (`CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`)
- Apply: `make migrate` (runs against running MariaDB container)
- Regenerate base schema: `make dump-init` (dumps current DB state)

### Scheduler (Cron)

The scheduler daemon (`upload/bin/dockercart_scheduler.php`) polls the `oc_dockercart_scheduler_task` table and dispatches workers on schedule. There are no hardcoded handler classes — modules register tasks at install time via `DockercartScheduler`:

```php
$this->load->library('dockercart/scheduler');
$this->dockercart_scheduler->registerTask(
    'novapost_sync',
    'NovaPost Sync',
    'php /var/www/html/bin/novapost-sync.php',
    '0 2 * * *',
    true
);
```

Scheduler targets in the Makefile:

```bash
make scheduler-logs       # Follow scheduler logs
make scheduler-restart    # Restart scheduler container
make scheduler-reload     # SIGHUP — reload code without restart
make scheduler-status     # Check if running
```

### Static Analysis & Lint

```bash
# PHP syntax check
find upload -type f -name "*.php" ! -path 'storage/vendor/*' -print0 | xargs -0 -P4 php -l -n

# PHPStan (level 1)
./storage/vendor/bin/phpstan analyze -a ./storage/vendor/autoload.php --no-progress

# PHP-CS-Fixer (indentation: tabs)
./storage/vendor/bin/php-cs-fixer fix --dry-run --diff
```

### MVC Conventions

The codebase follows MVC patterns inherited from OpenCart 3:

| Layer | Catalog (frontend) | Admin |
|---|---|---|
| Controller | `catalog/controller/{section}/{name}.php` | `admin/controller/extension/module/{name}.php` |
| Model | `catalog/model/{section}/{name}.php` | `admin/model/extension/module/{name}.php` |
| View | `catalog/view/theme/dockercart/template/{section}/{name}.twig` | `admin/view/template/extension/module/{name}.twig` |
| Language | `catalog/language/en-gb/{section}/{name}.php` | `admin/language/en-gb/extension/module/{name}.php` |

Language files must be kept in sync across all locales (`en-gb`, `ru-ua`, `uk-ua`, etc.).

### Release Workflow

Releases are automated from `main` via `semantic-release`. Commit messages follow Conventional Commits:

```
feat: add product label field
fix: resolve cache invalidation on price update
feat!: breaking API change
```

Preview next version:

```bash
npm run release:dry-run
```

The release tag (`vX.Y.Z`) is the source of truth — `CHANGELOG.md`, `VERSION`, and `package.json` are synced during release.

---

## robots.txt

`robots.txt` is auto-generated on every container start — like `config.php`. The sitemap URL is populated from your `DOCKERCART_URL` (or `DOCKERCART_HTTPS_URL` when SSL is enabled).

Crawling rules are defined in `docker/entrypoint.sh` (`ensure_robots_txt`). To customize, edit the heredoc there and restart the container.

---

## Contributing

1. Fork the repository and create a feature branch
2. Write focused commits following [Conventional Commits](https://www.conventionalcommits.org/)
3. Test with `make up`
4. Submit a pull request

---

## License

DockerCart is released under **GNU General Public License v3.0 (GPLv3)**.

The project originated from a fork of [OpenCart](https://github.com/opencart/opencart) (also GPL-licensed) but has since evolved into an independent platform with its own architecture, module ecosystem, and compatibility boundary. All original attributions are preserved. See [LICENSE.md](LICENSE.md).
