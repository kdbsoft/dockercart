#!/bin/bash
# DockerCart - Tracked SQL migration runner (shared by start.sh and update.sh)
#
# Applies every *.sql file in docker/mysql/migrations/ exactly once, recording
# applied filenames in the <prefix>schema_migrations table. Idempotent on
# re-run: already-applied files are skipped. Migration files MUST be idempotent
# themselves (CREATE TABLE IF NOT EXISTS, ADD COLUMN IF NOT EXISTS) per AGENTS.md.
#
# Usage:
#   ./scripts/run-migrations.sh
#
# Reads DB_* (or MARIADB_*) and DB_PREFIX from the environment. Used inside the
# apache/entrypoint context (compose exec) and by host-side deploy scripts.

set -eu

SCRIPT_DIR="$(cd -- "$(dirname -- "$0")" && pwd)"
REPO_DIR="$(cd -- "$SCRIPT_DIR/.." && pwd)"

DB_HOST="${DB_HOSTNAME:-${MARIADB_HOST:-mariadb}}"
DB_USER="${DB_USERNAME:-${MARIADB_USER:-dockercart}}"
DB_PASS="${DB_PASSWORD:-${MARIADB_PASSWORD:-dockercart_password}}"
DB_NAME="${DB_DATABASE:-${MARIADB_DATABASE:-dockercart}}"
DB_PREFIX_VALUE="${DB_PREFIX:-oc_}"

# Migrations live at docker/mysql/migrations on the host. Inside the apache
# container they are bind-mounted to /var/www/dc-migrations (see compose files).
if [ -d /var/www/dc-migrations ]; then
    MIG_DIR="/var/www/dc-migrations"
else
    MIG_DIR="$REPO_DIR/docker/mysql/migrations"
fi

# Validate prefix to avoid breaking SQL identifiers.
case "$DB_PREFIX_VALUE" in
    *[!a-zA-Z0-9_]*) echo "Error: DB_PREFIX contains unsupported characters: $DB_PREFIX_VALUE" >&2; exit 1 ;;
esac

MIGRATION_TABLE="${DB_PREFIX_VALUE}schema_migrations"

shopt -s nullglob
files=("$MIG_DIR"/*.sql)
if [ ${#files[@]} -eq 0 ]; then
    echo "run-migrations: no migration files found in $MIG_DIR — nothing to do."
    exit 0
fi

db_exec() {
    MYSQL_PWD="$DB_PASS" mariadb -h"$DB_HOST" -u"$DB_USER" --skip-ssl --default-character-set=utf8mb4 "$DB_NAME" "$@"
}

echo "run-migrations: waiting for MariaDB at ${DB_HOST}..."
max_attempts=30
attempt=0
until MYSQL_PWD="$DB_PASS" mariadb -h"$DB_HOST" -u"$DB_USER" --skip-ssl -e "SELECT 1" >/dev/null 2>&1; do
    attempt=$((attempt + 1))
    if [ $attempt -ge $max_attempts ]; then
        echo "run-migrations: ERROR: MariaDB not reachable at ${DB_HOST} (gave up after ${max_attempts} attempts)." >&2
        exit 1
    fi
    echo "run-migrations: DB not ready (attempt ${attempt}/${max_attempts}) - sleeping"
    sleep 2
done
echo "run-migrations: MariaDB is up."

echo "run-migrations: ensuring tracking table ${MIGRATION_TABLE} exists..."
db_exec -e "CREATE TABLE IF NOT EXISTS \`$MIGRATION_TABLE\` (filename VARCHAR(255) NOT NULL, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (filename)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"

applied=0
skipped=0
for migration in "${files[@]}"; do
    filename="$(basename "$migration")"
    escaped="$(printf '%s' "$filename" | sed "s/'/''/g")"
    if [ "$(db_exec -Nse "SELECT 1 FROM \`$MIGRATION_TABLE\` WHERE filename='$escaped' LIMIT 1;" 2>/dev/null)" = "1" ]; then
        skipped=$((skipped + 1))
        continue
    fi
    echo "run-migrations: applying $filename"
    if db_exec < "$migration"; then
        db_exec -e "INSERT INTO \`$MIGRATION_TABLE\` (filename) VALUES ('$escaped');"
        applied=$((applied + 1))
        echo "run-migrations: applied $filename"
    else
        echo "run-migrations: ERROR applying $filename" >&2
        exit 1
    fi
done

echo "run-migrations: done. Applied: $applied, skipped: $skipped"
