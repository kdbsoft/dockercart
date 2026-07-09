# 🛒 DockerCart

> ### ⚡ Zero to production store in 60 seconds

<p align="center">
  <img src="upload/image/dockercart_preview.png" alt="DockerCart Preview" width="800">
</p>

<p align="center">
  <a href="https://dockercart.net"><img src="https://img.shields.io/badge/dockercart.net-6366f1?style=flat-square&logo=globe&logoColor=white" alt="DockerCart"></a>
  &nbsp;
  <a href="https://dockercart.net/capabilities"><img src="https://img.shields.io/badge/Capabilities-6366f1?style=flat-square&logo=bookstack&logoColor=white" alt="Capabilities"></a>
  &nbsp;
  <a href="https://dockercart.net/changelog"><img src="https://img.shields.io/badge/Changelog-6366f1?style=flat-square&logo=git&logoColor=white" alt="Changelog"></a>
  &nbsp;
  <a href="https://demo.dockercart.net"><img src="https://img.shields.io/badge/Live%20Demo-6366f1?style=flat-square&logo=google-chrome&logoColor=white" alt="Live Demo"></a>
  &nbsp;
  <a href="https://github.com/mathflow-bit/dockercart/issues"><img src="https://img.shields.io/badge/Issues-6366f1?style=flat-square&logo=github&logoColor=white" alt="Issues"></a>
</p>

<p align="center">
  <a href="LICENSE.md"><img src="https://img.shields.io/badge/License-GPLv3-blue.svg?style=flat-square" alt="License: GPL v3"></a>
  &nbsp;
  <a href="https://www.php.net/"><img src="https://img.shields.io/badge/PHP-8.5-777BB4?style=flat-square&logo=php" alt="PHP"></a>
  &nbsp;
  <a href="https://mariadb.org/"><img src="https://img.shields.io/badge/MariaDB-11.4-003545?style=flat-square&logo=mariadb" alt="MariaDB"></a>
  &nbsp;
  <a href="https://redis.io/"><img src="https://img.shields.io/badge/Redis-7.0-DC3821?style=flat-square&logo=redis" alt="Redis"></a>
  &nbsp;
  <a href="https://docs.docker.com/compose/"><img src="https://img.shields.io/badge/Docker-Compose-2496ED?style=flat-square&logo=docker" alt="Docker"></a>
</p>

---

[DockerCart](https://dockercart.net) is a **[full-stack e-commerce platform](https://dockercart.net/capabilities)** wrapped in a Docker infrastructure. One command spins up the complete stack — Nginx reverse proxy, PHP 8.5 application server, MariaDB database, Redis cache, Manticore Search full-text engine, and a scheduler daemon — pre-configured and ready to handle real traffic.

No web installer. No `/install` directory. No manual config. Just `make up` and a [production-grade store](https://demo.dockercart.net) is live.

**Built for developers** who are tired of wrestling with fragile setups and want to focus on building features, not fighting infrastructure. See the [full capabilities list](https://dockercart.net/capabilities) or [try the live demo](https://demo.dockercart.net).

---

## 🚀 Quick Start

```bash
git clone https://github.com/mathflow-bit/dockercart.git
cd dockercart
cp .env.example .env
make up
```

Done. First boot handles **everything**:

- ✅ Generates `config.php` from environment variables
- ✅ Seeds the database and applies migrations
- ✅ Builds the full-text search index
- ✅ Sets correct file permissions
- ✅ No web installer — no human in the loop

**Admin panel:** `http://dockercart.local/admin`  
**Default credentials** (change in `.env`): `admin` / `admin123`

---

## 🏗️ Architecture

Six containers. One network. Zero exposed ports (except Nginx).

```
                              🌐  Internet
                                   │
                         ┌─────────▼─────────┐
                         │   nginx:alpine      │
                         │  TLS · gzip · cache │
                         └────┬───────────┬───┘
                              │           │
                  ┌───────────▼───┐  ┌────▼──────────┐
                  │  🐘 Apache    │  │  ⏰ Scheduler  │
                  │  PHP 8.5      │  │  Cron daemon   │
                  └───────┬───────┘  └───────────────┘
                          │
              ┌───────────┼───────────┐
              │           │           │
    ┌─────────▼──┐  ┌────▼────┐  ┌──▼──────────┐
    │ 🐬 MariaDB │  │ 🔴 Redis│  │ 🦀 Manticore │
    │  DB  11    │  │ Cache   │  │  Search 15   │
    └────────────┘  └─────────┘  └──────────────┘

    📁 Optional: FTP (vsftpd — chrooted to ./upload/image)
```

**How traffic flows:** Nginx is the sole entry point — it handles TLS termination, gzip compression, and static asset caching. Apache runs the PHP application behind it with no exposed ports. MariaDB stores your data, Redis handles caching and sessions, Manticore powers full-text search. The scheduler daemon runs background tasks (cron, syncs, feeds).

Everything communicates over a shared `dockercart-network` bridge. See the [full architecture docs](docs/guide.md#2-architecture) for directory layout and storage paths.

---

## 🖥️ Tech Stack

| Layer | Technology | Why |
|---|---|---|
| 🐘 Application | [PHP 8.5](https://www.php.net/) + [Apache 2.4](https://httpd.apache.org/) | Battle-tested PHP runtime |
| 🔄 Reverse proxy | [Nginx](https://nginx.org/) (alpine) | Blazing fast, tiny footprint |
| 🐬 Database | [MariaDB 11](https://mariadb.org/) | MySQL-compatible, rock solid |
| 🔴 Cache & sessions | [Redis 7](https://redis.io/) | Sub-millisecond reads |
| 🔍 Full-text search | [Manticore Search 15](https://manticoresearch.com/) | SQL-compatible, fast indexing |
| 🛡️ Reverse proxy (alt) | [Traefik v3](https://traefik.io/) | *Optional* — for existing infra |
| 🔒 SSL | [Let's Encrypt](https://letsencrypt.org/) / self-signed | Auto-renewal via certbot |
| 🎨 Frontend | ES6+ · [Tailwind CSS 3](https://tailwindcss.com/) · [Lucide](https://lucide.dev/) | Modern, zero jQuery |

---

## 📦 Deployment Modes

All modes invoked via `make`. Container names prefixed `dockercart_`. Full details in the [deployment guide](docs/guide.md#4-deployment).

### 🏠 Standalone (default)

| Mode | Command | What you get |
|---|---|---|
| 🌐 HTTP | `make up` | Plain HTTP on port 80 |
| 🔐 HTTPS (self-signed) | `make ssl` | Quick HTTPS for dev/staging |
| 🔒 HTTPS (Let's Encrypt) | `make le` | Production SSL with auto-renew |

### 🔀 Traefik (external reverse proxy)

| Mode | Command |
|---|---|
| 🌐 HTTP | `make traefik` |
| 🔐 HTTPS (self-signed) | `make traefik-ssl` |
| 🔒 HTTPS (Let's Encrypt) | `make traefik-le` |

### 📁 FTP (optional add-on)

```bash
make ftp   # Attach to any running mode — chrooted to ./upload/image
```

---

## ⚙️ Configuration

All settings live in **`.env`** (copy from `.env.example`).  
Config files are **generated at container start** — never edit them manually.

| Variable | Purpose |
|---|---|
| `DOCKERCART_URL` | 🌐 Store base URL |
| `DB_*` | 🐬 Database credentials |
| `ADMIN_USERNAME` / `ADMIN_PASSWORD` | 👤 Default admin account |
| `CACHE_ENGINE` | 🔴 `redis` (default) or `file` |
| `REDIS_MAXMEMORY` | 💾 Redis memory limit |
| `PHP_MEMORY_LIMIT` | 🧠 PHP memory limit |
| `MARIADB_CONFIG_SIZE` | ⚡ InnoDB profile: `s` · `m` · `l` |

Full reference → [`docs/guide.md`](docs/guide.md#3-configuration)

---

## 📚 Resources

| | Resource | Link |
|---|---|---|
| 📖 | Developer guide | [`docs/guide.md`](docs/guide.md) |
| ✨ | Capabilities | [dockercart.net/capabilities](https://dockercart.net/capabilities) |
| 📋 | Changelog | [dockercart.net/changelog](https://dockercart.net/changelog) |
| 🖥️ | Live demo | [demo.dockercart.net](https://demo.dockercart.net) |
| 🛒 | Add-ons store | [store.dockercart.net](https://store.dockercart.net) |
| 🔄 | Core updates (`make update`) | [`docs/guide.md`](docs/guide.md#9-core-updates) |
| 🐛 | Issues | [GitHub Issues](https://github.com/mathflow-bit/dockercart/issues) |
| 🔒 | Security policy | [`SECURITY.md`](SECURITY.md) |

---

## 🤝 Contributing

1. 🍴 Fork & create a feature branch
2. 📝 Write focused commits following [Conventional Commits](https://www.conventionalcommits.org/)
3. 🧪 Test with `make up`
4. 🚀 Submit a pull request

---

## 📄 License

DockerCart is released under **[GNU General Public License v3.0 (GPLv3)](https://www.gnu.org/licenses/gpl-3.0.html)**.

The project originated from a [fork of OpenCart](https://github.com/opencart/opencart) (also GPL-licensed) and has since evolved into an independent platform with its own architecture. All original attributions are preserved. See [`LICENSE.md`](LICENSE.md).
