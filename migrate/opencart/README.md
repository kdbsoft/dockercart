# OpenCart → DockerCart Migration

Migrates catalog data from an existing OpenCart installation (2.x / 3.x / 4.x) into a DockerCart database — categories, products, manufacturers, information pages, articles, and SEO URLs.

## Directory structure

```
migrate/opencart/
  migrate.py          # Migration script (Python 3.12, requires pymysql)
  Dockerfile          # python:3.12-slim image with pymysql pre-installed
  entrypoint.sh       # Maps environment variables to CLI arguments
  docker-compose.yml  # One-shot service attached to dockercart-network
  .env.example        # Template — copy to .env and fill in credentials
```

## Quick start

### 1. Create `.env`

```bash
cd migrate/opencart/
cp .env.example .env
```

Edit `.env` and fill in the source and target database details.

**`SOURCE_*`** — the old OpenCart database server  
**`TARGET_*`** — the MariaDB container inside your DockerCart stack.  
When the main stack is running, `TARGET_HOST=mariadb` resolves directly inside `dockercart-network` — no need to expose the MariaDB port externally.

### 2. Build the image

```bash
# Run from the migrate/opencart/ directory
docker compose build
```

### 3. Dry-run

```bash
DRY_RUN=true docker compose run --rm migrate
```

Changes are **not** committed — the run only prints what would be migrated.

### 4. Full migration

```bash
docker compose run --rm migrate
```

### Interactive language mapping

If `LANGUAGE_MAP` is empty in `.env`, the script will prompt for language mapping interactively. Use the `-it` flag:

```bash
docker compose run --rm -it migrate
```

### Migrate selected entities only

```bash
ENTITIES=categories,manufacturers docker compose run --rm migrate
```

Available values: `categories`, `products`, `manufacturers`, `information`, `article`.

## Environment variables

| Variable            | Required | Default               | Description                                                  |
|---------------------|----------|-----------------------|--------------------------------------------------------------|
| `SOURCE_HOST`       | ✔        | —                     | Source OpenCart database host                                |
| `SOURCE_PORT`       |          | `3306`                | Source database port                                         |
| `SOURCE_USER`       | ✔        | —                     | Source database user                                         |
| `SOURCE_PASSWORD`   | ✔        | —                     | Source database password                                     |
| `SOURCE_DATABASE`   | ✔        | —                     | Source database name                                         |
| `SOURCE_PREFIX`     |          | auto-detected         | Source table prefix (e.g. `oc_`)                             |
| `TARGET_HOST`       | ✔        | —                     | Target DockerCart database host                              |
| `TARGET_PORT`       |          | `3306`                | Target database port                                         |
| `TARGET_USER`       | ✔        | —                     | Target database user                                         |
| `TARGET_PASSWORD`   | ✔        | —                     | Target database password                                     |
| `TARGET_DATABASE`   | ✔        | —                     | Target database name                                         |
| `TARGET_PREFIX`     |          | auto-detected         | Target table prefix                                          |
| `ENTITIES`          |          | all                   | Comma-separated list of entities to migrate                  |
| `LANGUAGE_MAP`      |          | interactive prompt    | Language mapping, e.g. `1:1` or `en-gb:1,uk-ua:2`           |
| `DRY_RUN`           |          | `false`               | `true` — preview steps without COMMIT                        |
| `DOCKERCART_NETWORK`|          | `dockercart-network`  | External Docker network name of the main DockerCart stack    |

## Network connectivity

The `migrate` service joins the external `dockercart-network` (configurable via `DOCKERCART_NETWORK`), meaning `TARGET_HOST=mariadb` resolves to the MariaDB container without exposing any ports on the host.

The source OpenCart database must be reachable over TCP from the Docker host or have an exposed port — the container will connect to it directly.

## Passing extra flags

Any arguments after the service name in `docker run` / `docker compose run` are forwarded directly to the script:

```bash
docker compose run --rm migrate --help
docker compose run --rm migrate --dry-run --entities=categories
```
