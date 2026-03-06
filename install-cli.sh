#!/bin/bash
# DockerCart bootstrap helper (no OpenCart legacy installer)

set -e

BLUE='\033[0;34m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
RED='\033[0;31m'
NC='\033[0m'

DOCKERCART_URL=${DOCKERCART_URL:-http://dockercart.local}
ADMIN_USERNAME=${ADMIN_USERNAME:-admin}
ADMIN_PASSWORD=${ADMIN_PASSWORD:-admin123}
ADMIN_EMAIL=${ADMIN_EMAIL:-admin@example.com}
DB_DATABASE=${DB_DATABASE:-dockercart}

echo -e "${BLUE}╔══════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   DockerCart Bootstrap Helper (Install-less Mode)   ║${NC}"
echo -e "${BLUE}╚══════════════════════════════════════════════════════╝${NC}"
echo ""

echo -e "${YELLOW}ℹ Legacy OpenCart install/cli_install.php is disabled in DockerCart.${NC}"
echo -e "${YELLOW}ℹ Initialization is automatic: .env + DB seed during first MariaDB startup.${NC}"
echo ""

if ! docker compose ps | grep -q "mariadb"; then
    echo -e "${RED}❌ MariaDB container is not running.${NC}"
    echo -e "${YELLOW}Start stack first with docker compose up -d --build${NC}"
    exit 1
fi

echo -e "${YELLOW}⏳ Waiting for MariaDB health...${NC}"
until docker compose exec -T mariadb mariadb-admin ping -h 127.0.0.1 -u "${MARIADB_USER:-${MYSQL_USER:-dockercart}}" -p"${MARIADB_PASSWORD:-${MYSQL_PASSWORD:-dockercart_password}}" --silent >/dev/null 2>&1; do
    echo -n "."
    sleep 2
done
echo -e " ${GREEN}✓${NC}"

TABLE_COUNT=$(docker compose exec -T mariadb mariadb -N -B -u "${MARIADB_USER:-${MYSQL_USER:-dockercart}}" -p"${MARIADB_PASSWORD:-${MYSQL_PASSWORD:-dockercart_password}}" "${MARIADB_DATABASE:-${MYSQL_DATABASE:-${DB_DATABASE}}}" -e "SHOW TABLES" 2>/dev/null | wc -l | tr -d ' ')

if [ "${TABLE_COUNT}" -gt 0 ]; then
    echo -e "${GREEN}✓ Database '${DB_DATABASE}' looks initialized (${TABLE_COUNT} tables).${NC}"
else
    echo -e "${RED}❌ Database appears empty.${NC}"
    echo -e "${YELLOW}If this is first start, check mariadb logs for init errors.${NC}"
    echo -e "${YELLOW}If re-initialization is needed, recreate DB volume (destructive):${NC}"
    echo -e "${YELLOW}  docker compose down -v && docker compose up -d --build${NC}"
    exit 1
fi

echo ""
echo -e "${BLUE}🌐 Access:${NC}"
echo -e "  Frontend: ${GREEN}${DOCKERCART_URL}${NC}"
echo -e "  Admin:    ${GREEN}${DOCKERCART_URL%/}/admin${NC}"
echo ""
echo -e "${BLUE}🔑 Default admin credentials from .env:${NC}"
echo -e "  Username: ${GREEN}${ADMIN_USERNAME}${NC}"
echo -e "  Password: ${GREEN}${ADMIN_PASSWORD}${NC}"
echo -e "  Email:    ${GREEN}${ADMIN_EMAIL}${NC}"
echo ""
echo -e "${YELLOW}⚠️  Change admin password after first login.${NC}"
