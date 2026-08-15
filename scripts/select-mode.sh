#!/bin/bash
# Interactive run-mode selection for DockerCart.
# Sourced by start.sh (--menu): sets TRAEFIK_MODE / SSL_MODE in the current shell
# and remembers the last choice in .env as DOCKERCART_RUN_MODE.
#
# Two-step wizard:
#   Step 1 — launch mode:  Standalone (Default) or Traefik
#   Step 2 — SSL submenu:  HTTP / HTTPS self-signed / HTTPS Let's Encrypt
#                          (options depend on the chosen launch mode)

BLUE='\033[0;34m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
RED='\033[0;31m'
NC='\033[0m'

# --- Resolve the last used mode from .env (overrides defaults) ----------------
ENV_FILE=".env"
REMEMBERED_MODE=""
if [ -f "$ENV_FILE" ]; then
    REMEMBERED_MODE="$(grep -E '^DOCKERCART_RUN_MODE=' "$ENV_FILE" | tail -1 | cut -d= -f2- | tr -d '[:space:]')"
fi

# Default launch mode + SSL, derived from the remembered mode (fallback: standalone HTTP).
LAST_LAUNCH="standalone"
LAST_SSL="http"
case "${REMEMBERED_MODE:-http}" in
    http)        LAST_LAUNCH="standalone"; LAST_SSL="http" ;;
    ssl)         LAST_LAUNCH="standalone"; LAST_SSL="ssl" ;;
    le)          LAST_LAUNCH="standalone"; LAST_SSL="le" ;;
    traefik)     LAST_LAUNCH="traefik";    LAST_SSL="http" ;;
    traefik-ssl) LAST_LAUNCH="traefik";    LAST_SSL="ssl" ;;
    traefik-le)  LAST_LAUNCH="traefik";    LAST_SSL="le" ;;
esac

# ============================================================================
# STEP 1 — LAUNCH MODE
# ============================================================================
echo -e "${BLUE}Step 1/2 — Select launch mode:${NC}"
echo ""
echo "  1) Standalone (Default)"
echo "  2) Traefik (external reverse proxy)"
echo ""
echo -e "${YELLOW}Last used: ${LAST_LAUNCH}${NC}"
read -r -p "Enter choice [1-2] (Enter = last used): " LAUNCH_INPUT

LAUNCH_INPUT="${LAUNCH_INPUT:-$LAST_LAUNCH}"
case "$LAUNCH_INPUT" in
    1|standalone) LAUNCH="standalone" ;;
    2|traefik)    LAUNCH="traefik" ;;
    *)
        echo -e "${RED}Invalid choice: $LAUNCH_INPUT${NC}"
        exit 1
        ;;
esac

# ============================================================================
# STEP 2 — SSL SUBMENU (depends on step 1)
# ============================================================================
echo ""
echo -e "${BLUE}Step 2/2 — Select SSL mode (${LAUNCH}):${NC}"
echo ""
if [ "$LAUNCH" = "standalone" ]; then
    echo "  1) HTTP                      (dockercart.local)"
    echo "  2) HTTPS self-signed         (local testing)"
    echo "  3) HTTPS Let's Encrypt       (production, needs SSL_DOMAIN)"
else
    echo "  1) HTTP                      (Traefik, no SSL)"
    echo "  2) HTTPS self-signed         (Traefik + self-signed)"
    echo "  3) HTTPS Let's Encrypt       (Traefik + Let's Encrypt, production)"
fi
echo ""
echo -e "${YELLOW}Last used: ${LAST_SSL}${NC}"
read -r -p "Enter choice [1-3] (Enter = last used): " SSL_INPUT

SSL_INPUT="${SSL_INPUT:-$LAST_SSL}"
case "$SSL_INPUT" in
    1|http)       SSL="http" ;;
    2|ssl)        SSL="ssl" ;;
    3|le|letsencrypt) SSL="le" ;;
    *)
        echo -e "${RED}Invalid choice: $SSL_INPUT${NC}"
        exit 1
        ;;
esac

# --- Map launch + SSL into the legacy MENU token (consumed by start.sh) -------
case "$LAUNCH:$SSL" in
    standalone:http) MENU="http" ;;
    standalone:ssl)  MENU="ssl" ;;
    standalone:le)   MENU="le" ;;
    traefik:http)    MENU="traefik" ;;
    traefik:ssl)     MENU="traefik-ssl" ;;
    traefik:le)      MENU="traefik-le" ;;
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
# (.env is created later by the setup wizard in start.sh's SETUP block.)
if [ -f "$ENV_FILE" ]; then
    if grep -qE '^DOCKERCART_RUN_MODE=' "$ENV_FILE"; then
        sed -i "s/^DOCKERCART_RUN_MODE=.*/DOCKERCART_RUN_MODE=${MENU}/" "$ENV_FILE"
    else
        printf '\n# Last run mode selected via make start\nDOCKERCART_RUN_MODE=%s\n' "$MENU" >> "$ENV_FILE"
    fi
fi
