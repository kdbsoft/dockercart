#!/bin/bash
# Interactive run-mode selection for DockerCart.
# Sourced by start.sh (--menu): sets TRAEFIK_MODE / SSL_MODE in the current shell
# and remembers the last choice in .env as DOCKERCART_RUN_MODE.

BLUE='\033[0;34m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
RED='\033[0;31m'
NC='\033[0m'

# --- Resolve the current mode from command-line state (default: standalone HTTP) ---
LAST_MODE="http"
case "${SSL_MODE:-none}" in
    self-signed)  LAST_MODE="ssl" ;;
    letsencrypt)  LAST_MODE="le" ;;
esac
if [ "${TRAEFIK_MODE:-false}" = true ]; then
    case "$LAST_MODE" in
        http) LAST_MODE="traefik" ;;
        ssl)  LAST_MODE="traefik-ssl" ;;
        le)   LAST_MODE="traefik-le" ;;
    esac
fi

# --- Remembered mode from .env (overrides defaults) ---
ENV_FILE=".env"
if [ -f "$ENV_FILE" ]; then
    REMEMBERED_MODE="$(grep -E '^DOCKERCART_RUN_MODE=' "$ENV_FILE" | tail -1 | cut -d= -f2- | tr -d '[:space:]')"
    if [ -n "$REMEMBERED_MODE" ]; then
        LAST_MODE="$REMEMBERED_MODE"
    fi
fi

# --- Interactive menu ---
echo -e "${BLUE}Select start mode:${NC}"
echo ""
echo "  1) Standalone HTTP              (dockercart.local)"
echo "  2) Standalone HTTPS self-signed (local testing)"
echo "  3) Standalone HTTPS Let's Encrypt (production, needs SSL_DOMAIN)"
echo "  4) Traefik HTTP                 (external reverse proxy)"
echo "  5) Traefik HTTPS self-signed"
echo "  6) Traefik HTTPS Let's Encrypt  (production)"
echo ""
echo -e "${YELLOW}Last used: ${LAST_MODE}${NC}"
read -r -p "Enter choice [1-6] (Enter = last used): " MENU_INPUT

MENU_INPUT="${MENU_INPUT:-$LAST_MODE}"
case "$MENU_INPUT" in
    1|http)            MENU="http" ;;
    2|ssl)             MENU="ssl" ;;
    3|le|letsencrypt)  MENU="le" ;;
    4|traefik)         MENU="traefik" ;;
    5|traefik-ssl)     MENU="traefik-ssl" ;;
    6|traefik-le)      MENU="traefik-le" ;;
    *)
        echo -e "${RED}Invalid choice: $MENU_INPUT${NC}"
        exit 1
        ;;
esac

echo -e "${GREEN}✓ Selected mode: ${MENU}${NC}"
echo ""

# --- Apply flags for start.sh ---
case "$MENU" in
    http)        TRAEFIK_MODE=false; SSL_MODE="none" ;;
    ssl)         TRAEFIK_MODE=false; SSL_MODE="self-signed" ;;
    le)          TRAEFIK_MODE=false; SSL_MODE="letsencrypt" ;;
    traefik)     TRAEFIK_MODE=true;  SSL_MODE="none" ;;
    traefik-ssl) TRAEFIK_MODE=true;  SSL_MODE="self-signed" ;;
    traefik-le)  TRAEFIK_MODE=true;  SSL_MODE="letsencrypt" ;;
esac

# --- Remember the choice in .env ---
if [ ! -f "$ENV_FILE" ] && [ -f .env.example ]; then
    cp .env.example "$ENV_FILE"
fi
if [ -f "$ENV_FILE" ]; then
    if grep -qE '^DOCKERCART_RUN_MODE=' "$ENV_FILE"; then
        sed -i "s/^DOCKERCART_RUN_MODE=.*/DOCKERCART_RUN_MODE=${MENU}/" "$ENV_FILE"
    else
        printf '\n# Last run mode selected via make start\nDOCKERCART_RUN_MODE=%s\n' "$MENU" >> "$ENV_FILE"
    fi
fi
