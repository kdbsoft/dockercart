# DockerCart

> **OpenCart evolved. A self-hosted e-commerce platform that's ready to deploy in minutes.**

DockerCart is a production-ready e-commerce platform built on top of OpenCart 3, shipped as a complete Docker stack. It is not a vanilla OpenCart install — it is an opinionated evolution of OpenCart: hundreds of bug fixes applied, security holes patched, performance tuned, and an ecosystem of first-party modules included. Everything works out of the box.

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](LICENSE.md)
[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql)](https://www.mysql.com/)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker)](https://docs.docker.com/compose/)

---

## Why DockerCart?

OpenCart is a solid foundation, but running it in production has historically meant dealing with a long list of known bugs, incompatible extensions, fragile file-based configuration, and manual server setup. DockerCart solves all of that:

Note on frontend stacks: OpenCart's legacy storefront is built on jQuery, Bootstrap and Font Awesome, while DockerCart ships a modern frontend stack using JavaScript (ES6+), Tailwind CSS and the Lucide icon font.

| OpenCart baseline | DockerCart |
|---|---|
| Manual server setup | One-command Docker deployment |
| Web installer (`/install`) required | Install-less bootstrap from `.env` + DB seed |
| Known bugs in core | Hundreds of fixes applied to the base |
| Configuration in PHP files | Environment variables via `.env` |
| No built-in caching layer | Memcached + OPcache pre-configured |
| No full-text search | Manticore Search engine included |
| No containerization | Docker Compose stack, hot-reload dev workflow |
| Scattered extension ecosystem | First-party modules, tested and integrated |
| No deployment tooling | Makefile, health checks, backup/restore targets |

---

## Stack

| Component | Technology |
|---|---|
| Application | PHP 8.4 + Apache + Nginx |
| Database | MariaDB 11.8 |
| Object cache | Memcached 1.6 |
| Full-text search | Manticore Search |
| Reverse proxy | Traefik v3 *(optional)* |
| SSL | Let's Encrypt / self-signed |
| Frontend | OpenCart (legacy): jQuery, Bootstrap, Font Awesome · DockerCart: JavaScript (ES6+), Tailwind CSS, Lucide icon font |

---

## Performance

DockerCart is engineered for industrial-grade speed and low latency. It combines modern runtime optimizations and proven infrastructure components to deliver a very fast, production-ready storefront:

- Industrial-grade performance: tuned for low-latency, high-throughput workloads.
- Modern PHP runtime: built for PHP 8.4 with OPcache and typical production-level PHP optimizations enabled.
- Caching: Memcached and internal caching layers significantly reduce database load and response times.
- Fast search: Manticore Search provides high-performance full-text queries and relevance ranking.
- Database and query optimizations: schema and index improvements are included (see docker/mysql/migrations and sql-optimization scripts).
- Hot-reload dev workflow: edits to PHP/Twig/JS are applied immediately without container restarts, keeping development fast.

These optimizations make DockerCart suitable for demanding production environments where speed and scalability matter.


## Quick Start

**Requirements:** Docker 24+ and Docker Compose v2.

```bash
git clone https://github.com/your-org/dockercart.git
cd dockercart
cp .env.example .env
```

DockerCart does **not** use the legacy OpenCart web installer. On first startup, database bootstrap happens automatically from `docker/mysql/init.sql`, and `config.php` / `admin/config.php` read runtime values from `.env`.

### `robots.txt` on first start (important)

DockerCart ships a **safe first-start policy** for crawlers:

- `upload/robots.txt` is restrictive by default (`Disallow: /`)
- if `robots.txt` is missing, `docker/entrypoint.sh` generates the same restrictive version on first start

To enable indexing after launch:

1. Copy `upload/robots-dist.txt` to `upload/robots.txt`
2. Update `Sitemap:` with your real DockerCart domain
3. Tune rules for your SEO strategy

This step is easy to forget, so please include it in your post-deploy checklist.

Default admin credentials (customize in `.env` before first launch):

- `ADMIN_USERNAME=admin`
- `ADMIN_PASSWORD=admin123`
- `ADMIN_EMAIL=admin@example.com`

Then choose a deployment mode:

---

### Mode 1 — Standalone *(no Traefik, simplest)*

Use `docker-compose.standalone.yml`. By default it binds host port **80**.

```bash
# via Make
make dev-standalone

# via Docker Compose
docker compose -f docker-compose.standalone.yml up -d --build
```

Store: **`http://your-domain`** · Admin: **`http://your-domain/admin`**

Set these values in `.env`:

- `DOCKERCART_DOMAIN=your-domain`
- `DOCKERCART_URL=http://your-domain`
- `DOCKERCART_HTTP_PORT=80`

---

### Mode 2 — Traefik *(production / local dev / staging)*

Use `docker-compose.yml` with an external Traefik network.

```bash
# via Make
make dev

# via Docker Compose
docker compose up -d --build

# via script
./start.sh
```

Store: **`http://your-domain`** (set `DOCKERCART_DOMAIN` in `.env`)

---

### Mode 3 — Self-signed SSL *(HTTPS testing)*

```bash
# via Make
make dev-ssl

# via script
./start.sh --ssl
```

Access: **`https://your-domain`** (browser warning is expected for self-signed certs)

---

### Mode 4 — Let's Encrypt *(production SSL with automatic renewal)*

Requires a real public domain with ports 80/443 open.

```bash
# Edit .env first:
SSL_DOMAIN=shop.example.com
SSL_EMAIL=admin@example.com

# via Make
make prod

# via script
./start.sh --letsencrypt
```

---

### Mode 5 — Standalone + Let's Encrypt *(no Traefik, production SSL)*

Requires a real public domain with ports 80/443 open.

```bash
# Edit .env first:
SSL_DOMAIN=shop.example.com
SSL_EMAIL=admin@example.com
DOCKERCART_DOMAIN=shop.example.com
DOCKERCART_URL=https://shop.example.com
DOCKERCART_HTTPS_URL=https://shop.example.com
DOCKERCART_SSL_ENABLED=true

# via Make
make prod-standalone

# compatible alias
STANDALONE=1 make letsencrypt
```

Access: **`https://shop.example.com`**

- Runs without Traefik (standalone compose + Nginx)
- Obtains certs automatically via certbot (HTTP-01 challenge)
- Keeps a renewal loop in `certbot` container (checks every 24h by default, renews only near expiry)
- Reuses existing certificate/key from persistent storage to avoid unnecessary re-issuance
- Uses `DOCKERCART_HTTPS_PORT` (default `443`) for HTTPS binding

---

### Mode 6 — Optional FTP *(images directory only)*

FTP is disabled by default and starts only when explicitly requested.

```bash
# via Make (short command)
make ftp

# with Let's Encrypt (single command)
make letsencrypt-ftp
```

FTP user is chrooted to existing host directory `./upload/image` only and has extended privileges in this directory: upload, overwrite, delete, and rename image files.
Inside container this directory is mounted as `/home/vsftpd/${FTP_USER}`.

Configure in `.env` if needed:

- `FTP_PORT=21`
- `FTP_USER=images`
- `FTP_PASS=change_me_please`
- `FTP_WRITE_ENABLE=YES`
- `FTP_ALLOW_WRITEABLE_CHROOT=YES`
- `FTP_LOCAL_UMASK=000`
- `FTP_FILE_OPEN_MODE=0777`
- `FTP_PASV_ADDRESS=your-server-ip-or-domain` *(must be reachable by FTP client; do not use `127.0.0.1`)*
- `FTP_PASV_ADDR_RESOLVE=YES`
- `FTP_PASV_MIN_PORT=21100`
- `FTP_PASV_MAX_PORT=21110`

---

> **Traefik is optional.** Modes 1 and 5 do not require it.

---

## Makefile Reference

```bash
make help          # List all targets

make dev-standalone    # Start — direct host port (default: 80), no Traefik
make prod-standalone # Start — standalone + Let's Encrypt HTTPS (no Traefik)
make dev            # Start — Traefik mode (HTTP by default)
make dev-ssl           # Start — Traefik + self-signed HTTPS (local testing)
make prod   # Start — Traefik + Let's Encrypt (production)
make ftp           # Start — stack + optional FTP profile (access only to ./upload/image)
make prod-ftp # Start — Let's Encrypt + FTP profile together
make down          # Stop containers
make restart       # Restart containers
make logs          # Show last 100 log lines
make logs-follow   # Follow logs
make shell         # bash into the app container
make mariadb       # Open MariaDB CLI
make backup        # Dump DB to ./backups/
make restore       # Restore from latest dump in ./backups/
make clean         # Stop + remove all volumes (destructive)
```

---

## Modules

### Free — included, no license key required

| Module | Description |
|---|---|
| **Checkout** | One-page checkout flow improvements and UX fixes (GPL, free) |
| **Theme** | DockerCart theme with customization settings |
| **Full-text Search** | Manticore Search-powered relevance search across the catalog |
| **Blog** | Full blog system: categories, authors, comments, SEO-ready posts |
| **Newsletter** | Subscription form + mailing list management |
| **FAQ** | Structured FAQ pages with accordion layout |
| **Responsive Banners** | Separate portrait/landscape images via `<picture>` |
| **Shop Features** | Features icons section |
| **Universal Shipping** | Flexible, multilingual shipping module with geo-zone, weight- and price-based rules; create unlimited shipping methods. (Free) |
| **Universal Payment** | Flexible, free payment module that lets you create multiple internal payment methods (geo-zone, order total rules), exposes them as a grouped `quote[]` payment extension and is fully compatible with DockerCart Checkout. (Free) |

### Premium — require a license key from [dockercart.net](https://dockercart.net)

| Module | Description |
|---|---|
| **One-Click Checkout** | Streamlined checkout that reduces cart abandonment |
| **Advanced Filter** | Ajax product filtering by attributes, price, and custom options |
| **Multicurrency** | Real-time currency switching with automatic rate feeds |
| **SEO Generator** | Automatic SEO URLs and meta tags for all products |
| **Import/Export Excel** | Bulk product management via `.xlsx` files |
| **Import YML** | Import catalogs in Yandex Market Language (YML) format |
| **Google Translation** | Integration with Google Translate for multilingual storefronts |
| **Redirects** | 301/302 redirect management with CSV import |
| **Export YML** | Export catalog in Yandex Market Language format |
| **Google Base** | Product feed for Google Merchant Center |
| **Sitemap** | Auto-generated XML sitemap for crawlers |

---

## Directory Structure

```
dockercart/
├── docker/                     Docker service configs
│   ├── apache.conf             Apache VirtualHost
│   ├── php.ini                 PHP runtime config
│   ├── entrypoint.sh           Container startup script
│   ├── mysql/
│   │   ├── init.sql            Schema + seed data
│   │   └── migrations/         Incremental DB migration scripts
│   └── manticore/              Manticore Search config
├── storage/                    Runtime files — outside webroot
│   └── logs/                   Application error logs
├── upload/                     Application source (mounted as /var/www/html)
│   ├── admin/                  Admin panel
│   └── catalog/                Storefront
├── .env.example                Environment variable template
├── docker-compose.yml          Default stack (Traefik or standalone via override)
├── docker-compose.standalone.yml  Standalone mode (no Traefik) stack
├── Dockerfile                  Application image
└── Makefile                    Shortcut commands
```

---

## Configuration

All runtime settings live in `.env`. Copy from `.env.example` and edit:

```dotenv
# Domain (used by Traefik mode)
DOCKERCART_DOMAIN=dockercart.local
DOCKERCART_URL=http://dockercart.local
DOCKERCART_HTTPS_URL=http://dockercart.local
DOCKERCART_SSL_ENABLED=false

# Standalone port (used by docker-compose.standalone.yml)
DOCKERCART_HTTP_PORT=80

# Standalone HTTPS port (used by docker-compose.standalone.letsencrypt.yml)
DOCKERCART_HTTPS_PORT=443

# Database
DB_HOSTNAME=mariadb
DB_USERNAME=dockercart
DB_PASSWORD=dockercart_password

# PHP
PHP_MEMORY_LIMIT=256M
PHP_UPLOAD_MAX_FILESIZE=100M

# SSL / Let's Encrypt (production)
SSL_DOMAIN=example.com
SSL_EMAIL=admin@example.com

# Persist LE state between rebuilds/deploys (important for rate-limit safety)
LETSENCRYPT_DATA_DIR=./docker/letsencrypt
LETSENCRYPT_WEBROOT_DIR=./docker/letsencrypt/www

# Standalone certbot renewal check interval
CERTBOT_RENEW_INTERVAL=24h
```

See [`.env.example`](.env.example) for a complete reference.

---

## Documentation

| Document | Description |
| --- | --- |
| [SSL.md](SSL.md) | Unified SSL/HTTPS guide covering HTTP, self-signed SSL, Let's Encrypt, standalone mode, and troubleshooting |
| [INSTALL.md](INSTALL.md) | Detailed installation reference |
| [DEPLOYMENT.md](DEPLOYMENT.md) | Deployment architecture and CI/CD notes |
| [SECURITY.md](SECURITY.md) | Security policy and vulnerability reporting |

---

## Contributing

1. Fork the repository and create a feature branch
2. Write focused commits using Conventional Commits, for example `feat: add cache invalidation` or `fix: handle standalone SSL redirect`
3. Test your changes with `make standalone`
4. Submit a pull request describing the change and its motivation

---

## Releases

- Releases are cut automatically from `main` via `semantic-release`
- The release source of truth is the Git tag (`vX.Y.Z`), with `CHANGELOG.md`, `VERSION`, `package.json`, and `package-lock.json` synchronized during release
- Preview the next calculated release locally with `npm run release:dry-run`

---

## License

DockerCart is released under the **GNU General Public License v3.0 (GPLv3)**.

DockerCart is based on [OpenCart](https://github.com/opencart/opencart), which is also GPL-licensed. All original attributions are preserved. See [LICENSE.md](LICENSE.md) for the full license text.

---

**Official site:** [dockercart.net](https://dockercart.net) · **Demo:** [demo.dockercart.net](https://demo.dockercart.net) · **Support:** `support@dockercart.net`

