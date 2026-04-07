#!/bin/sh

set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
cd "$SCRIPT_DIR"

log() {
    printf '%s %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

if [ -f "$SCRIPT_DIR/.env" ]; then
    set -a
    # shellcheck disable=SC1091
    . "$SCRIPT_DIR/.env"
    set +a
fi

LOCK_FILE="${LOCK_FILE:-$SCRIPT_DIR/.update.lock}"
if command -v flock >/dev/null 2>&1; then
    exec 9>"$LOCK_FILE"
    if ! flock -n 9; then
        log "Another update process is already running. Exiting."
        exit 0
    fi
else
    log "Warning: flock is not installed. Lock protection is disabled."
fi

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    log "Error: $SCRIPT_DIR is not a git repository."
    exit 1
fi

BRANCH=$(git symbolic-ref --quiet --short HEAD || true)
if [ -z "$BRANCH" ]; then
    log "Error: detached HEAD is not supported for automated updates."
    exit 1
fi

if [ "${ALLOW_DIRTY:-0}" != "1" ] && [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    log "Error: repository has local tracked changes. Commit/stash them or set ALLOW_DIRTY=1."
    exit 1
fi

log "Current branch: $BRANCH"
log "Fetching updates from origin/$BRANCH..."
git fetch --prune origin "$BRANCH"

LOCAL=$(git rev-parse @)
REMOTE=$(git rev-parse "origin/$BRANCH")
BASE=$(git merge-base @ "origin/$BRANCH")

if [ "$LOCAL" = "$REMOTE" ]; then
    log "Code is already up to date."
elif [ "$LOCAL" = "$BASE" ]; then
    log "Pulling updates (fast-forward only)..."
    git pull --ff-only origin "$BRANCH"
    log "Code updated successfully."
elif [ "$REMOTE" = "$BASE" ]; then
    log "Local branch is ahead of origin. Skipping pull."
else
    log "Error: local and remote branches have diverged. Manual intervention required."
    exit 1
fi

if [ "${SKIP_MIGRATIONS:-0}" = "1" ]; then
    log "SKIP_MIGRATIONS=1 set. Database migrations are skipped."
    exit 0
fi

compose() {
    if [ "${STANDALONE:-0}" = "1" ]; then
        docker compose -f docker-compose.standalone.yml "$@"
    else
        docker compose "$@"
    fi
}

DB_USER="${DB_USERNAME:-${MARIADB_USER:-dockercart}}"
DB_PASS="${DB_PASSWORD:-${MARIADB_PASSWORD:-dockercart_password}}"
DB_NAME="${DB_DATABASE:-${MARIADB_DATABASE:-dockercart}}"
DB_PREFIX_VALUE="${DB_PREFIX:-oc_}"

case "$DB_PREFIX_VALUE" in
    *[!a-zA-Z0-9_]*)
        log "Error: DB_PREFIX contains unsupported characters: $DB_PREFIX_VALUE"
        exit 1
        ;;
esac

MIGRATION_TABLE="${DB_PREFIX_VALUE}schema_migrations"

db_exec() {
    compose exec -T mariadb mariadb -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" "$@"
}

log "Checking database connectivity..."
if ! db_exec -e "SELECT 1;" >/dev/null 2>&1; then
    log "Error: cannot connect to MariaDB container/database."
    exit 1
fi

log "Ensuring migration tracking table exists: $MIGRATION_TABLE"
db_exec -e "CREATE TABLE IF NOT EXISTS \`$MIGRATION_TABLE\` (filename VARCHAR(255) NOT NULL, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (filename)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"

set -- docker/mysql/migrations/*.sql
if [ ! -e "$1" ]; then
    log "No SQL migration files found in docker/mysql/migrations/."
    exit 0
fi

applied_count=0
skipped_count=0

for migration in "$@"; do
    filename=$(basename "$migration")
    escaped_filename=$(printf '%s' "$filename" | sed "s/'/''/g")

    if [ "$(db_exec -Nse "SELECT 1 FROM \`$MIGRATION_TABLE\` WHERE filename='$escaped_filename' LIMIT 1;")" = "1" ]; then
        log "Skipping already applied migration: $filename"
        skipped_count=$((skipped_count + 1))
        continue
    fi

    log "Applying migration: $filename"
    if db_exec < "$migration"; then
        db_exec -e "INSERT INTO \`$MIGRATION_TABLE\` (filename) VALUES ('$escaped_filename');"
        applied_count=$((applied_count + 1))
        log "Applied migration: $filename"
    else
        log "Error: failed to apply migration $filename"
        exit 1
    fi
done

log "Done. Applied: $applied_count, skipped: $skipped_count"
