#!/bin/bash
# DockerCart - install the host-side logrotate rule for the nginx container.
#
# The nginx access/error logs are bind-mounted out of the container into
# ./docker/nginx/logs (see docker-compose*.yml). Without a rotation rule that
# directory grows without bound: every request writes a line and bot traffic
# alone can produce millions of them.
#
# This script renders docker/logrotate/nginx with the absolute log path and the
# nginx container name of THIS checkout, installs it as
# /etc/logrotate.d/dockercart-nginx and validates it with `logrotate -d`.
# Run it as root, or via `make logrotate-install` (which uses sudo if needed).
#
# Usage:
#   sudo ./scripts/install-nginx-logrotate.sh
#   ./scripts/install-nginx-logrotate.sh --dry-run    # print, do not install
#
# Idempotent: re-running regenerates the file (e.g. after moving the checkout).

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "$0")" && pwd)"
REPO_DIR="$(cd -- "$SCRIPT_DIR/.." && pwd)"

TEMPLATE="$REPO_DIR/docker/logrotate/nginx"
LOG_DIR="$REPO_DIR/docker/nginx/logs"
TARGET="/etc/logrotate.d/dockercart-nginx"

dry_run=0
case "${1:-}" in
	--dry-run | -n) dry_run=1 ;;
	'') : ;;
	*)
		echo "Usage: $0 [--dry-run]" >&2
		exit 2
		;;
esac

[ -f "$TEMPLATE" ] || {
	echo "ERROR: template not found: $TEMPLATE" >&2
	exit 1
}

# Load .env (KEY=value only) so a custom NGINX_CONTAINER_NAME /
# COMPOSE_PROJECT_NAME is honoured. A missing .env is fine: compose falls back
# to the same defaults used below.
if [ -f "$REPO_DIR/.env" ]; then
	# shellcheck source=scripts/load-env.sh
	. "$SCRIPT_DIR/load-env.sh"
	load_env "$REPO_DIR/.env"
fi

container="${NGINX_CONTAINER_NAME:-${COMPOSE_PROJECT_NAME:-dockercart}_nginx}"

# Prefer docker, fall back to podman (some hosts only ship podman).
runtime="docker"
if ! command -v docker >/dev/null 2>&1; then
	if command -v podman >/dev/null 2>&1; then
		runtime="podman"
	else
		echo "WARNING: neither docker nor podman found in PATH; the postrotate hook will not work." >&2
	fi
fi

rendered="$(sed -e "s|__LOG_DIR__|$LOG_DIR|g" \
	-e "s|__NGINX_CONTAINER__|$container|g" \
	-e "s|__RUNTIME__|$runtime|g" \
	"$TEMPLATE")"

if [ "$dry_run" -eq 1 ]; then
	printf '%s\n' "$rendered"
	exit 0
fi

mkdir -p "$LOG_DIR"

sudo_cmd=""
if [ "$(id -u)" -ne 0 ]; then
	if command -v sudo >/dev/null 2>&1; then
		sudo_cmd="sudo"
	else
		echo "ERROR: must run as root (or install sudo) to write $TARGET" >&2
		exit 1
	fi
fi

printf '%s\n' "$rendered" | $sudo_cmd tee "$TARGET" >/dev/null
$sudo_cmd chmod 0644 "$TARGET"

echo "Installed $TARGET"
echo "  log directory : $LOG_DIR"
echo "  container     : $container (via $runtime)"

# Parses the config and reports what would be rotated; touches no file and
# does not run the postrotate hook.
if ! $sudo_cmd logrotate --debug "$TARGET"; then
	echo "WARNING: logrotate reported a problem with $TARGET (see output above)." >&2
	exit 1
fi

echo "Done. Host logrotate.timer rotates the files daily (7 rotations, 100M cap);"
echo "no cron entry inside the nginx container is needed."
