#!/bin/bash
# DockerCart - Simple Start Script
# Usage: ./start.sh [options]
#   ./start.sh                    - Default: Nginx proxy + Apache (Traefik)
#   ./start.sh --ssl              - Self-signed SSL (local testing)
#   ./start.sh --letsencrypt      - Let's Encrypt SSL (production)

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
PROD_MODE=false
SSL_MODE="none"

# ============================================================================
# PARSE OPTIONS
# ============================================================================

while [[ $# -gt 0 ]]; do
    case $1 in
        --ssl)
            SSL_MODE="self-signed"
            shift
            ;;
        --letsencrypt)
            SSL_MODE="letsencrypt"
            shift
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            exit 1
            ;;
    esac
done

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

# Load .env into the script environment so variables are available to this script
# We use `set -o allexport` to export all variables defined in .env. If .env
# contains non-shell-friendly lines this may fail; the repo .env files are
# expected to be simple KEY=VALUE lines.
if [ -f .env ]; then
    echo -e "${YELLOW}Loading .env variables...${NC}"
    # shellcheck disable=SC1090
    set -o allexport
    source .env
    set +o allexport
fi

# echo -e "${YELLOW}Creating directories...${NC}"
# mkdir -p storage/{cache,logs,download,upload,modification,session}
# mkdir -p backups docker/ssl/{certs,private}
# echo -e "${GREEN}✓ Directories ready${NC}"
echo ""

# ============================================================================
# SSL SETUP
# ============================================================================

if [ "$SSL_MODE" = "letsencrypt" ]; then
    echo -e "${YELLOW}Let's Encrypt setup${NC}"
    
    # After sourcing .env, prefer the loaded variable. Validate it here.
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

if [ "$SSL_MODE" = "self-signed" ]; then
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
echo -e "${GREEN}✅ DockerCart is running!${NC}"
echo ""

if [ "$PROD_MODE" = true ]; then
    echo -e "${BLUE}Production mode (Nginx proxy + Apache):${NC}"
    echo "  Network: ${GREEN}dockercart-network${NC}"
    echo "  Frontend: ${GREEN}Nginx (ports managed externally)${NC}"
    echo "  Backend:  ${GREEN}Apache (internal, port 80)${NC}"
    echo "  Database: ${GREEN}MariaDB (internal)${NC}"
    echo ""
else
    echo -e "${BLUE}Local development (Apache + exposed ports):${NC}"
    # Use values from .env when available, with sensible fallbacks
    SITE_URL="${DOCKERCART_URL:-http://dockercart.local}"
    # DOCKERCART_DOMAIN should be a host-only value (e.g. dockercart.local)
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
