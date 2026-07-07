#!/bin/bash
# Install /etc/cron.d/dockercart-backup entry for scheduled S3 backups.
#
# Reads BACKUP_S3_SCHEDULE and BACKUP_S3_CRON_USER from .env (with defaults),
# writes a cron entry that runs the backup-worker compose service.
#
# Uses COMPOSE_PROFILES=backup (standard Compose spec env var) to activate the
# backup-worker service profile — works identically with docker compose v2 and
# podman-compose.
#
# Idempotent: re-running overwrites the existing entry.
#
# Usage: sudo ./install-backup-cron.sh
set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"
CRON_FILE="/etc/cron.d/dockercart-backup"
LOG_FILE="$PROJECT_DIR/storage/logs/backup.log"

# --- Load .env (best-effort; tolerate non-strict shell syntax) ---
if [ -f "$PROJECT_DIR/.env" ]; then
    set +u
    set -a
    # shellcheck disable=SC1091
    . "$PROJECT_DIR/.env" || true
    set +a
    set -u
fi

SCHEDULE="${BACKUP_S3_SCHEDULE:-0 2 * * *}"
CRON_USER="${BACKUP_S3_CRON_USER:-root}"

# --- Validation ---
if [ "$(id -u)" -ne 0 ]; then
    echo "ERROR: run with sudo (need write access to /etc/cron.d/)." >&2
    exit 1
fi

if [ ! -f "$PROJECT_DIR/docker-compose.yml" ]; then
    echo "ERROR: $PROJECT_DIR/docker-compose.yml not found." >&2
    exit 1
fi

# Detect compose command. `docker compose` works whether docker is real or the
# podman-docker shim; fall back to `podman-compose` on podman-only hosts without
# the shim.
if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
    COMPOSE_CMD="docker compose"
elif command -v podman-compose >/dev/null 2>&1; then
    COMPOSE_CMD="podman-compose"
else
    echo "ERROR: no compose command found (tried 'docker compose', 'podman-compose')." >&2
    echo "       Install docker compose v2 plugin or podman-compose." >&2
    exit 1
fi

if [ -z "$SCHEDULE" ]; then
    echo "ERROR: BACKUP_S3_SCHEDULE is empty in .env." >&2
    exit 1
fi

# --- Summary ---
echo "Installing cron entry at $CRON_FILE"
echo "  schedule    : $SCHEDULE"
echo "  user        : $CRON_USER"
echo "  project dir : $PROJECT_DIR"
echo "  compose cmd : $COMPOSE_CMD"
echo "  log file    : $LOG_FILE"

# --- Write cron entry ---
# COMPOSE_PROFILES=backup activates the backup-worker service profile
# (Compose spec standard — works with docker compose v2 and podman-compose).
cat > "$CRON_FILE" <<EOF
# DockerCart S3 backup — managed by install-backup-cron.sh
# Do not edit manually; re-run: sudo ./install-backup-cron.sh
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

$SCHEDULE $CRON_USER cd "$PROJECT_DIR" && COMPOSE_PROFILES=backup $COMPOSE_CMD run --rm --no-deps backup-worker >> "$LOG_FILE" 2>&1
EOF

chmod 644 "$CRON_FILE"

# Ensure log file exists and is writable by the cron user
mkdir -p "$PROJECT_DIR/storage/logs"
touch "$LOG_FILE"
chmod 666 "$LOG_FILE" 2>/dev/null || true

# Cron on most distros picks up files from /etc/cron.d/ automatically (no reload
# needed). We try to nudge systemd cron service if present, but stay quiet on failure.
if command -v systemctl >/dev/null 2>&1; then
    for svc in cron crond; do
        if systemctl is-active "$svc" >/dev/null 2>&1; then
            systemctl reload "$svc" 2>/dev/null || systemctl restart "$svc" 2>/dev/null || true
            break
        fi
    done
fi

echo ""
echo "Installed. Backups will run on schedule: $SCHEDULE"
echo "Logs: $LOG_FILE"
echo "Manual test: make backup-s3"
echo "Uninstall: sudo rm $CRON_FILE"
