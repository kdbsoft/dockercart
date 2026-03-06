#!/bin/bash
# Health check script for DockerCart Docker environment

set -e

# Load runtime configuration from .env
DOCKERCART_HTTP_PORT=${DOCKERCART_HTTP_PORT:-80}
DOCKERCART_DOMAIN=${DOCKERCART_DOMAIN:-dockercart.local}
DOCKERCART_URL=${DOCKERCART_URL:-http://${DOCKERCART_DOMAIN}}
PHPMYADMIN_PORT=${PHPMYADMIN_PORT:-8081}
PMA_URL=${PMA_URL:-http://pma.${DOCKERCART_DOMAIN}}
HEALTHCHECK_HOST=${HEALTHCHECK_HOST:-127.0.0.1}
if [ -f .env ]; then
    env_dockercart_http_port=$(grep "^DOCKERCART_HTTP_PORT=" .env | cut -d'=' -f2 || true)
    env_dockercart_domain=$(grep "^DOCKERCART_DOMAIN=" .env | cut -d'=' -f2 || true)
    env_dockercart_url=$(grep "^DOCKERCART_URL=" .env | cut -d'=' -f2 || true)
    env_phpmyadmin_port=$(grep "^PHPMYADMIN_PORT=" .env | cut -d'=' -f2 || true)
    env_pma_url=$(grep "^PMA_URL=" .env | cut -d'=' -f2 || true)

    [ -n "${env_dockercart_http_port:-}" ] && DOCKERCART_HTTP_PORT="${env_dockercart_http_port}"
    [ -n "${env_dockercart_domain:-}" ] && DOCKERCART_DOMAIN="${env_dockercart_domain}"
    [ -n "${env_dockercart_url:-}" ] && DOCKERCART_URL="${env_dockercart_url}"
    [ -n "${env_phpmyadmin_port:-}" ] && PHPMYADMIN_PORT="${env_phpmyadmin_port}"
    [ -n "${env_pma_url:-}" ] && PMA_URL="${env_pma_url}"
fi

DOCKERCART_HTTP_PORT=${DOCKERCART_HTTP_PORT:-80}
DOCKERCART_DOMAIN=${DOCKERCART_DOMAIN:-dockercart.local}
DOCKERCART_URL=${DOCKERCART_URL:-http://${DOCKERCART_DOMAIN}}
PHPMYADMIN_PORT=${PHPMYADMIN_PORT:-8081}
PMA_URL=${PMA_URL:-http://pma.${DOCKERCART_DOMAIN}}

BLUE='\033[0;34m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   DockerCart Health Check         ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════╝${NC}"
echo ""

# Check counters
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

# Check containers
echo -e "${YELLOW}📦 Container Check${NC}"
check "Container dockercart_apache" "docker compose ps apache | grep -q 'Up'"
check "Container dockercart_mariadb" "docker compose ps mariadb | grep -q 'Up'"
check "Container dockercart_phpmyadmin" "docker compose ps phpmyadmin | grep -q 'Up'"
echo ""

# Check ports
echo -e "${YELLOW}🔌 Port Check${NC}"
check "Port ${DOCKERCART_HTTP_PORT} (DockerCart)" "curl -sf http://${HEALTHCHECK_HOST}:${DOCKERCART_HTTP_PORT} -o /dev/null"
check "Port ${PHPMYADMIN_PORT} (phpMyAdmin)" "curl -sf http://${HEALTHCHECK_HOST}:${PHPMYADMIN_PORT} -o /dev/null"
echo ""

# Check MariaDB
echo -e "${YELLOW}💾 MariaDB Check${NC}"
check "MariaDB responding" "docker compose exec -T mariadb mariadb-admin ping -h 127.0.0.1 -u root -proot_password --silent"
check "Database dockercart exists" "docker compose exec -T mariadb mariadb -u dockercart -pdockerart_password -e 'USE dockercart'"
echo ""

# Check PHP
echo -e "${YELLOW}🐘 PHP Check${NC}"
check "PHP running" "docker compose exec -T apache php -v"
check "PHP extension mysqli" "docker compose exec -T apache php -m | grep -q mysqli"
check "PHP extension gd" "docker compose exec -T apache php -m | grep -q gd"
check "PHP extension zip" "docker compose exec -T apache php -m | grep -q zip"
echo ""

# Check files
echo -e "${YELLOW}📁 File Check${NC}"
check "Directory upload" "docker compose exec -T apache test -d /var/www/html"
check "File index.php" "docker compose exec -T apache test -f /var/www/html/index.php"
check "Directory storage" "docker compose exec -T apache test -d /var/www/storage"
echo ""

# Check permissions
echo -e "${YELLOW}🔒 Permissions Check${NC}"
check "Storage is writable" "docker compose exec -T apache test -w /var/www/storage"
check "Image cache is writable" "docker compose exec -T apache test -w /var/www/html/image || docker compose exec -T apache mkdir -p /var/www/html/image"
echo ""

# Summary
echo -e "${BLUE}════════════════════════════════════════${NC}"
echo -e "Passed: ${GREEN}${PASSED}${NC}"
echo -e "Failed: ${RED}${FAILED}${NC}"
echo -e "${BLUE}════════════════════════════════════════${NC}"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ All checks passed successfully!${NC}"
    echo -e "${GREEN}DockerCart is ready to use.${NC}"
    echo ""
    echo -e "${BLUE}Access:${NC}"
    echo -e "  DockerCart: ${GREEN}${DOCKERCART_URL}${NC}"
    echo -e "  Admin: ${GREEN}${DOCKERCART_URL%/}/admin${NC}"
    echo -e "  phpMyAdmin: ${GREEN}${PMA_URL}${NC}"
    exit 0
else
    echo -e "${YELLOW}⚠️  Some checks failed.${NC}"
    echo -e "${YELLOW}Check logs: ${GREEN}docker compose logs -f${NC}"
    exit 1
fi
