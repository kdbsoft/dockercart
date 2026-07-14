#!/bin/bash
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

if [ ! -f .env ]; then
    echo -e "${YELLOW}Creating .env${NC}"
    cp .env.example .env
fi

if [ -f .env ]; then
    echo -e "${YELLOW}Loading .env variables...${NC}"
    set -o allexport
    source .env
    set +o allexport
fi

echo ""

# ============================================================================
# SSL SETUP
# ============================================================================

if [ "$SSL_MODE" = "letsencrypt" ]; then
    echo -e "${YELLOW}Let's Encrypt setup${NC}"

    if [ -z "${SSL_DOMAIN:-}" ] || [ "${SSL_DOMAIN}" = "example.com" ]; then
        echo -e "${RED}❌ SSL_DOMAIN not configured in .env${NC}"
        echo ""
        echo "Edit .env and set:"
        echo "  SSL_DOMAIN=your-domain.com"
        echo "  SSL_EMAIL=admin@your-domain.com"
        exit 1
    fi

    echo -e "${GREEN}✓ Domain: ${SSL_DOMAIN}${NC}"
    echo ""
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
    docker compose "${COMPOSE_FILES[@]}" up -d --build

    ACTIVE_CERT_NAME="dockercart"
    VALID_CERT_NAME=""
    USABLE_CERT_NAME=""
    MATCHING_CERT_NAME=""

    for cert_path in "$LE_DATA_DIR"/live/*/fullchain.pem; do
        [ -f "$cert_path" ] || continue
        cert_name="${cert_path#$LE_DATA_DIR/live/}"
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
    docker compose -f docker-compose.yml -f docker-compose.le.yml up -d --build
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

docker compose "${COMPOSE_FILES[@]}" down 2>/dev/null || true
docker compose "${COMPOSE_FILES[@]}" build
docker compose "${COMPOSE_FILES[@]}" up -d

echo -e "${YELLOW}Waiting for services to be ready...${NC}"
sleep 10

echo -e "${GREEN}✓ Containers started${NC}"
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
    echo "  Network: ${GREEN}dockercart-network${NC}"
    echo "  Frontend: ${GREEN}Nginx (ports managed externally)${NC}"
    echo "  Backend:  ${GREEN}Apache (internal, port 80)${NC}"
    echo "  Database: ${GREEN}MariaDB (internal)${NC}"
    echo ""
else
    echo -e "${GREEN}✅ DockerCart is running!${NC}"
    echo ""
    SITE_URL="${DOCKERCART_URL:-http://dockercart.local}"
    SITE_HOST="${DOCKERCART_DOMAIN:-dockercart.local}"
    PHPMYADMIN_PORT="${PHPMYADMIN_PORT:-8085}"
    DB_HOST_PRINT="${DB_HOSTNAME:-mariadb}"
    DB_PORT_PRINT="${DB_PORT:-3306}"

    echo "  Site:      ${GREEN}${SITE_URL}${NC}"
    echo "  Admin:     ${GREEN}${SITE_URL%/}/admin${NC}"
    echo "  phpMyAdmin: ${GREEN}http://${SITE_HOST}:${PHPMYADMIN_PORT}${NC}"
    echo "  MariaDB:   ${GREEN}${DB_HOST_PRINT}:${DB_PORT_PRINT}${NC}"
    if [ "$SSL_MODE" = "self-signed" ]; then
        echo "  HTTPS:     ${GREEN}https://${SITE_HOST} (warning: self-signed)${NC}"
    fi
fi

echo ""
echo -e "${BLUE}Database:${NC}"
echo "  Host:     ${GREEN}${DB_HOSTNAME:-mariadb}${NC}"
echo "  User:     ${GREEN}${DB_USERNAME:-dockercart}${NC}"
echo "  Password: ${GREEN}${DB_PASSWORD:-dockercart_password}${NC}"
echo ""
echo -e "${BLUE}Commands:${NC}"
echo "  Stop:     ${GREEN}docker compose down${NC}"
echo "  Logs:     ${GREEN}docker compose logs -f${NC}"
echo "  Shell:    ${GREEN}docker compose exec apache bash${NC}"
echo ""

if [ "$SSL_MODE" = "letsencrypt" ]; then
    echo -e "${YELLOW}ℹ Certificate renewal runs automatically${NC}"
    echo ""
fi

echo -e "${GREEN}For more commands: make help${NC}"
echo ""
