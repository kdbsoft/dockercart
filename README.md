# DockerCart

![DockerCart Preview](upload/image/dockercart_preview.png)

DockerCart is a full-stack e-commerce platform built on a Docker infrastructure. A single command brings up the complete stack — Nginx reverse proxy, PHP 8.5 application server, MariaDB database, Redis cache, Manticore Search full-text engine, and a scheduler daemon — pre-configured and ready to serve production traffic.

There is no web installer and no `/install` directory. Run `make start` and a production-grade store is live.

Documentation and resources are available at [dockercart.net](https://dockercart.net), including the [capabilities list](https://dockercart.net/capabilities) and a [live demo](https://demo.dockercart.net).

---

## Technology

| Layer | Technology |
|---|---|
| Application | PHP 8.5 + Apache 2.4 |
| Reverse proxy | Nginx (alpine) |
| Database | MariaDB 11 |
| Cache | Redis 7 |
| Sessions | Persistent (file, `storage/session`) |
| Full-text search | Manticore Search 6 |
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

On first run, `make start` generates `.env` from `.env.example`. The interactive setup asks only for the store domain and admin email — everything else (timezone, ports, admin username, seed mode) gets a default, and all passwords are generated randomly. Set `DOCKERCART_NONINTERACTIVE=1` to skip the prompts entirely. Subsequent runs just start the stack (mode is remembered in `.env`). `make start` with no choice starts standalone HTTP; pass `start.sh` flags for non-interactive runs, e.g. `make start ARGS="--traefik --le"`.

> **Note for podman-compose users:** the stack is tested with docker compose, but works under podman-compose (podman's `docker` shim) too, **provided `.env` stays flat** — no nested `${VAR}` references. docker compose expands `MARIADB_USER=${DB_USERNAME}` recursively; podman-compose does not, which leaves the literal string in the container env, breaks the mariadb healthcheck, and the startup hangs waiting on `condition: service_healthy`. If `make start` seems stuck, check the mariadb container env: `docker inspect ${MARIADB_CONTAINER_NAME:-dockercart_mariadb} --format '{{range .Config.Env}}{{println .}}{{end}}'` — a literal `${DB_USERNAME}` in `MARIADB_USER`/`MARIADB_DATABASE` means `.env` needs the flat values from `.env.example`.

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

Nginx is the sole entry point. It handles TLS termination, gzip compression, and static asset caching. Apache runs the PHP application behind Nginx with no exposed ports. MariaDB stores data, Redis handles caching, sessions are stored persistently on disk (survive Redis restarts), and Manticore powers full-text search. The scheduler daemon runs background tasks (cron, syncs, feeds).

All services communicate over a shared `dockercart-network` bridge. See `docs/guide.md#2-architecture` for the directory layout and storage paths.

---

## Deployment Modes

All modes are invoked via `make`. Container names are prefixed `dockercart_`. Full details are in `docs/guide.md#4-deployment`.

The launch/SSL mode is chosen with `make start`, which shows an interactive menu
(Standalone vs. Traefik × none/self-signed/Let's Encrypt) and remembers the choice
in `.env` (`DOCKERCART_COMPOSE_FILES`). A bare `make start` (default HTTP) also
restarts in the last used mode. For non-interactive/CI runs, pass `start.sh` flags
directly: `make start ARGS="--traefik --le"`. `make stop` stops all containers
regardless of the mode they were started in.

### Start (interactive)

```bash
make start                 # menu: Standalone HTTP / self-signed / Let's Encrypt, or Traefik variants
make start ARGS="--le"     # non-interactive: standalone + Let's Encrypt
make start ARGS="--traefik --le"   # non-interactive: Traefik + Let's Encrypt
```

### FTP (optional add-on)

```bash
make ftp   # Attach to any running mode — chrooted to ./upload/image
```

### External Traefik (pre-existing reverse proxy)

The Traefik modes (`make start` → option 4/5/6) assume a **separate, already-running
Traefik** on the host, connected to the stack via the external `traefik` Docker
network. DockerCart does **not** start Traefik for you.

Prerequisites on the host:

- A Docker network named `traefik` (`docker network create traefik`, or match the
  name to `DOCKERCART_NETWORK`).
- Traefik v3 with an entrypoint named `web` (HTTP) and/or `websecure` (HTTPS), and
  certificate resolvers named `le` (Let's Encrypt) and `selfsigned`.
- For `make start ARGS="--traefik --le"`: a public DNS record pointing `DOCKERCART_DOMAIN` at the
  host, with inbound ports 80/443 open.

The repository ships reference Traefik configs under `docker/traefik/` — these are
**example host configurations**, not consumed by any compose file. Copy/adapt them
to your host Traefik instance as needed.

The nginx service still runs inside the stack (Traefik → nginx → apache) and carries
the `traefik.*` labels that register it with your external Traefik.

---

## Configuration

All settings live in `.env`. The first `make start` generates it from `.env.example` — asking only for the store domain and admin email, generating random passwords, and applying defaults for everything else. `.env.example` is the full reference template.

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
3. Test with `make start`
4. Submit a pull request

---

## License

DockerCart is released under the [GNU General Public License v3.0 (GPLv3)](https://www.gnu.org/licenses/gpl-3.0.html).

The project originates from a fork of [OpenCart](https://github.com/opencart/opencart) (also GPL-licensed) and has since evolved into an independent platform with its own architecture. All original attributions are preserved. See `LICENSE.md`.
