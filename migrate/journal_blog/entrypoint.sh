#!/usr/bin/env bash
# Entrypoint for the Journal Blog migration service.
# Extra args from `docker compose run` are forwarded to migrate.py.

set -euo pipefail

exec python /app/migrate.py "$@"
