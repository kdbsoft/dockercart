#!/bin/sh

set -eu

SCRIPT_DIR=$(CDPATH='' cd -- "$(dirname -- "$0")" && pwd)
cd "$SCRIPT_DIR"

log() {
    printf '%s %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

if [ -f "$SCRIPT_DIR/.env" ]; then
    # shellcheck disable=SC1091
    . "$SCRIPT_DIR/scripts/load-env.sh"
    load_env "$SCRIPT_DIR/.env"
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

compose() {
    # start.sh records the active -f file set in .env as
    # DOCKERCART_COMPOSE_FILES; use it when present so updates in Traefik/SSL
    # modes keep the same overrides instead of falling back to the base file.
    FILES=""
    if [ -n "${DOCKERCART_COMPOSE_FILES:-}" ]; then
        for f in $DOCKERCART_COMPOSE_FILES; do
            FILES="$FILES -f $f"
        done
    elif [ "${TRAEFIK:-0}" = "1" ]; then
        FILES="-f docker-compose.traefik.yml"
    else
        FILES="-f docker-compose.yml"
        if docker ps -a --format '{{.Names}}' 2>/dev/null | grep -qFx "${CERTBOT_CONTAINER_NAME:-dockercart_certbot}"; then
            FILES="$FILES -f docker-compose.le.yml"
        fi
    fi
    # shellcheck disable=SC2086
    # 9>&- closes the flock fd in this child and all its descendants
    # (podman spawns long-lived conmon container monitors that would otherwise
    # inherit fd 9 and hold the .update.lock flock forever, blocking later
    # `make update` runs with a bogus "already running" error).
    docker compose $FILES "$@" 9>&-
}

reconcile_working_tree() {
    # The GUI updater (admin/cli/dockercart_update.php) copies upload/ straight
    # into the bind mount but never advances git HEAD, so the working tree ends
    # up dirty on exactly the files upstream will also touch. A fast-forward
    # pull refuses to overwrite locally-modified files, so before pulling we
    # reset only those GUI-managed paths (upload/*) that the upstream commit
    # also changes. Real local edits outside upload/ are left untouched (they
    # are blocked earlier by the dirty check below).
    upstream_changed=$(git diff --name-only "$BASE" "$REMOTE" 2>/dev/null)
    [ -z "$upstream_changed" ] && return 0

    local_dirty=$(git diff --name-only HEAD 2>/dev/null)
    [ -z "$local_dirty" ] && return 0

    echo "$local_dirty" | while read -r f; do
        case "$f" in
            upload/*) ;;
            *) continue ;;
        esac

        if echo "$upstream_changed" | grep -qx "$f"; then
            git checkout -- "$f"
            log "Reconciled (GUI sync) before pull: $f"
        fi
    done
}

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    log "Error: $SCRIPT_DIR is not a git repository."
    exit 1
fi

BRANCH=$(git symbolic-ref --quiet --short HEAD || true)
if [ -z "$BRANCH" ]; then
    log "Error: detached HEAD is not supported for automated updates."
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
	reconcile_working_tree

	# After reconciling GUI-applied paths (upload/*), any remaining
	# tracked changes are real local edits. GUI-managed paths that upstream did
	# NOT touch are harmless for a fast-forward pull (kept as-is), so only edits
	# outside upload/ block the update.
	if [ "${ALLOW_DIRTY:-0}" != "1" ]; then
		blocking=""
		for f in $(git diff --name-only HEAD 2>/dev/null); do
			case "$f" in
				upload/*) ;;
				*) blocking="$blocking $f" ;;
			esac
		done
		if [ -n "$blocking" ]; then
			log "Error: repository has local tracked changes outside upload/:$blocking. Commit/stash them or set ALLOW_DIRTY=1."
			exit 1
		fi
	fi

	git pull --ff-only origin "$BRANCH"
	log "Code updated successfully."

	# Decide whether the container image itself needs a rebuild. Changes to the
	# Dockerfile, PHP config, Apache config, or docker/ scripts change what is
	# baked into the image; bind mounts alone will NOT pick those up. Compare the
	# relevant paths between the previous and new commit (or HEAD when no fetch
	# happened) — if any differ, rebuild.
	BUILD_REQUIRED=0
	BUILD_PATHSPEC="Dockerfile docker/ docker-compose*.yml composer.lock"
	# shellcheck disable=SC2086
	if git diff --quiet "$BASE" "$REMOTE" -- $BUILD_PATHSPEC 2>/dev/null; then
		log "No Dockerfile/docker/compose changes detected — skipping image rebuild."
	else
		log "Dockerfile, docker/ or compose changes detected — rebuilding image."
		BUILD_REQUIRED=1
	fi

	if [ "$BUILD_REQUIRED" -eq 1 ]; then
		compose build apache scheduler
		compose up -d --force-recreate apache scheduler
	else
		# Single-file bind mounts (e.g. VERSION) are bound by inode at container
		# creation time. git pull replaces files with new inodes, so the running
		# container keeps reading the old content. Force-recreate apache AND
		# scheduler to re-bind all single-file mounts and pick up code changes.
		log "Recreating apache + scheduler containers to refresh bind mounts..."
		compose up --force-recreate --no-deps -d apache scheduler
	fi

	# Refresh OCMOD modifications to match new code
	log "Refreshing OCMOD modifications..."
	compose exec -T apache php /var/www/html/admin/cli/dockercart_modification_refresh.php
	compose exec -T apache chown -R www-data:staff /var/www/storage/modification/
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

DB_USER="${DB_USERNAME:-${MARIADB_USER:-dockercart}}"
DB_PASS="${DB_PASSWORD:-${MARIADB_PASSWORD:-dockercart_password}}"
DB_NAME="${DB_DATABASE:-${MARIADB_DATABASE:-dockercart}}"
DB_PREFIX_VALUE="${DB_PREFIX:-oc_}"
MARIADB_CONTAINER="${MARIADB_CONTAINER_NAME:-${COMPOSE_PROJECT_NAME:-dockercart}_mariadb}"
DB_EXEC_METHOD="compose"

case "$DB_PREFIX_VALUE" in
    *[!a-zA-Z0-9_]*)
        log "Error: DB_PREFIX contains unsupported characters: $DB_PREFIX_VALUE"
        exit 1
        ;;
esac

MIGRATION_TABLE="${DB_PREFIX_VALUE}schema_migrations"

db_exec_compose() {
    compose exec -T -e MYSQL_PWD mariadb mariadb -u"$DB_USER" "$DB_NAME" "$@"
}

db_exec_docker() {
    docker exec -i -e MYSQL_PWD="$DB_PASS" "$MARIADB_CONTAINER" mariadb -u"$DB_USER" "$DB_NAME" "$@" 9>&-
}

db_exec() {
    if [ "$DB_EXEC_METHOD" = "docker" ]; then
        db_exec_docker "$@"
    else
        db_exec_compose "$@"
    fi
}

log "Checking database connectivity..."
if db_exec_compose -e "SELECT 1;" >/dev/null 2>&1; then
    DB_EXEC_METHOD="compose"
elif db_exec_docker -e "SELECT 1;" >/dev/null 2>&1; then
    DB_EXEC_METHOD="docker"
    log "Warning: compose exec failed, using container fallback: $MARIADB_CONTAINER"
else
    compose_err=$(db_exec_compose -e "SELECT 1;" 2>&1 || true)
    docker_err=$(db_exec_docker -e "SELECT 1;" 2>&1 || true)
    log "Error: cannot connect to MariaDB container/database."
    if [ -n "$compose_err" ]; then
        log "compose exec error: $compose_err"
    fi
    if [ -n "$docker_err" ]; then
        log "docker exec error: $docker_err"
    fi
    log "DB settings used: user=$DB_USER db=$DB_NAME container=$MARIADB_CONTAINER"
    log "Hint: ensure mariadb is running and DB_* values in .env match existing DB volume credentials."
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
