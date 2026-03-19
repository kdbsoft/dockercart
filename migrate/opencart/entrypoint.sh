#!/usr/bin/env bash
# Entrypoint for the DockerCart OpenCart migration service.
# Maps environment variables to CLI arguments, then hands off to migrate.py.
# Any extra arguments appended to `docker run` / `docker compose run` are
# forwarded verbatim, making it easy to override individual flags or pass --help.

set -euo pipefail

args=()

# ── Source (old OpenCart) database ───────────────────────────────────────────
[[ -n "${SOURCE_HOST:-}" ]]     && args+=(--source-host     "$SOURCE_HOST")
[[ -n "${SOURCE_PORT:-}" ]]     && args+=(--source-port     "$SOURCE_PORT")
[[ -n "${SOURCE_USER:-}" ]]     && args+=(--source-user     "$SOURCE_USER")
[[ -n "${SOURCE_PASSWORD:-}" ]] && args+=(--source-password "$SOURCE_PASSWORD")
[[ -n "${SOURCE_DATABASE:-}" ]] && args+=(--source-database "$SOURCE_DATABASE")
[[ -n "${SOURCE_PREFIX:-}" ]]   && args+=(--source-prefix   "$SOURCE_PREFIX")

# ── Target (DockerCart) database ─────────────────────────────────────────────
[[ -n "${TARGET_HOST:-}" ]]     && args+=(--target-host     "$TARGET_HOST")
[[ -n "${TARGET_PORT:-}" ]]     && args+=(--target-port     "$TARGET_PORT")
[[ -n "${TARGET_USER:-}" ]]     && args+=(--target-user     "$TARGET_USER")
[[ -n "${TARGET_PASSWORD:-}" ]] && args+=(--target-password "$TARGET_PASSWORD")
[[ -n "${TARGET_DATABASE:-}" ]] && args+=(--target-database "$TARGET_DATABASE")
[[ -n "${TARGET_PREFIX:-}" ]]   && args+=(--target-prefix   "$TARGET_PREFIX")

# ── Migration options ─────────────────────────────────────────────────────────
[[ -n "${ENTITIES:-}" ]]      && args+=(--entities     "$ENTITIES")
[[ -n "${LANGUAGE_MAP:-}" ]]  && args+=(--language-map "$LANGUAGE_MAP")
[[ "${DRY_RUN:-false}" == "true" ]] && args+=(--dry-run)

# Extra args from `docker run ... migrate <extra>` are appended last so they
# can override env-based flags (argparse last-wins for duplicate flags).
exec python /app/migrate.py "${args[@]}" "$@"
