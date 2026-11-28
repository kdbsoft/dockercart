#!/bin/bash
# Health check script for DockerCart Docker environment
# Checks the current stack: containers, HTTP health endpoint, DB, PHP, storage.

# NOTE: no `set -e` here — failed checks must not abort the run, the summary
# at the end decides the exit code.

# Load runtime configuration from .env (best-effort)
if [ -f .env ]; then
    # shellcheck disable=SC1091
    . ./scripts/load-env.sh
    load_env .env
fi

DOCKERCART_HTTP_PORT=${DOCKERCART_HTTP_PORT:-80}
DOCKERCART_HTTPS_PORT=${DOCKERCART_HTTPS_PORT:-443}
DOCKERCART_DOMAIN=${DOCKERCART_DOMAIN:-dockercart.local}

# Same derivation as the container (docker/store-url.sh) — the probe must hit the
# URL the store is really configured with, not a placeholder from the template.
# shellcheck source=./docker/store-url.sh
. ./docker/store-url.sh
resolve_store_urls
DOCKERCART_URL="$(store_effective_url)"
HEALTHCHECK_HOST=${HEALTHCHECK_HOST:-127.0.0.1}
HEALTHCHECK_TOKEN=${HEALTHCHECK_TOKEN:-}
MARIADB_USER=${MARIADB_USER:-${DB_USERNAME:-dockercart}}
MARIADB_PASSWORD=${MARIADB_PASSWORD:-${DB_PASSWORD:-dockercart_password}}
MARIADB_DATABASE=${MARIADB_DATABASE:-${DB_DATABASE:-dockercart}}

# Container names follow the same defaulting chain as start.sh/Makefile.
PROJECT_NAME=${COMPOSE_PROJECT_NAME:-dockercart}
NGINX_CT=${NGINX_CONTAINER_NAME:-${PROJECT_NAME}_nginx}
APACHE_CT=${APACHE_CONTAINER_NAME:-${PROJECT_NAME}_apache}
TRAEFIK_CT=${TRAEFIK_CONTAINER_NAME:-traefik}
CERT_PROBE_PORT=${CERT_PROBE_PORT:-443}

# Shared ACME resolver discovery (scripts/traefik-resolver.sh).
# shellcheck source=./scripts/traefik-resolver.sh
. ./scripts/traefik-resolver.sh

# Traefik terminates TLS in the `--traefik --le` mode only; the mode is recorded
# in .env by start.sh (DOCKERCART_COMPOSE_FILES), so the certificate and
# resolver checks stay silent for standalone/HTTP stacks.
TRAEFIK_LE=false
case " ${DOCKERCART_COMPOSE_FILES:-} " in
    *'traefik.le'*) TRAEFIK_LE=true ;;
esac

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

# Edge certificate probe command (eval'd by check(), hence no function): the
# subject must exist and must not be Traefik's default self-signed certificate.
# Traefik serves that one until ACME succeeds, whether the resolver name is
# wrong or the HTTP-01 challenge fails, and Cloudflare reports it as 526.
EDGE_CERT_CMD="openssl s_client -connect 127.0.0.1:${CERT_PROBE_PORT} -servername ${DOCKERCART_DOMAIN} </dev/null 2>/dev/null | openssl x509 -noout -subject 2>/dev/null | grep -qv 'TRAEFIK DEFAULT CERT'"

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

# Restart loops: a crash-looping container still reports "Up ... (health:
# starting)" between restarts, so the status checks above cannot see it. nginx
# is the one that loops when its log bind mount is not writable by root or a
# rendered config is invalid; RestartCount is the honest signal.
echo -e "${YELLOW}🔁 Restart Loop Check${NC}"
check "nginx not restarting (RestartCount=0)" "[ \"\$(docker inspect -f '{{.RestartCount}}' ${NGINX_CT} 2>/dev/null)\" = '0' ]"
check "apache not restarting (RestartCount=0)" "[ \"\$(docker inspect -f '{{.RestartCount}}' ${APACHE_CT} 2>/dev/null)\" = '0' ]"
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

# Edge certificate + ACME resolver (Traefik --le mode only). Traefik serves its
# own default self-signed certificate until ACME succeeds — a mismatch between
# the router's tls.certresolver label and the proxy's certificatesResolvers, or a
# failing HTTP-01 challenge, both surface as Cloudflare error 526.
if [ "$TRAEFIK_LE" = "true" ]; then
    echo -e "${YELLOW}🔐 Edge Certificate Check (Traefik + Let's Encrypt)${NC}"

    RESOLVER_WANT="${TRAEFIK_CERTRESOLVER:-letsencrypt}"
    RESOLVERS_FOUND="$(detect_traefik_resolvers)"
    if [ -n "$RESOLVERS_FOUND" ]; then
        # shellcheck disable=SC2086 # newline-separated list, word splitting intended
        echo -e "  ACME resolvers on the running Traefik: $(printf '%s ' $RESOLVERS_FOUND)"
    fi
    check "TRAEFIK_CERTRESOLVER '${RESOLVER_WANT}' exists on Traefik" "check_traefik_resolver '${RESOLVER_WANT}'"
    check "no 'nonexistent certificate resolver' in Traefik logs" "! docker logs ${TRAEFIK_CT} 2>&1 | grep -qi 'nonexistent certificate resolver'"

    if command -v openssl >/dev/null 2>&1; then
        echo -e "  Probing https://127.0.0.1:${CERT_PROBE_PORT} (SNI ${DOCKERCART_DOMAIN})"
        check "certificate for ${DOCKERCART_DOMAIN} is served (not Traefik default)" "${EDGE_CERT_CMD}"
    else
        echo -e "${YELLOW}  openssl not installed — certificate probe skipped${NC}"
    fi
    echo ""
fi

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
