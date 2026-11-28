#!/bin/bash
# shellcheck shell=bash
# shellcheck source=./scripts/configure-env.sh
# DockerCart - Simple Start Script
# Usage: ./start.sh [options]
#   ./start.sh                    - Standalone HTTP (default)
#   ./start.sh --ssl              - Standalone HTTPS (self-signed)
#   ./start.sh --le               - Standalone HTTPS (Let's Encrypt)
#   ./start.sh --traefik          - Traefik HTTP
#   ./start.sh --traefik --ssl    - Traefik HTTPS (self-signed)
#   ./start.sh --traefik --le     - Traefik HTTPS (Let's Encrypt)

set -e

BLUE='\033[0;34m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${BLUE}╔══════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   DockerCart Platform                       ║${NC}"
echo -e "${BLUE}╚══════════════════════════════════════════════╝${NC}"
echo ""

# ============================================================================
# DEFAULTS
# ============================================================================

COMPOSE_FILES=("-f" "docker-compose.yml")
TRAEFIK_MODE=false
SSL_MODE="none"

# ============================================================================
# PARSE OPTIONS
# ============================================================================

while [[ $# -gt 0 ]]; do
    case $1 in
        --traefik)
            TRAEFIK_MODE=true
            shift
            ;;
        --ssl)
            SSL_MODE="self-signed"
            shift
            ;;
        --le)
            SSL_MODE="letsencrypt"
            shift
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            echo "Usage: $0 [--traefik] [--ssl|--le]"
            exit 1
            ;;
    esac
done

if [ "$TRAEFIK_MODE" = true ]; then
    COMPOSE_FILES=("-f" "docker-compose.traefik.yml")
fi

# ============================================================================
# PREREQUISITES
# ============================================================================

if ! command -v docker &> /dev/null || ! command -v docker compose &> /dev/null; then
    echo -e "${RED}❌ Docker or Docker Compose not found${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Docker & Docker Compose${NC}"
echo ""

# ============================================================================
# SETUP
# ============================================================================

# First-run configuration: creates .env from .env.example. The interactive
# wizard (scripts/configure-env.sh) asks only for the store domain and the
# admin email; everything else (timezone, project name, ports, admin username,
# seed mode) gets a sane default, and all passwords are generated randomly.
# Non-interactive runs (CI, no TTY) silently copy the template and generate
# random passwords. Re-run with DOCKERCART_ENV_FORCE_WIZARD=1 to redo it.
if [ ! -f .env ] || [ "${DOCKERCART_ENV_FORCE_WIZARD:-0}" = "1" ]; then
    . ./scripts/configure-env.sh
fi

# Load the shared project-name sanitizer (also used by the wizard above).
# shellcheck disable=SC1091
. ./scripts/project-name.sh

# Traefik ACME resolver discovery/verification (shared with health-check.sh).
# A resolver name that the external Traefik does not know is a silent failure:
# no certificate is ever issued and the proxy serves its default self-signed one.
# shellcheck disable=SC1091
. ./scripts/traefik-resolver.sh

if [ -f .env ]; then
    echo -e "${YELLOW}Loading .env variables...${NC}"
    # shellcheck disable=SC1091
    . ./scripts/load-env.sh
    load_env .env
fi

# ============================================================================
# DERIVE STORE URL(S) FROM DOMAIN + LISTEN PORT (fallback only)
# ============================================================================
# DOCKERCART_URL / DOCKERCART_HTTPS_URL are the source of truth for the app
# URL (config.php, config_url/config_ssl in the DB, robots.txt seed sitemap, Traefik
# labels). They are usually derived from DOCKERCART_DOMAIN + the listen ports,
# but an explicitly set URL (e.g. http://shop.local:8080) must win — .env is
# operator-owned and start.sh never writes to it. Derivation runs only when the
# URL keys are absent (fresh .env from the template, or a config lacking them).
# Port 80/443 are the defaults and are omitted from the URL.
# The flag records whether these two values came from the derivation (they are
# recomputed if the operator sets a different domain later for Let's Encrypt).
DOCKERCART_URL_DERIVED=false
if [ -n "${DOCKERCART_DOMAIN:-}" ] && [ -z "${DOCKERCART_URL:-}" ]; then
    DOCKERCART_URL_DERIVED=true
    if [ -z "${DOCKERCART_HTTP_PORT:-}" ]; then
        DOCKERCART_HTTP_PORT=80
    fi
    if [ -z "${DOCKERCART_HTTPS_PORT:-}" ]; then
        DOCKERCART_HTTPS_PORT=443
    fi
    DOCKERCART_URL_PORT_SUFFIX=""
    if [ "${DOCKERCART_HTTP_PORT}" != "80" ]; then
        DOCKERCART_URL_PORT_SUFFIX=":${DOCKERCART_HTTP_PORT}"
    fi
    DOCKERCART_HTTPS_URL_PORT_SUFFIX=""
    if [ "${DOCKERCART_HTTPS_PORT}" != "443" ]; then
        DOCKERCART_HTTPS_URL_PORT_SUFFIX=":${DOCKERCART_HTTPS_PORT}"
    fi
    export DOCKERCART_URL="http://${DOCKERCART_DOMAIN}${DOCKERCART_URL_PORT_SUFFIX}"
    export DOCKERCART_HTTPS_URL="https://${DOCKERCART_DOMAIN}${DOCKERCART_HTTPS_URL_PORT_SUFFIX}"
fi

echo ""

# ============================================================================
# SEED MODE (only on fresh install: no existing DB volume)
# ============================================================================
# The seed choice lives in .env as DOCKERCART_SEED_MODE (see .env.example;
# default "demo", use "clean" for an empty store). Here we only read it back;
# no interactive prompt. On a fresh database volume with no seed key recorded,
# default to demo data. Edit .env and run `make restart` to change the choice.

SEED_MODE="${DOCKERCART_SEED_MODE:-demo}"
DB_VOLUME_NAME="${DB_VOLUME_NAME:-$(sanitize_project_name "${COMPOSE_PROJECT_NAME:-$(basename "$PWD")}")_mariadb-data}"

# Detect whether a database volume already exists (i.e. this is NOT a first run)
if docker volume inspect "${DB_VOLUME_NAME}" >/dev/null 2>&1; then
    echo -e "${GREEN}✓ Database volume exists — skipping install prompt${NC}"
elif [ -n "${DOCKERCART_SEED_MODE:-}" ]; then
    echo -e "${YELLOW}Using DOCKERCART_SEED_MODE=${SEED_MODE} from environment${NC}"
else
    echo -e "${YELLOW}No seed mode recorded — defaulting to demo data${NC}"
fi

echo ""

# ============================================================================
# SSL SETUP
# ============================================================================

# --- Let's Encrypt identity -------------------------------------------------
# The setup wizard asks for exactly two things: DOCKERCART_DOMAIN and
# ADMIN_EMAIL. Let's Encrypt therefore never asks for them a second time:
# SSL_DOMAIN / SSL_EMAIL are OPTIONAL overrides that fall back to those two
# values when unset or left at their .env.example placeholders.
resolve_ssl_identity() {
    if [ -z "${SSL_DOMAIN:-}" ] || [ "${SSL_DOMAIN}" = "example.com" ]; then
        SSL_DOMAIN="${DOCKERCART_DOMAIN:-}"
    fi
    if [ -z "${SSL_EMAIL:-}" ] || [ "${SSL_EMAIL}" = "admin@example.com" ]; then
        SSL_EMAIL="${ADMIN_EMAIL:-}"
    fi
    export SSL_DOMAIN SSL_EMAIL
}

# ACME validation needs a publicly resolvable FQDN. Local-only names (the
# wizard default dockercart.local, RFC 2606 TLDs) can never pass HTTP-01 —
# detecting that here turns a cryptic certbot DNS timeout into a clear message.
is_acme_domain() {
    case "$1" in
        '' | localhost | *[!A-Za-z0-9.-]* | *.local | *.localhost | *.internal | \
            *.test | *.invalid | *.example) return 1 ;;
    esac
    printf '%s' "$1" | grep -q '\.' || return 1
    return 0
}

# Ask for a publicly resolvable domain while the operator is still looking at
# the terminal, instead of aborting with "edit .env and re-run". Returns 0 when
# a usable domain is in place (either given now or already configured), 1 when
# the operator aborted (Ctrl-D) or the run is non-interactive.
ask_public_domain() {
    local current="$1" proposed
    [ "${DOCKERCART_NONINTERACTIVE:-0}" = "1" ] && return 1
    [ -t 0 ] || return 1

    echo ""
    echo -e "${YELLOW}The certificate needs a public domain (DNS pointing at this host).${NC}"
    echo -e "${YELLOW}Current: ${current:-<empty>}${NC}"
    while true; do
        printf 'Public domain for the certificate [%s]: ' "${current:-none}"
        if ! IFS= read -r proposed; then
            return 1
        fi
        proposed="${proposed%%[[:space:]]}"
        [ -z "$proposed" ] && proposed="$current"
        if is_acme_domain "$proposed"; then
            break
        fi
        echo -e "${RED}That is not a public domain (local-only names cannot pass ACME).${NC}"
        echo -e "${RED}Enter a real domain, or Ctrl-D to abort.${NC}"
    done
    [ "$proposed" = "$current" ] && return 0

    # Persist so `make restart` / the next start use the same host, then apply
    # it to this run. DOCKERCART_URL / DOCKERCART_HTTPS_URL are operator-owned
    # (start.sh never rewrites an explicit URL) — only a derived pair follows.
    DOCKERCART_DOMAIN="$proposed"
    SSL_DOMAIN="$proposed"
    export DOCKERCART_DOMAIN SSL_DOMAIN
    if grep -qE '^DOCKERCART_DOMAIN=' .env 2>/dev/null; then
        sed -i "s|^DOCKERCART_DOMAIN=.*|DOCKERCART_DOMAIN=${DOCKERCART_DOMAIN}|" .env
    else
        printf '\n# Public store domain (written by start.sh for Let'\''s Encrypt)\nDOCKERCART_DOMAIN=%s\n' "$DOCKERCART_DOMAIN" >> .env
    fi
    echo -e "${GREEN}✓ Domain: ${DOCKERCART_DOMAIN} (saved to .env)${NC}"

    if [ "$DOCKERCART_URL_DERIVED" = "true" ]; then
        export DOCKERCART_URL="http://${DOCKERCART_DOMAIN}${DOCKERCART_URL_PORT_SUFFIX:-}"
        export DOCKERCART_HTTPS_URL="https://${DOCKERCART_DOMAIN}${DOCKERCART_HTTPS_URL_PORT_SUFFIX:-}"
    elif [ -n "${DOCKERCART_URL:-}" ] && ! printf '%s' "$DOCKERCART_URL" | grep -Fq "$DOCKERCART_DOMAIN"; then
        # An explicit URL wins (operator-owned), so it is NOT rewritten — say so
        # instead of leaving a silently stale app URL behind.
        echo -e "${YELLOW}ℹ DOCKERCART_URL in .env is '${DOCKERCART_URL}' and was left untouched.${NC}"
        echo -e "${YELLOW}  Update it if the store itself must be served on ${DOCKERCART_DOMAIN}.${NC}"
    fi
    return 0
}

if [ "$SSL_MODE" = "letsencrypt" ]; then
    resolve_ssl_identity

    if [ "$TRAEFIK_MODE" = true ]; then
        # Traefik runs its own ACME client and takes the router host from
        # DOCKERCART_DOMAIN (docker-compose.traefik.le.yml); certbot is never
        # used and SSL_DOMAIN/SSL_EMAIL are irrelevant here.
        LE_HOST="${DOCKERCART_DOMAIN:-dockercart.local}"
        echo -e "${YELLOW}Let's Encrypt setup (Traefik certresolver)${NC}"
        if ! is_acme_domain "$LE_HOST"; then
            if ! ask_public_domain "$LE_HOST"; then
                echo -e "${YELLOW}⚠ DOCKERCART_DOMAIN='${LE_HOST}' is not publicly resolvable —"
                echo -e "${YELLOW}  Traefik cannot validate it. Set a real domain in .env before going live.${NC}"
            fi
            LE_HOST="${DOCKERCART_DOMAIN:-$LE_HOST}"
        fi
        echo -e "${GREEN}✓ Domain: ${LE_HOST}${NC}"

        # Verify BEFORE touching the stack: a router pointing at a resolver that
        # the external Traefik does not define never receives a certificate, and
        # the failure only shows up later as a browser warning / Cloudflare 526.
        # Escape hatch for unusual setups (Traefik behind a socket proxy that
        # hides its args): SKIP_TRAEFIK_RESOLVER_CHECK=1.
        if [ "${SKIP_TRAEFIK_RESOLVER_CHECK:-0}" != "1" ]; then
            if ! check_traefik_resolver "${TRAEFIK_CERTRESOLVER:-letsencrypt}"; then
                echo -e "${RED}❌ Not starting: the router would ask for a certificate from a${NC}"
                echo -e "${RED}   resolver that does not exist. Set TRAEFIK_CERTRESOLVER in .env${NC}"
                echo -e "${RED}   to a name listed above (or fix Traefik's static config), then${NC}"
                echo -e "${RED}   re-run. Skip this preflight with SKIP_TRAEFIK_RESOLVER_CHECK=1.${NC}"
                exit 1
            fi
        else
            echo -e "${YELLOW}⚠ Traefik resolver check skipped (SKIP_TRAEFIK_RESOLVER_CHECK=1)${NC}"
        fi
        echo ""
    else
        echo -e "${YELLOW}Let's Encrypt setup${NC}"
        if ! is_acme_domain "${SSL_DOMAIN}" && ! ask_public_domain "${SSL_DOMAIN}"; then
            echo -e "${RED}❌ Standalone Let's Encrypt needs a public domain, got '${SSL_DOMAIN:-<empty>}'${NC}"
            echo ""
            echo "Set the store domain in .env (it is used for the app URL and the"
            echo "certificate alike) and point its DNS A/AAAA record at this host:"
            echo "  DOCKERCART_DOMAIN=your-domain.com"
            echo "  ADMIN_EMAIL=admin@your-domain.com"
            echo ""
            echo "SSL_DOMAIN / SSL_EMAIL are optional overrides when the certificate"
            echo "must use a different name than the store domain."
            exit 1
        fi
        if [ -z "${SSL_EMAIL}" ]; then
            echo -e "${RED}❌ No ACME account email — set ADMIN_EMAIL (or SSL_EMAIL) in .env${NC}"
            exit 1
        fi
        echo -e "${GREEN}✓ Domain: ${SSL_DOMAIN}${NC}"
        echo -e "${GREEN}✓ ACME email: ${SSL_EMAIL}${NC}"
        echo ""
    fi
fi

if [ "$SSL_MODE" = "self-signed" ] && [ "$TRAEFIK_MODE" = false ]; then
    echo -e "${YELLOW}Generating self-signed certificate${NC}"

    if [ ! -f docker/ssl/certs/dockercart.crt ] || [ ! -f docker/ssl/private/dockercart.key ]; then
        mkdir -p docker/ssl/{certs,private}
        openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
            -keyout docker/ssl/private/dockercart.key \
            -out docker/ssl/certs/dockercart.crt \
            -subj "/C=US/ST=State/L=City/O=Organization/CN=${DOCKERCART_DOMAIN:-dockercart.local}" \
            2>/dev/null || true
        echo -e "${GREEN}✓ Certificate generated${NC}"
    fi
    echo ""
fi

# ============================================================================
# CONFIGURE DOCKER COMPOSE FILES BASED ON SSL MODE
# ============================================================================

case "$SSL_MODE" in
    "none")
        if [ "$TRAEFIK_MODE" = true ]; then
            COMPOSE_FILES+=("-f" "docker-compose.traefik.no-ssl.yml")
        fi
        echo -e "${YELLOW}Mode: HTTP (no SSL)${NC}"
        ;;
    "self-signed")
        if [ "$TRAEFIK_MODE" = true ]; then
            COMPOSE_FILES+=("-f" "docker-compose.traefik.ssl.yml")
            echo -e "${YELLOW}Mode: Traefik HTTPS with self-signed certificate${NC}"
        else
            COMPOSE_FILES+=("-f" "docker-compose.ssl.yml")
            echo -e "${YELLOW}Mode: Standalone HTTPS with self-signed certificate${NC}"
        fi
        ;;
    "letsencrypt")
        if [ "$TRAEFIK_MODE" = true ]; then
            COMPOSE_FILES+=("-f" "docker-compose.traefik.le.yml")
            echo -e "${YELLOW}Mode: Traefik HTTPS with Let's Encrypt${NC}"
        else
            echo -e "${YELLOW}Mode: Standalone HTTPS with Let's Encrypt${NC}"
        fi
        ;;
esac

if [ -n "${MARIADB_EXTERNAL_PORT:-}" ]; then
    COMPOSE_FILES+=("-f" "docker-compose.mariadb-port.yml")
    echo -e "${YELLOW}MariaDB external port enabled: ${MARIADB_EXTERNAL_PORT}${NC}"
fi
echo ""

# ============================================================================
# PERSIST ACTIVE COMPOSE FILE SET
# ============================================================================
# Record the exact -f file list used to start the stack so that subsequent
# `make restart` / `make stop` reconstruct the same mode (Traefik
# + SSL overrides) without an explicit mode argument. Without this, a traefik
# stack restarted via the base-only Makefile target silently falls back to
# standalone HTTP and loses Traefik labels / SSL.
if [ -f .env ]; then
    persist_compose_files() {
        local key="DOCKERCART_COMPOSE_FILES" files="" i
        for ((i = 1; i < ${#COMPOSE_FILES[@]}; i += 2)); do
            files="${files}${COMPOSE_FILES[$i]} "
        done
        # Collapse any stray newlines and strip trailing whitespace so the value
        # is always a single space-separated line (never a bare executable token).
        files="$(printf '%s' "$files" | tr -d '\n' | sed 's/[[:space:]]*$//')"
        if grep -qE "^${key}=" .env; then
            sed -i "s|^${key}=.*|${key}=${files}|" .env
        else
            printf '\n# Active compose file set (written by start.sh; read by make restart/stop/down)\n%s=%s\n' "$key" "$files" >> .env
        fi
    }
    persist_compose_files
fi

# ============================================================================
# LET'S ENCRYPT — CERTBOT SETUP (standalone mode only)
# ============================================================================

if [ "$SSL_MODE" = "letsencrypt" ] && [ "$TRAEFIK_MODE" = false ]; then
    echo -e "${YELLOW}Setting up Let's Encrypt certificates...${NC}"

    LE_DATA_DIR="${LETSENCRYPT_DATA_DIR:-./docker/letsencrypt}"
    LE_WEBROOT_DIR="${LETSENCRYPT_WEBROOT_DIR:-${LE_DATA_DIR}/www}"
    RENEW_INTERVAL="${CERTBOT_RENEW_INTERVAL:-24h}"

    mkdir -p "$LE_DATA_DIR" "$LE_WEBROOT_DIR"

    if [ -f "$LE_DATA_DIR/renewal/dockercart.conf" ] && [ ! -s "$LE_DATA_DIR/renewal/dockercart.conf" ]; then
        echo "Removing empty renewal config $LE_DATA_DIR/renewal/dockercart.conf"
        rm -f "$LE_DATA_DIR/renewal/dockercart.conf"
    fi

    if [ -d "$LE_DATA_DIR/live/dockercart" ] && [ ! -f "$LE_DATA_DIR/renewal/dockercart.conf" ] && [ ! -L "$LE_DATA_DIR/live/dockercart" ]; then
        echo "Removing stale bootstrap lineage $LE_DATA_DIR/live/dockercart"
        rm -rf "$LE_DATA_DIR/live/dockercart" "$LE_DATA_DIR/archive/dockercart"
    fi

    echo "Starting standalone HTTP stack for ACME webroot challenge..."
    DOCKERCART_SEED_MODE="${SEED_MODE}" docker compose "${COMPOSE_FILES[@]}" up -d --build

    ACTIVE_CERT_NAME="dockercart"
    VALID_CERT_NAME=""
    USABLE_CERT_NAME=""
    MATCHING_CERT_NAME=""

    for cert_path in "$LE_DATA_DIR"/live/*/fullchain.pem; do
        [ -f "$cert_path" ] || continue
        cert_name="${cert_path#"$LE_DATA_DIR"/live/}"
        cert_name="${cert_name%/fullchain.pem}"
        if command -v openssl >/dev/null 2>&1; then
            if ! openssl x509 -noout -ext subjectAltName -in "$cert_path" 2>/dev/null | tr -d ' ' | grep -Fq "DNS:${SSL_DOMAIN}"; then
                continue
            fi
        fi
        MATCHING_CERT_NAME="$cert_name"
        if [ -z "$USABLE_CERT_NAME" ] && command -v openssl >/dev/null 2>&1 && openssl x509 -checkend 0 -noout -in "$cert_path" >/dev/null 2>&1; then
            USABLE_CERT_NAME="$cert_name"
        fi
        if command -v openssl >/dev/null 2>&1 && openssl x509 -checkend 2592000 -noout -in "$cert_path" >/dev/null 2>&1; then
            VALID_CERT_NAME="$cert_name"
            break
        fi
    done

    if [ -n "$VALID_CERT_NAME" ]; then
        ACTIVE_CERT_NAME="$VALID_CERT_NAME"
    elif [ -n "$USABLE_CERT_NAME" ]; then
        ACTIVE_CERT_NAME="$USABLE_CERT_NAME"
    elif [ -n "$MATCHING_CERT_NAME" ]; then
        ACTIVE_CERT_NAME="$MATCHING_CERT_NAME"
    fi

    echo "Detected certificate lineage for ${SSL_DOMAIN}: $ACTIVE_CERT_NAME"

    if [ "$ACTIVE_CERT_NAME" != "dockercart" ] && [ -d "$LE_DATA_DIR/live/$ACTIVE_CERT_NAME" ] && [ ! -e "$LE_DATA_DIR/live/dockercart" ]; then
        echo "Linking nginx default cert path to existing lineage: $ACTIVE_CERT_NAME"
        ln -s "$ACTIVE_CERT_NAME" "$LE_DATA_DIR/live/dockercart"
    elif [ "$ACTIVE_CERT_NAME" != "dockercart" ] && [ -L "$LE_DATA_DIR/live/dockercart" ]; then
        current_target="$(readlink "$LE_DATA_DIR/live/dockercart" || true)"
        if [ "$current_target" != "$ACTIVE_CERT_NAME" ]; then
            echo "Updating nginx cert symlink: dockercart -> $ACTIVE_CERT_NAME"
            ln -snf "$ACTIVE_CERT_NAME" "$LE_DATA_DIR/live/dockercart"
        fi
    fi

    CERT_PATH="$LE_DATA_DIR/live/$ACTIVE_CERT_NAME/fullchain.pem"
    HAS_VALID_CERT=false
    if [ -f "$CERT_PATH" ] && command -v openssl >/dev/null 2>&1; then
        if openssl x509 -checkend 2592000 -noout -in "$CERT_PATH" >/dev/null 2>&1; then
            if openssl x509 -noout -ext subjectAltName -in "$CERT_PATH" 2>/dev/null | tr -d ' ' | grep -Fq "DNS:${SSL_DOMAIN}"; then
                HAS_VALID_CERT=true
            fi
        fi
    fi

    if [ "$HAS_VALID_CERT" = "true" ]; then
        echo "Existing certificate ($ACTIVE_CERT_NAME) is valid for more than 30 days — skipping new issuance."
    else
        echo "Requesting/renewing Let's Encrypt certificate for ${SSL_DOMAIN}..."
        CERTBOT_CERT_NAME="$ACTIVE_CERT_NAME"
        if [ ! -s "$LE_DATA_DIR/renewal/$CERTBOT_CERT_NAME.conf" ]; then
            CERTBOT_CERT_NAME=""
            for renewal_conf in "$LE_DATA_DIR"/renewal/*.conf; do
                [ -s "$renewal_conf" ] || continue
                if grep -Fq "${SSL_DOMAIN}" "$renewal_conf"; then
                    CERTBOT_CERT_NAME="${renewal_conf##*/}"
                    CERTBOT_CERT_NAME="${CERTBOT_CERT_NAME%.conf}"
                    break
                fi
            done
        fi
        if [ -z "$CERTBOT_CERT_NAME" ]; then
            CERTBOT_CERT_NAME="dockercart"
        fi
        echo "Using certbot cert-name: $CERTBOT_CERT_NAME"
        if ! docker compose -f docker-compose.yml -f docker-compose.le.yml run --rm --no-deps --entrypoint certbot certbot certonly \
            --webroot -w /var/www/certbot \
            --email "${SSL_EMAIL}" \
            --agree-tos \
            --no-eff-email \
            --non-interactive \
            --keep-until-expiring \
            --cert-name "$CERTBOT_CERT_NAME" \
            -d "${SSL_DOMAIN}"; then
            CAN_USE_EXISTING_CERT=false
            if [ -f "$CERT_PATH" ] && command -v openssl >/dev/null 2>&1; then
                if openssl x509 -checkend 0 -noout -in "$CERT_PATH" >/dev/null 2>&1; then
                    if openssl x509 -noout -ext subjectAltName -in "$CERT_PATH" 2>/dev/null | tr -d ' ' | grep -Fq "DNS:${SSL_DOMAIN}"; then
                        CAN_USE_EXISTING_CERT=true
                    fi
                fi
            fi
            if [ "$CAN_USE_EXISTING_CERT" = "true" ]; then
                echo "⚠️ Certificate request failed, but a non-expired matching certificate is present. Continuing with existing cert."
            else
                echo "❌ Certificate request failed and no usable existing certificate is available."
                exit 1
            fi
        fi
    fi

    echo "Switching stack to standalone HTTPS mode..."
    DOCKERCART_SEED_MODE="${SEED_MODE}" docker compose -f docker-compose.yml -f docker-compose.le.yml up -d --build

    # Persist standalone-LE mode (base + LE override) as the active file set so
    # `make restart` / `make stop` / `make update` reconstruct HTTPS mode
    # instead of falling back to the base-only HTTP set. The override stays out
    # of COMPOSE_FILES during the ACME HTTP phase above (its :443 TLS server
    # block would fail nginx startup while no certificate exists yet), so it is
    # appended only now — after the LE stack came up successfully.
    COMPOSE_FILES+=("-f" "docker-compose.le.yml")
    if [ -f .env ]; then
        persist_compose_files
    fi

    echo ""
    echo "Store: https://${SSL_DOMAIN}"
    echo "Admin: https://${SSL_DOMAIN}/admin"
    echo "HTTP challenge endpoint: http://${SSL_DOMAIN}/.well-known/acme-challenge/"
    echo "Auto-renewal: certbot service checks every $RENEW_INTERVAL (renews only near expiry)"
    echo ""
    # Skip the generic start below
    exit 0
fi

# ============================================================================
# START
# ============================================================================

echo -e "${BLUE}Starting containers...${NC}"
echo ""

# No `down` here: up --build recreates only the services whose config/image
# changed, so already-running services (mariadb, redis) stay up and the store
# does not experience a full-stack outage on every start/deploy.
# The chosen seed mode is passed for this run only — nothing is written to .env.
DOCKERCART_SEED_MODE="${SEED_MODE}" docker compose "${COMPOSE_FILES[@]}" up -d --build --remove-orphans

# ============================================================================
# BOUNDED READINESS WAIT
# ============================================================================
# `up -d` returns once containers are created, not when the app is usable.
# Wait for mariadb (healthy) and apache (healthy healthcheck) with a hard
# timeout so a broken stack fails loudly with diagnostics instead of hanging
# forever (e.g. podman-compose blocking on `condition: service_healthy` while
# mariadb stays unhealthy — which happens when .env contains nested ${VAR}
# references that podman-compose does not expand; keep .env flat, see
# .env.example). Tune with START_TIMEOUT (seconds, default 240).

PROJECT_NAME="$(sanitize_project_name "${COMPOSE_PROJECT_NAME:-$(basename "$PWD")}")"
MARIADB_CT="${MARIADB_CONTAINER_NAME:-${PROJECT_NAME}_mariadb}"
APACHE_CT="${APACHE_CONTAINER_NAME:-${PROJECT_NAME}_apache}"
NGINX_CT="${NGINX_CONTAINER_NAME:-${PROJECT_NAME}_nginx}"
START_TIMEOUT="${START_TIMEOUT:-240}"
# How long the edge may take to serve a real certificate (ACME is not instant).
CERT_TIMEOUT="${CERT_TIMEOUT:-60}"
# Host port probed for the served certificate. In Traefik mode this is the
# external Traefik's https entrypoint; 443 is omitted from the URL by design.
CERT_PROBE_PORT="${CERT_PROBE_PORT:-443}"

wait_for_healthy() {
    local name="$1" desc="$2"
    local deadline=$((SECONDS + START_TIMEOUT))
    while [ "$SECONDS" -lt "$deadline" ]; do
        local health
        health=$(docker inspect --format '{{.State.Health.Status}}' "$name" 2>/dev/null || true)
        if [ "$health" = "healthy" ]; then
            echo -e "${GREEN}✓ ${desc} is healthy${NC}"
            return 0
        fi
        sleep 3
    done
    return 1
}

echo -e "${YELLOW}Waiting for services to be ready (timeout ${START_TIMEOUT}s)...${NC}"
if ! wait_for_healthy "$MARIADB_CT" "MariaDB"; then
    echo -e "${RED}❌ MariaDB (${MARIADB_CT}) did not become healthy within ${START_TIMEOUT}s${NC}"
    echo -e "${RED}   Run 'docker logs ${MARIADB_CT} --tail 50' and check MARIADB_USER/MARIADB_PASSWORD in .env${NC}"
    docker inspect --format '  {{.Name}}: status={{.State.Status}} health={{.State.Health.Status}}' "$MARIADB_CT" 2>/dev/null || true
    exit 1
fi
if ! wait_for_healthy "$APACHE_CT" "Apache"; then
    echo -e "${RED}❌ Apache (${APACHE_CT}) did not become healthy within ${START_TIMEOUT}s${NC}"
    echo -e "${RED}   Run 'docker logs ${APACHE_CT} --tail 50' for the entrypoint/migration errors${NC}"
    docker inspect --format '  {{.Name}}: status={{.State.Status}} health={{.State.Health.Status}}' "$APACHE_CT" 2>/dev/null || true
    exit 1
fi
# nginx is where users land: without it the store answers 502/521 even though
# apache and mariadb are fine (e.g. its log bind mount is not writable).
if ! wait_for_healthy "$NGINX_CT" "nginx"; then
    echo -e "${RED}❌ nginx (${NGINX_CT}) did not become healthy within ${START_TIMEOUT}s${NC}"
    echo -e "${RED}   Run 'docker logs ${NGINX_CT} --tail 30' — a restart loop usually means${NC}"
    echo -e "${RED}   the config or the log/cache bind mounts are not usable by root.${NC}"
    docker inspect --format '  {{.Name}}: status={{.State.Status}} health={{.State.Health.Status}} restarts={{.RestartCount}}' "$NGINX_CT" 2>/dev/null || true
    docker logs "$NGINX_CT" --tail 30 2>&1 | sed 's/^/    /' || true
    exit 1
fi

# ============================================================================
# TRAEFIK + LET'S ENCRYPT — CERTIFICATE POST-CHECK
# ============================================================================
# Traefik requests the certificate on the first TLS handshake for the router
# host, so right after `up` the default certificate may still be served. A
# resolver-name mismatch and a failed HTTP-01 challenge look identical from
# here (Traefik's default self-signed cert), so read the certificate the edge
# actually presents and fail loudly instead of letting the operator discover it
# through a browser/Cloudflare 526.

# Raw TLS probe of the local edge for $1, empty when nothing listens.
probe_edge_cert() {
    local domain="$1"
    command -v openssl >/dev/null 2>&1 || return 0
    if command -v timeout >/dev/null 2>&1; then
        timeout 10 openssl s_client -connect "127.0.0.1:${CERT_PROBE_PORT}" \
            -servername "$domain" </dev/null 2>/dev/null
    else
        openssl s_client -connect "127.0.0.1:${CERT_PROBE_PORT}" \
            -servername "$domain" </dev/null 2>/dev/null
    fi
}

# `subject=CN = shop.example` of the served certificate, empty if unreachable.
edge_cert_subject() {
    probe_edge_cert "$1" | openssl x509 -noout -subject 2>/dev/null || true
}

# `notAfter=Jan  1 00:00:00 2027 GMT` of the served certificate.
edge_cert_enddate() {
    probe_edge_cert "$1" | openssl x509 -noout -enddate 2>/dev/null || true
}

# Wait up to CERT_TIMEOUT for a real certificate for $1.
#   returns 0 — issued, or the edge does not answer locally (nothing to judge
#               from this host: a warning is printed instead of a 60 s stall)
#   returns 1 — the edge keeps presenting Traefik's default self-signed cert
assert_edge_cert() {
    local domain="$1" deadline=$((SECONDS + CERT_TIMEOUT)) subject
    while [ "$SECONDS" -lt "$deadline" ]; do
        subject="$(edge_cert_subject "$domain")"
        if [ -z "$subject" ]; then
            echo -e "${YELLOW}⚠ Could not verify the certificate for ${domain}: nothing answered on 127.0.0.1:${CERT_PROBE_PORT}${NC}"
            echo -e "${YELLOW}  (Traefik on another host, or 443 not published here?) — check it in a browser.${NC}"
            return 0
        fi
        case "$subject" in
            *'TRAEFIK DEFAULT CERT'*) sleep 3 ;;
            *) echo -e "${GREEN}✓ Certificate for ${domain} is being served${NC}" ; return 0 ;;
        esac
    done
    echo -e "${RED}❌ Traefik still serves its DEFAULT self-signed certificate for ${domain}${NC}"
    echo -e "${RED}   Usual causes: TRAEFIK_CERTRESOLVER='${TRAEFIK_CERTRESOLVER:-letsencrypt}' is not a${NC}"
    echo -e "${RED}   resolver of the external Traefik, or the HTTP-01 challenge cannot reach it.${NC}"
    echo -e "${RED}   Inspect: docker logs <traefik> --tail 50 | grep -i 'certificate resolver\\|acme'${NC}"
    echo -e "${RED}   Behind Cloudflare this shows up as error 526 (SSL mode must be Full (strict)).${NC}"
    return 1
}

if [ "$SSL_MODE" = "letsencrypt" ] && [ "$TRAEFIK_MODE" = true ]; then
    if ! command -v openssl >/dev/null 2>&1; then
        echo -e "${YELLOW}⚠ openssl not installed — cannot verify the certificate Traefik serves${NC}"
    elif ! assert_edge_cert "${DOCKERCART_DOMAIN:-dockercart.local}"; then
        exit 1
    fi
fi

echo -e "${GREEN}✓ Containers started${NC}"
echo ""
echo -e "${BLUE}ℹ Migrations are applied automatically by the apache entrypoint${NC}"
echo -e "${BLUE}  (after the base schema from init.sql is imported). No separate${NC}"
echo -e "${BLUE}  step needed here — run 'make migrate' only to re-apply manually.${NC}"
echo ""

# ============================================================================
# STATUS & INFO
# ============================================================================

echo -e "${BLUE}📊 Status:${NC}"
docker compose "${COMPOSE_FILES[@]}" ps
echo ""

if [ "$TRAEFIK_MODE" = true ]; then
    echo -e "${GREEN}✅ DockerCart is running in Traefik mode!${NC}"
    echo ""
    echo -e "${BLUE}Production mode (Nginx proxy + Apache):${NC}"
    echo -e "  Network: ${GREEN}dockercart-network${NC}"
    echo -e "  Frontend: ${GREEN}Nginx (ports managed externally)${NC}"
    echo -e "  Backend:  ${GREEN}Apache (internal, port 80)${NC}"
    echo -e "  Database: ${GREEN}MariaDB (internal)${NC}"
    if [ "$SSL_MODE" = "letsencrypt" ]; then
        # Make the certificate state part of the start summary: "not issued" is
        # visible here instead of only in a browser / Cloudflare error page.
        CERT_END="$(edge_cert_enddate "${DOCKERCART_DOMAIN:-dockercart.local}" | sed 's/^notAfter=//')"
        if [ -n "$CERT_END" ]; then
            echo -e "  Cert:      ${GREEN}issued, valid until ${CERT_END}${NC}"
        else
            echo -e "  Cert:      ${YELLOW}not verified from this host (check your browser / Cloudflare)${NC}"
        fi
    fi
    echo ""
else
    echo -e "${GREEN}✅ DockerCart is running!${NC}"
    echo ""
    SITE_URL="${DOCKERCART_URL:-http://dockercart.local}"
    SITE_HOST="${DOCKERCART_DOMAIN:-dockercart.local}"
    DB_HOST_PRINT="${DB_HOSTNAME:-mariadb}"
    DB_PORT_PRINT="${DB_PORT:-3306}"

    echo -e "  Site:      ${GREEN}${SITE_URL}${NC}"
    echo -e "  Admin:     ${GREEN}${SITE_URL%/}/admin${NC}"
    echo -e "  MariaDB:   ${GREEN}${DB_HOST_PRINT}:${DB_PORT_PRINT}${NC}"
    if [ "$SSL_MODE" = "self-signed" ]; then
        echo -e "  HTTPS:     ${GREEN}https://${SITE_HOST} (warning: self-signed)${NC}"
    fi
fi

echo ""
echo -e "${BLUE}Database:${NC}"
echo -e "  Host:     ${GREEN}${DB_HOSTNAME:-mariadb}${NC}"
echo -e "  User:     ${GREEN}${DB_USERNAME:-dockercart}${NC}"
echo -e "  Password: ${GREEN}${DB_PASSWORD:-dockercart_password}${NC}"
echo ""
echo -e "${BLUE}Commands:${NC}"
echo -e "  Stop:     ${GREEN}docker compose down${NC}"
echo -e "  Logs:     ${GREEN}docker compose logs -f${NC}"
echo -e "  Shell:    ${GREEN}docker compose exec apache bash${NC}"
echo -e "  Doctor:   ${GREEN}make doctor${NC}  (containers, HTTP, DB, restart loops, edge cert)"
echo ""

if [ "$SSL_MODE" = "letsencrypt" ]; then
    echo -e "${YELLOW}ℹ Certificate renewal runs automatically${NC}"
    echo ""
fi

echo -e "${GREEN}For more commands: make help${NC}"
echo ""
