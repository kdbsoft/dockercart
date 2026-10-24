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

# Path of the manifest written by the GUI updater (admin/cli/dockercart_update.php):
# the exact list of files it deployed into upload/. Used to tell GUI copies apart
# from files the user added by hand.
UPDATE_MANIFEST="$SCRIPT_DIR/storage/dockercart_update/manifest.json"
RECONCILE_BACKUP="$SCRIPT_DIR/storage/dockercart_update/backup/reconcile"
DEPLOYED_FILE="$SCRIPT_DIR/storage/dockercart_update/deployed.json"
HOST_GIT_FILE="$SCRIPT_DIR/storage/dockercart_update/host-git.json"

# One manifest path per line. The file is a single-line JSON string array, so a
# crude split is enough (paths are relative to upload/).
manifest_paths() {
    [ -f "$UPDATE_MANIFEST" ] || return 0
    tr ',' '\n' < "$UPDATE_MANIFEST" \
        | sed -e 's/\\\//\//g' -e 's/^\["//' -e 's/"\]$//' -e 's/^"//' -e 's/"$//'
}

manifest_has() {
    manifest_paths | grep -qxF "$1"
}

# Read a top-level string value from a small JSON file without jq/php.
json_get() {
    [ -f "$1" ] || return 1
    sed -n 's/.*"'"$2"'"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' "$1" \
        | sed -e 's|\\/|/|g' -e 's|\\"|"|g' -e 's|\\\\|\\|g' \
        | head -n 1
}

# Escape a value for a JSON string.
json_escape() {
    printf '%s' "$1" | sed -e 's/\\/\\\\/g' -e 's/"/\\"/g'
}

# Atomically write a flat JSON object from key=value arguments.
write_json_file() {
    json_file=$1
    shift
    json_tmp="$json_file.tmp"
    json_first=1

    mkdir -p "$(dirname "$json_file")" 2>/dev/null || true

    {
        printf '{\n'
        for json_kv in "$@"; do
            if [ "$json_first" -eq 1 ]; then
                json_first=0
            else
                printf ',\n'
            fi
            printf '  "%s": "%s"' "${json_kv%%=*}" "$(json_escape "${json_kv#*=}")"
        done
        printf '\n}\n'
    } > "$json_tmp"

    mv -f "$json_tmp" "$json_file"
}

# Normalize a git remote URL for comparison (drop trailing .git and slash).
normalize_remote() {
    printf '%s' "$1" | sed -e 's#/*$##' -e 's/\.git$//'
}

# Record the host git checkout state so the admin panel can compare it with the
# repository configured for the GUI updater.
write_host_git_state() {
    write_json_file "$HOST_GIT_FILE" \
        "origin=$(git remote get-url origin 2>/dev/null || true)" \
        "branch=$BRANCH" \
        "head=$(git rev-parse HEAD 2>/dev/null || true)" \
        "version=$(tr -d '\r\n' 2>/dev/null < "$SCRIPT_DIR/upload/VERSION" || true)" \
        "checked_at=$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
}

# Reset tracked paths from HEAD. The list is fed through stdin
# (--pathspec-from-file, git >= 2.25) so paths with spaces survive, and a single
# command avoids racing the IDE's git integration for .git/index.lock. Only an
# index-lock failure is retried; any other error is reported immediately.
# `HEAD` (not a bare `--`) is required: a bare checkout restores from the index,
# which would leave IDE-staged GUI changes behind and still block the pull.
reconcile_checkout() {
    reconcile_paths=$1
    reconcile_tries=0

    # --pathspec-from-file needs git >= 2.25. On older git fall back to passing
    # the paths as arguments (breaks on spaces in names, but keeps updates
    # working instead of aborting on an unknown option).
    if git checkout -h 2>&1 | grep -q -- '--pathspec-from-file'; then
        reconcile_use_file=1
    else
        reconcile_use_file=0
    fi

    while :; do
        if [ "$reconcile_use_file" = "1" ]; then
            reconcile_err=$(printf '%s\n' "$reconcile_paths" | git checkout HEAD --pathspec-from-file=- 2>&1) && reconcile_rc=0 || reconcile_rc=$?
        else
            # shellcheck disable=SC2086
            reconcile_err=$(git checkout HEAD -- $reconcile_paths 2>&1) && reconcile_rc=0 || reconcile_rc=$?
        fi

        if [ "$reconcile_rc" -eq 0 ]; then
            return 0
        fi

        case "$reconcile_err" in
            *index.lock*|*"Another git process"*)
                reconcile_tries=$((reconcile_tries + 1))

                if [ "$reconcile_tries" -ge 5 ]; then
                    log "Error: git index stayed locked after 5 attempts: $reconcile_err"
                    return 1
                fi

                log "git index busy, retrying ($reconcile_tries/5)..."
                sleep 1
                ;;
            *)
                log "Error: git checkout failed: $reconcile_err"
                return 1
                ;;
        esac
    done
}

# Keep a copy of a file that is about to be removed, preserving its path
# relative to upload/, so nothing the user wrote is silently lost.
reconcile_backup_file() {
    reconcile_rel=$1
    reconcile_dest="$RECONCILE_BACKUP-$RECONCILE_TS/$reconcile_rel"

    mkdir -p "$(dirname "$reconcile_dest")" 2>/dev/null || true
    cp -p "$SCRIPT_DIR/upload/$reconcile_rel" "$reconcile_dest" 2>/dev/null || true
}

reconcile_working_tree() {
    # The GUI updater (admin/cli/dockercart_update.php) copies upload/ straight
    # into the bind mount but never advances git HEAD (the .git directory is not
    # mounted into the container), so the working tree ends up carrying the new
    # release while HEAD still points at the old one. A fast-forward pull then
    # refuses to run: tracked files look locally modified, files the GUI added
    # are untracked ("would be overwritten by merge"), and files the GUI pruner
    # removed are shown as deletions.
    #
    # Before pulling, put upload/ back into a state git can fast-forward:
    #   1. restore tracked upload/ files the upstream commit changes, plus any
    #      tracked upload/ file the GUI pruner deleted;
    #   2. remove untracked upload/ files that upstream provides (GUI copies).
    # Real local edits outside upload/ still block the update below.
    upstream_changed=$(git diff --name-only "$BASE" "$REMOTE" 2>/dev/null || true)
    [ -z "$upstream_changed" ] && return 0

    RECONCILE_TS=$(date '+%Y%m%d-%H%M%S')

    # The GUI worker records what it deployed (source, remote, branch, sha). Use
    # it to (a) warn when the GUI and the git checkout track different sources
    # and (b) compare untracked files against the exact deployed commit instead
    # of only the pull target.
    deployed_source=$(json_get "$DEPLOYED_FILE" source || true)
    deployed_remote=$(json_get "$DEPLOYED_FILE" remote || true)
    deployed_branch=$(json_get "$DEPLOYED_FILE" branch || true)
    deployed_sha=$(json_get "$DEPLOYED_FILE" sha || true)

    if [ "$deployed_source" = "gui" ]; then
        origin_remote=$(git remote get-url origin 2>/dev/null || true)

        if [ -n "$deployed_remote" ] && [ -n "$origin_remote" ] \
            && [ "$(normalize_remote "$deployed_remote")" != "$(normalize_remote "$origin_remote")" ]; then
            log "Warning: the GUI updater deployed from $deployed_remote, but this checkout's origin is $origin_remote - upload/ is being reset to git's HEAD."
        fi

        if [ -n "$deployed_branch" ] && [ "$deployed_branch" != "$BRANCH" ]; then
            log "Warning: the GUI updater tracked branch $deployed_branch, but this checkout is on $BRANCH."
        fi
    fi

    # Prefer the exact deployed commit for content comparison; fall back to the
    # pull target. The pull target still decides which untracked files block the
    # merge (existence check in the loop below).
    compare_refs="$REMOTE"

    if [ -n "$deployed_sha" ] && git cat-file -e "$deployed_sha^{commit}" 2>/dev/null; then
        compare_refs="$deployed_sha $REMOTE"
    fi

    # --- 1. tracked changes -------------------------------------------------
    reset_list=""

    while IFS= read -r f; do
        [ -z "$f" ] && continue

        case "$f" in
            upload/*) ;;
            *) continue ;;
        esac

        if [ -e "$SCRIPT_DIR/$f" ]; then
            # Modified: reset only when upstream also changes the file, so a
            # real local edit to an untouched template survives.
            if printf '%s\n' "$upstream_changed" | grep -qxF "$f"; then
                reset_list="${reset_list:+$reset_list
}$f"
            fi
        else
            # Deleted: restore while upstream still ships the file. A deletion
            # upstream shares is already covered by the pull itself.
            if git cat-file -e "$REMOTE:$f" 2>/dev/null; then
                reset_list="${reset_list:+$reset_list
}$f"
            fi
        fi
    done <<EOF
$(git diff --name-only HEAD 2>/dev/null || true)
EOF

    if [ -n "$reset_list" ]; then
        if reconcile_checkout "$reset_list"; then
            log "Reconciled $(printf '%s' "$reset_list" | grep -c .) tracked GUI path(s) before pull."
        else
            log "Error: could not reset GUI-managed paths (git index is busy)."
            return 1
        fi
    fi

    # --- 2. untracked files that upstream provides -------------------------
    removed=0

    while IFS= read -r f; do
        [ -z "$f" ] && continue

        case "$f" in
            upload/*) ;;
            *) continue ;;
        esac

        # A pure local addition that upstream does not ship cannot block the
        # pull, so leave it alone.
        git cat-file -e "$REMOTE:$f" 2>/dev/null || continue

        rel=${f#upload/}

        reconcile_known=0

        for compare_ref in $compare_refs; do
            if git show "$compare_ref:$f" 2>/dev/null | cmp -s - "$SCRIPT_DIR/$f"; then
                reconcile_known=1
                break
            fi
        done

        if [ "$reconcile_known" -eq 1 ]; then
            # Byte-identical to a known deployment: an unmodified GUI copy.
            rm -f "$SCRIPT_DIR/$f"
            removed=$((removed + 1))
        else
            # Content differs: keep a copy before removing the obstacle. A file
            # listed in the GUI manifest is a GUI copy; anything else is a user
            # addition and gets an explicit warning.
            reconcile_backup_file "$rel"
            rm -f "$SCRIPT_DIR/$f"
            removed=$((removed + 1))

            if manifest_has "$rel"; then
                log "Replaced GUI file whose content differed (backup kept): $f"
            else
                log "Warning: removed untracked file not in the GUI manifest (backup kept): $f"
            fi
        fi
    done <<EOF
$(git ls-files --others --exclude-standard -- upload/ 2>/dev/null || true)
EOF

    if [ "$removed" -gt 0 ]; then
        log "Removed $removed GUI-copied untracked file(s) before pull."
    fi

    return 0
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
write_host_git_state || log "Warning: could not record host git state."

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

	# Record the git deployment so the GUI/admin and the next update run can see
	# which source is actually checked out.
	write_json_file "$DEPLOYED_FILE" \
		"source=git" \
		"remote=$(git remote get-url origin 2>/dev/null || true)" \
		"branch=$BRANCH" \
		"sha=$(git rev-parse HEAD 2>/dev/null || true)" \
		"version=$(tr -d '\r\n' 2>/dev/null < "$SCRIPT_DIR/upload/VERSION" || true)" \
		"deployed_at=$(date -u '+%Y-%m-%dT%H:%M:%SZ')" \
		|| log "Warning: could not record deployment state."

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

	# apache gets a new IP after --force-recreate, so nginx must re-resolve
	# the upstream hostname immediately instead of serving 502s until its
	# periodic re-resolve (or next restart). Non-fatal: the update itself
	# (code + migrations) must not fail just because nginx is stopped.
	compose exec -T nginx nginx -s reload || log "Warning: nginx reload failed, restart nginx manually."

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
