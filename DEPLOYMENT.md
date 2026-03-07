# DockerCart Deployment Guide

## Current deployment model

DockerCart runs in **install-less** mode:

- the legacy OpenCart web installer (`/install`) is removed;
- runtime app config is read from `.env` by `upload/config.php` and `upload/admin/config.php`;
- initial database bootstrap is performed automatically on first MariaDB startup:
  - seed SQL: `docker/mysql/init.sql`
  - bootstrap script: `docker/mysql/init.sh`.

> Important: `docker/mysql/init.sql` is executed only when the DB volume is created for the first time.

---

## Quick start

### 1) Prepare environment

1. Copy `.env.example` to `.env`.
2. Set DB credentials and your domain/URL values.
3. Optionally set admin bootstrap values:
   - `ADMIN_USERNAME`
   - `ADMIN_PASSWORD`
   - `ADMIN_EMAIL`

### 2) Start in Traefik mode
Uses `docker-compose.yml` with an external Traefik network. Prefer the helper script or Make targets which select HTTP/HTTPS modes automatically.

```bash
# HTTP (default)
make up
# or
./start.sh

# HTTPS local testing (self-signed)
make ssl
# or
./start.sh --ssl

# HTTPS production (Let's Encrypt) — requires valid domain and DNS
make letsencrypt
# or
./start.sh --letsencrypt
```

Note: Traefik must be available on the external `traefik` network (the compose files expect an external network named `traefik`). The start script will include the appropriate docker-compose override files for SSL vs non-SSL modes.

### 3) Start in Standalone mode

Uses `docker-compose.standalone.yml` (default host port is `80`).

```bash
docker compose -f docker-compose.standalone.yml up -d --build
```

---

## First database initialization

On first startup MariaDB will:

1. create DB/user from environment variables;
2. run `docker/mysql/init.sh`;
3. import `docker/mysql/init.sql`;
4. apply bootstrap settings (admin user, store URL, etc.) if OpenCart tables exist.

If `docker/mysql/init.sql` is empty, containers will still start, but the DB will remain empty and the application will not be usable until the seed is provided and the DB volume is reinitialized.

### Reinitialize DB volume (destructive)

```bash
docker compose down -v
docker compose up -d --build
```

Standalone mode:

```bash
docker compose -f docker-compose.standalone.yml down -v
docker compose -f docker-compose.standalone.yml up -d --build
```

---

## Deploy to a remote server

Use `deploy-docker.sh`.

Example:

```bash
./deploy-docker.sh -h your-server.com -u deploy -d /opt/dockercart --restart --yes --env
```

Files transferred:

- `docker-compose.yml`
- `docker-compose.standalone.yml`
- `docker/`
- `Dockerfile`
- `.dockerignore`
- `Makefile`
- `start.sh`
- `install-cli.sh`
- `DEPLOYMENT.md`
- `.env.example`
- `.env` (when `--env` is passed)

---

## Post-start checks

```bash
docker compose ps
docker compose logs -f mariadb
docker compose logs -f apache
docker compose logs -f nginx
```

Bootstrap helper check:

```bash
bash ./install-cli.sh
```

---

## Common issues

### 1) Database is empty

Cause: empty or invalid `docker/mysql/init.sql`.

Fix:

1. place a valid OpenCart-compatible seed in `docker/mysql/init.sql`;
2. reinitialize DB volume (`down -v` + `up -d --build`).

### 2) Wrong storefront/admin URLs

Verify `.env` values:

- `DOCKERCART_URL`
- `DOCKERCART_HTTPS_URL`
- `DOCKERCART_SSL_ENABLED`
- `DOCKERCART_DOMAIN`

If SSL is disabled, set:

- `DOCKERCART_SSL_ENABLED=false`
- `DOCKERCART_HTTPS_URL` equal to `DOCKERCART_URL`

### 3) Updated env values were not applied

Restart containers:

```bash
docker compose down
docker compose up -d
```
