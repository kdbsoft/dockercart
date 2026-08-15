#!/bin/bash
# Health check script for DockerCart Docker environment
# Checks the current stack: containers, HTTP health endpoint, DB, PHP, storage.

# NOTE: no `set -e` here — failed checks must not abort the run, the summary
# at the end decides the exit code.

# Load runtime configuration from .env (best-effort)
if [ -f .env ]; then
    set -a
    set +e
    # shellcheck disable=SC1091
    . ./.env
    set -e
    set +a
fi

DOCKERCART_HTTP_PORT=${DOCKERCART_HTTP_PORT:-80}
DOCKERCART_DOMAIN=${DOCKERCART_DOMAIN:-dockercart.local}
DOCKERCART_URL=${DOCKERCART_URL:-http://${DOCKERCART_DOMAIN}}
HEALTHCHECK_HOST=${HEALTHCHECK_HOST:-127.0.0.1}
HEALTHCHECK_TOKEN=${HEALTHCHECK_TOKEN:-}
MARIADB_USER=${MARIADB_USER:-${DB_USERNAME:-dockercart}}
MARIADB_PASSWORD=${MARIADB_PASSWORD:-${DB_PASSWORD:-dockercart_password}}
MARIADB_DATABASE=${MARIADB_DATABASE:-${DB_DATABASE:-dockercart}}

BLUE='\033[0;34m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${BLUE}╔══════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   DockerCart Health Check                    ║${NC}"
echo -e "${BLUE}╚══════════════════════════════════════════════╝${NC}"
echo ""

PASSED=0
FAILED=0

check() {
    local name=$1
    local command=$2

    echo -n "  Checking: $name... "

    if eval "$command" > /dev/null 2>&1; then
        echo -e "${GREEN}✓${NC}"
        ((PASSED++))
        return 0
    else
        echo -e "${RED}✗${NC}"
        ((FAILED++))
        return 1
    fi
}

# Check Docker
echo -e "${YELLOW}🐳 Docker Check${NC}"
check "Docker installed" "command -v docker"
check "Docker running" "docker info"
check "Docker Compose installed" "command -v docker compose"
echo ""

# Check containers (mode-agnostic: uses docker ps by name, so it works in
# standalone and Traefik modes regardless of which compose files started the
# stack)
echo -e "${YELLOW}📦 Container Check${NC}"
check "nginx" "docker ps --filter name=^/${NGINX_CONTAINER_NAME:-${COMPOSE_PROJECT_NAME:-dockercart}_nginx}\$ --format '{{.Status}}' | grep -q 'Up'"
check "apache" "docker ps --filter name=^/${APACHE_CONTAINER_NAME:-${COMPOSE_PROJECT_NAME:-dockercart}_apache}\$ --format '{{.Status}}' | grep -q 'Up'"
check "mariadb" "docker ps --filter name=^/${MARIADB_CONTAINER_NAME:-${COMPOSE_PROJECT_NAME:-dockercart}_mariadb}\$ --format '{{.Status}}' | grep -q 'Up'"
check "redis" "docker ps --filter name=^/${REDIS_CONTAINER_NAME:-${COMPOSE_PROJECT_NAME:-dockercart}_redis}\$ --format '{{.Status}}' | grep -q 'Up'"
check "manticore" "docker ps --filter name=^/${MANTICORE_CONTAINER_NAME:-${COMPOSE_PROJECT_NAME:-dockercart}_manticore}\$ --format '{{.Status}}' | grep -q 'Up'"
check "scheduler" "docker ps --filter name=^/${SCHEDULER_CONTAINER_NAME:-${COMPOSE_PROJECT_NAME:-dockercart}_scheduler}\$ --format '{{.Status}}' | grep -q 'Up'"
echo ""

# Check HTTP health endpoint (proxies to apache's healthcheck.php)
# In Traefik mode the store port is whatever the router publishes — pick it
# up from DOCKERCART_URL (e.g. http://shop.example:8080) when present.
echo -e "${YELLOW}🔌 HTTP Health Check${NC}"
HEALTH_URL_PORT="$(printf '%s' "${DOCKERCART_URL}" | sed -nE 's|^https?://[^/:]+:([0-9]+).*|\1|p')"
if [ -n "${HEALTH_URL_PORT}" ]; then
    DOCKERCART_HTTP_PORT="${HEALTH_URL_PORT}"
fi
HEALTH_URL_HOST="$(printf '%s' "${DOCKERCART_URL}" | sed -nE 's|^https?://([^/:]+).*|\1|p')"
HEALTH_CMD="curl -sf -H \"Host: ${HEALTH_URL_HOST}\" \"http://${HEALTHCHECK_HOST}:${DOCKERCART_HTTP_PORT}/health\""
if [ -n "${HEALTHCHECK_TOKEN}" ]; then
    HEALTH_CMD="${HEALTH_CMD} -H \"X-Healthcheck-Token: ${HEALTHCHECK_TOKEN}\""
fi
check "HTTP /health (${DOCKERCART_URL})" "${HEALTH_CMD}"
echo ""

# Check MariaDB
echo -e "${YELLOW}💾 MariaDB Check${NC}"
check "MariaDB responding" "docker compose exec -T -e MYSQL_PWD=\"$MARIADB_PASSWORD\" mariadb mariadb-admin ping -h 127.0.0.1 -u \"$MARIADB_USER\" --silent"
check "Database ${MARIADB_DATABASE} exists" "docker compose exec -T -e MYSQL_PWD=\"$MARIADB_PASSWORD\" mariadb mariadb -u \"$MARIADB_USER\" -e \"USE \\\`$MARIADB_DATABASE\\\`\""
echo ""

# Check PHP
echo -e "${YELLOW}🐘 PHP Check${NC}"
check "PHP running" "docker compose exec -T apache php -v"
check "PHP extension mysqli" "docker compose exec -T apache php -m | grep -q mysqli"
check "PHP extension gd" "docker compose exec -T apache php -m | grep -q gd"
check "PHP extension redis" "docker compose exec -T apache php -m | grep -q redis"
echo ""

# Check files & permissions
echo -e "${YELLOW}📁 File Check${NC}"
check "Webroot present" "docker compose exec -T apache test -d /var/www/html"
check "index.php present" "docker compose exec -T apache test -f /var/www/html/index.php"
check "Storage present" "docker compose exec -T apache test -d /var/www/storage"
check "Storage writable" "docker compose exec -T apache su -s /bin/sh www-data -c 'test -w /var/www/storage'"
check "Session dir writable" "docker compose exec -T apache su -s /bin/sh www-data -c 'test -w /var/www/storage/session'"
echo ""

# Summary
echo -e "${BLUE}══════════════════════════════════════════════${NC}"
echo -e "Passed: ${GREEN}${PASSED}${NC}   Failed: ${RED}${FAILED}${NC}"
echo -e "${BLUE}══════════════════════════════════════════════${NC}"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ All checks passed successfully!${NC}"
    echo -e "${GREEN}DockerCart is ready to use.${NC}"
    echo ""
    echo -e "${BLUE}Access:${NC}"
    echo -e "  Store: ${GREEN}${DOCKERCART_URL}${NC}"
    echo -e "  Admin: ${GREEN}${DOCKERCART_URL%/}/admin${NC}"
    exit 0
else
    echo -e "${YELLOW}⚠️  Some checks failed.${NC}"
    echo -e "${YELLOW}Check logs: ${GREEN}docker compose logs -f${NC}"
    exit 1
fi
