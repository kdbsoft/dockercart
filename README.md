# DockerCart

![DockerCart Preview](upload/image/dockercart_preview.png)

DockerCart is a full-stack e-commerce platform built on a Docker infrastructure. A single command brings up the complete stack — Nginx reverse proxy, PHP 8.5 application server, MariaDB database, Redis cache, Manticore Search full-text engine, and a scheduler daemon — pre-configured and ready to serve production traffic.

There is no web installer and no `/install` directory. Run `make start` (or `make up`) and a production-grade store is live.

Documentation and resources are available at [dockercart.net](https://dockercart.net), including the [capabilities list](https://dockercart.net/capabilities) and a [live demo](https://demo.dockercart.net).

---

## Technology

| Layer | Technology |
|---|---|
| Application | PHP 8.5 + Apache 2.4 |
| Reverse proxy | Nginx (alpine) |
| Database | MariaDB 11 |
| Cache & sessions | Redis 7 |
| Full-text search | Manticore Search 15 |
| Reverse proxy (alternative) | Traefik v3 (optional, for existing infrastructure) |
| SSL | Let's Encrypt / self-signed (auto-renewal via certbot) |
| Frontend | ES6+ · Tailwind CSS 3 · Lucide |

---

## Quick Start

```bash
git clone https://github.com/kdbsoft/dockercart.git
cd dockercart
make start
```

On first run, `make start` prompts for the critical settings — store domain, timezone, database and admin passwords (press Enter to generate random ones), admin account, and whether to install demo data — and writes them to `.env`. Subsequent runs only prompt for missing keys. Use `make up` to skip the prompt and start in standalone HTTP mode.

First boot performs the following automatically:

- Generates `config.php` from environment variables
- Seeds the database and applies migrations
- Builds the full-text search index
- Sets correct file permissions
- Requires no manual intervention

**Admin panel:** `http://dockercart.local/admin`  
**Admin credentials:** the values set during setup (or generated — see `.env` `ADMIN_USERNAME` / `ADMIN_PASSWORD`)

---

## Architecture

Six containers, one network, no exposed ports except Nginx.

```
                               Internet
                                   │
                         ┌─────────▼─────────┐
                         │   nginx:alpine    │
                         └────┬───────────┬──┘
                              │           │
                  ┌───────────▼───┐  ┌────▼──────────┐
                  │   PHP apache  │  │   Scheduler   │
                  └───────┬───────┘  └───────────────┘
                          │
              ┌───────────┼───────────┐
              │           │           │
    ┌─────────▼──┐  ┌────▼────┐  ┌──▼──────────┐
    │  MariaDB   │  │  Redis  │  │  Manticore  │
    └────────────┘  └─────────┘  └─────────────┘

    Optional: FTP (vsftpd — chrooted to ./upload/image)
```

Nginx is the sole entry point. It handles TLS termination, gzip compression, and static asset caching. Apache runs the PHP application behind Nginx with no exposed ports. MariaDB stores data, Redis handles caching and sessions, and Manticore powers full-text search. The scheduler daemon runs background tasks (cron, syncs, feeds).

All services communicate over a shared `dockercart-network` bridge. See `docs/guide.md#2-architecture` for the directory layout and storage paths.

---

## Deployment Modes

All modes are invoked via `make`. Container names are prefixed `dockercart_`. Full details are in `docs/guide.md#4-deployment`.

`make start` shows an interactive menu of all modes and remembers the last choice in `.env` (`DOCKERCART_RUN_MODE`). A bare `make start` restarts in the same mode. `make stop` stops all containers regardless of the mode they were started in.

### Standalone (default)

| Mode | Command | Description |
|---|---|---|
| HTTP | `make up` | Plain HTTP on port 80 |
| HTTPS (self-signed) | `make ssl` | HTTPS for development and staging |
| HTTPS (Let's Encrypt) | `make le` | Production SSL with auto-renewal |

### Traefik (external reverse proxy)

| Mode | Command |
|---|---|
| HTTP | `make traefik` |
| HTTPS (self-signed) | `make traefik-ssl` |
| HTTPS (Let's Encrypt) | `make traefik-le` |

### FTP (optional add-on)

```bash
make ftp   # Attach to any running mode — chrooted to ./upload/image
```

---

## Configuration

All settings live in `.env`. The first `make start` generates it interactively (domain, timezone, passwords, admin account). Missing keys are subsequently requested at startup. `.env.example` is the full reference template.

Config files are generated at container start — they should never be edited manually.

| Variable | Purpose |
|---|---|
| `DOCKERCART_URL` | Store base URL |
| `DB_*` | Database credentials |
| `ADMIN_USERNAME` / `ADMIN_PASSWORD` | Default admin account |
| `CACHE_ENGINE` | `redis` (default) or `file` |
| `REDIS_MAXMEMORY` | Redis memory limit |
| `PHP_MEMORY_LIMIT` | PHP memory limit |
| `MARIADB_CONFIG_SIZE` | InnoDB profile: `s` · `m` · `l` |

Full reference: `docs/guide.md#3-configuration`

---

## Resources

| Resource | Link |
|---|---|
| Developer guide | `docs/guide.md` |
| Capabilities | [dockercart.net/capabilities](https://dockercart.net/capabilities) |
| Changelog | [dockercart.net/changelog](https://dockercart.net/changelog) |
| Live demo | [demo.dockercart.net](https://demo.dockercart.net) |
| Add-ons store | [store.dockercart.net](https://store.dockercart.net) |
| Core updates (`make update`) | `docs/guide.md#9-core-updates` |
| Issues | [GitHub Issues](https://github.com/kdbsoft/dockercart/issues) |
| Security policy | `SECURITY.md` |

---

## Contributing

1. Fork the repository and create a feature branch
2. Write focused commits following [Conventional Commits](https://www.conventionalcommits.org/)
3. Test with `make up`
4. Submit a pull request

---

## License

DockerCart is released under the [GNU General Public License v3.0 (GPLv3)](https://www.gnu.org/licenses/gpl-3.0.html).

The project originates from a fork of [OpenCart](https://github.com/opencart/opencart) (also GPL-licensed) and has since evolved into an independent platform with its own architecture. All original attributions are preserved. See `LICENSE.md`.
