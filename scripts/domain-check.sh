#!/bin/bash
# DockerCart — read-only domain / migration check.
#
# A store developed locally (dockercart.local:8080) and moved to a server with a
# real domain keeps a few things pointing at the old host, because they are NOT
# derived: the generated sitemap.xml, the robots body stored in oc_setting, a
# multistore URL, webhook endpoints, and any absolute link someone pasted into a
# description. Everything else (config.php, config_url/config_ssl, robots.txt)
# follows DOCKERCART_DOMAIN and heals on boot.
#
# This script answers the only question that matters after a move — "what still
# points at the old host?" — without changing anything. It is safe to run at any
# time, also on a stopped stack (the database section is then skipped).
#
# The running container is authoritative for the store URL (that is what visitors
# get); without it the answer is what `.env` + the active compose set would give
# on the next `make restart`.
#
# Usage: ./scripts/domain-check.sh      (or: make retarget-check / make doctor)
#
# Exit code: 0 — nothing to fix, 1 — at least one finding (✗).

# No `set -e`: a failed probe must not abort the report.
set -u

# shellcheck source=./scripts/load-env.sh
. ./scripts/load-env.sh
[ -f .env ] && load_env .env

# shellcheck source=./docker/store-url.sh
. ./docker/store-url.sh

ENV_DOMAIN=${DOCKERCART_DOMAIN:-}
ENV_URL=$(sed -n 's/^DOCKERCART_URL=//p' .env 2>/dev/null | tail -1 | tr -d '[:space:]')
ENV_HTTPS_URL=$(sed -n 's/^DOCKERCART_HTTPS_URL=//p' .env 2>/dev/null | tail -1 | tr -d '[:space:]')
ENV_SSL_FLAG=${DOCKERCART_SSL_ENABLED:-}

DOCKERCART_HTTP_PORT=${DOCKERCART_HTTP_PORT:-80}
DOCKERCART_HTTPS_PORT=${DOCKERCART_HTTPS_PORT:-443}
DOCKERCART_DOMAIN=${DOCKERCART_DOMAIN:-dockercart.local}

# The active compose set is authoritative for the SSL flag: the TLS overrides set
# DOCKERCART_SSL_ENABLED=true literally, so a stale `false` in .env is ignored by
# compose — and must be ignored here too (this mirrors what `make restart` does).
TLS_FILES=0
case " ${DOCKERCART_COMPOSE_FILES:-} " in
    *".ssl.yml "*|*".le.yml "*) TLS_FILES=1 ;;
esac

DB_USER=${DB_USERNAME:-dockercart}
DB_PASS=${DB_PASSWORD:-dockercart_password}
DB_NAME=${DB_DATABASE:-dockercart}
DB_PREFIX=${DB_PREFIX:-oc_}

BLUE='\033[0;34m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
RED='\033[0;31m'
NC='\033[0m'

ISSUES=0
NOTES=0

ok() { echo -e "  ${GREEN}✓${NC} $*"; }
bad() {
    ISSUES=$((ISSUES + 1))
    echo -e "  ${RED}✗${NC} $*"
}
note() {
    NOTES=$((NOTES + 1))
    echo -e "  ${YELLOW}ℹ${NC} $*"
}
section() { echo -e "\n${BLUE}$*${NC}"; }

host_of() { store_url_host "${1:-}"; }

# scheme://host[:port] of a URL, without path — the part that must match.
origin_of() {
    printf '%s\n' "${1:-}" | sed -E 's|^([A-Za-z][A-Za-z0-9+.-]*://[^/?]+).*|\1|'
}

# ── Which URL does the store use? ────────────────────────────────────────────
# 1) the running container (what visitors actually get);
# 2) .env + the active compose set (what `make restart` would produce).
SOURCE=".env + active compose set"
LIVE_ENV=$(docker compose exec -T apache sh -c \
    'printf "%s\t%s\t%s\t%s" "${DOCKERCART_DOMAIN:-}" "${DOCKERCART_URL:-}" "${DOCKERCART_HTTPS_URL:-}" "${DOCKERCART_SSL_ENABLED:-}"' \
    2>/dev/null)

if [ -n "$(printf '%s' "$LIVE_ENV" | tr -d '[:space:]')" ]; then
    IFS=$'\t' read -r LIVE_DOMAIN LIVE_URL LIVE_HTTPS_URL LIVE_SSL <<< "$LIVE_ENV"
    SOURCE="running apache container (docker compose exec apache printenv)"
    export DOCKERCART_DOMAIN="$LIVE_DOMAIN"
    export DOCKERCART_URL="$LIVE_URL"
    export DOCKERCART_HTTPS_URL="$LIVE_HTTPS_URL"
    export DOCKERCART_SSL_ENABLED="$LIVE_SSL"
else
    DOCKERCART_DOMAIN="$ENV_DOMAIN"
    export DOCKERCART_DOMAIN
    export DOCKERCART_URL="$ENV_URL"
    export DOCKERCART_HTTPS_URL="$ENV_HTTPS_URL"
    export DOCKERCART_SSL_ENABLED="$ENV_SSL_FLAG"
    [ "$TLS_FILES" = "1" ] && export DOCKERCART_SSL_ENABLED=true
fi

resolve_store_urls

STORE_EFFECTIVE="$(store_effective_url)"
STORE_EFFECTIVE="${STORE_EFFECTIVE%/}"
STORE_ORIGIN="$(origin_of "$STORE_EFFECTIVE")"
STORE_HOST="$(store_url_host "$STORE_EFFECTIVE")"

# What `.env` + compose would give — differs from the live URL => a restart is
# pending (the running container still serves the old domain).
env_store_effective() (
    if [ "$TLS_FILES" = "1" ]; then
        export DOCKERCART_DOMAIN="$ENV_DOMAIN" DOCKERCART_URL="$ENV_URL" \
            DOCKERCART_HTTPS_URL="$ENV_HTTPS_URL" DOCKERCART_SSL_ENABLED=true
    else
        export DOCKERCART_DOMAIN="$ENV_DOMAIN" DOCKERCART_URL="$ENV_URL" \
            DOCKERCART_HTTPS_URL="$ENV_HTTPS_URL" DOCKERCART_SSL_ENABLED="$ENV_SSL_FLAG"
    fi
    resolve_store_urls
    store_effective_url
)
ENV_EFFECTIVE="$(env_store_effective)"
ENV_EFFECTIVE="${ENV_EFFECTIVE%/}"

echo -e "${BLUE}DockerCart — domain check${NC}"
echo -e "  store URL: ${GREEN}${STORE_EFFECTIVE}/${NC}"
echo -e "  source:    ${SOURCE}"
if [ "$STORE_URLS_DERIVED" = "1" ]; then
    echo -e "  derived:   yes (DOCKERCART_DOMAIN + ports + TLS flag)"
else
    echo -e "  derived:   no (explicit DOCKERCART_URL)"
fi

if [ -n "$ENV_EFFECTIVE" ] && [ "$ENV_EFFECTIVE" != "$STORE_EFFECTIVE" ]; then
    note "the running container serves ${STORE_EFFECTIVE}/ but .env + compose would give ${ENV_EFFECTIVE}/ — a restart is pending (make restart)"
fi

# ── 1. .env ──────────────────────────────────────────────────────────────────
section ".env"

if [ -z "$ENV_DOMAIN" ]; then
    bad "DOCKERCART_DOMAIN is empty — the store falls back to the template host"
else
    ok "DOCKERCART_DOMAIN=${ENV_DOMAIN}"
fi

EXPLICIT_URLS=0
for key in DOCKERCART_URL DOCKERCART_HTTPS_URL; do
    value="$(sed -n "s/^${key}=//p" .env 2>/dev/null | tail -1 | tr -d '[:space:]')"
    [ -n "$value" ] || continue
    EXPLICIT_URLS=$((EXPLICIT_URLS + 1))

    if store_url_is_placeholder "$value" "$ENV_DOMAIN"; then
        note "${key}=${value} is the template placeholder — start.sh comments it out automatically"
    elif [ "$(host_of "$value")" != "$STORE_HOST" ]; then
        bad "${key}=${value} wins over DOCKERCART_DOMAIN — the store will be served on $(host_of "$value")"
    else
        ok "${key}=${value} (explicit, matches the domain)"
    fi
done
[ "$EXPLICIT_URLS" -gt 0 ] || ok "DOCKERCART_URL/DOCKERCART_HTTPS_URL unset (derived from the domain)"

# A non-default public port ends up in every generated link: ":8080" copied from
# a local stand is the classic leftover. Private/local names are exempt.
if [ "$DOCKERCART_HTTP_PORT" != "80" ] || [ "$DOCKERCART_HTTPS_PORT" != "443" ]; then
    case "${ENV_DOMAIN:-${LIVE_DOMAIN:-$STORE_HOST}}" in
        *".local"|*".localhost"|localhost) note "non-default ports ${DOCKERCART_HTTP_PORT}/${DOCKERCART_HTTPS_PORT} (fine for a local host)" ;;
        *) bad "public ports ${DOCKERCART_HTTP_PORT}/${DOCKERCART_HTTPS_PORT} — links will carry :${DOCKERCART_HTTP_PORT}; set 80/443 in .env for a public domain" ;;
    esac
else
    ok "public ports 80/443"
fi

if [ "$TLS_FILES" = "1" ] && [ "$ENV_SSL_FLAG" != "true" ]; then
    note "DOCKERCART_SSL_ENABLED in .env is '${ENV_SSL_FLAG:-<unset>}'; the TLS compose override sets it to true (the mode is authoritative)"
elif [ "$TLS_FILES" = "0" ] && [ "$ENV_SSL_FLAG" = "true" ]; then
    bad "DOCKERCART_SSL_ENABLED=true but no TLS override in DOCKERCART_COMPOSE_FILES — the app would emit https URLs nobody serves"
else
    ok "SSL flag and compose files agree (ssl=${STORE_SSL_ENABLED})"
fi

# ── 2. Files (host side, no container needed) ────────────────────────────────
section "Files"

ROBOTS_URL=""
if [ -f upload/robots.txt ]; then
    ROBOTS_URL="$(sed -nE 's/^[[:space:]]*Sitemap:[[:space:]]*([^[:space:]]+).*/\1/Ip' upload/robots.txt | head -n 1)"
fi
if [ -z "$ROBOTS_URL" ]; then
    note "upload/robots.txt has no Sitemap line (nothing advertised)"
elif [ "$(origin_of "$ROBOTS_URL")" = "$STORE_ORIGIN" ]; then
    ok "robots.txt Sitemap ${ROBOTS_URL}"
elif [ "$(host_of "$ROBOTS_URL")" = "$STORE_HOST" ]; then
    bad "robots.txt Sitemap ${ROBOTS_URL} has the wrong scheme/port — restart the stack (the entrypoint repairs it) or save System → Indexing"
else
    bad "robots.txt Sitemap ${ROBOTS_URL} points at another host"
fi

if [ ! -f upload/sitemap.xml ]; then
    note "upload/sitemap.xml not generated yet (make shell → php /var/www/html/bin/dockercart_sitemap_generate.php)"
else
    SITEMAP_ORIGINS="$(grep -o '<loc>[^<]*' upload/sitemap.xml 2>/dev/null \
        | sed 's|^<loc>||' | while read -r loc; do origin_of "$loc"; done \
        | sort | uniq -c | sort -rn)"
    if [ -z "$SITEMAP_ORIGINS" ]; then
        note "upload/sitemap.xml contains no <loc> entries (sitemap index only)"
    else
        while read -r count origin; do
            [ -n "${origin:-}" ] || continue
            if [ "$origin" = "$STORE_ORIGIN" ]; then
                ok "sitemap.xml: ${count} <loc> entries on ${origin}"
            else
                bad "sitemap.xml: ${count} <loc> entries on ${origin} — regenerate (php /var/www/html/bin/dockercart_sitemap_generate.php)"
            fi
        done <<< "$SITEMAP_ORIGINS"
    fi
fi

# ── 3. Database ──────────────────────────────────────────────────────────────
section "Database"

# One read-only SQL query (tab separated, no headers). Empty output on any error.
sql() {
    docker compose exec -T -e MYSQL_PWD="$DB_PASS" mariadb \
        mariadb -u "$DB_USER" --skip-ssl -N -B "$DB_NAME" -e "$1" 2>/dev/null
}

if ! docker compose exec -T -e MYSQL_PWD="$DB_PASS" mariadb \
        mariadb-admin ping -h 127.0.0.1 -u "$DB_USER" --silent >/dev/null 2>&1; then
    note "MariaDB is not running — database checks skipped (start the stack and re-run)"
else
    # Store URL rows (store_id = 0); the entrypoint resyncs both on every boot.
    while IFS=$'\t' read -r key value; do
        [ -n "${key:-}" ] || continue
        if [ "$(origin_of "$value")" = "$STORE_ORIGIN" ]; then
            ok "${key}=${value}"
        else
            bad "${key}=${value} — expected ${STORE_EFFECTIVE}/ (restart the stack: the entrypoint resyncs it)"
        fi
    done <<< "$(sql "SELECT \`key\`, \`value\` FROM \`${DB_PREFIX}setting\` WHERE \`key\` IN ('config_url','config_ssl') AND store_id = 0")"

    # The robots body stored with the settings: only its Sitemap line is derived.
    INDEX_ROBOTS_URL="$(sql "SELECT \`value\` FROM \`${DB_PREFIX}setting\` WHERE \`key\` = 'config_indexing_robots' AND store_id = 0" \
        | sed -nE 's/^.*Sitemap:[[:space:]]*([^[:space:]\\]+).*$/\1/Ip' | head -n 1)"
    if [ -z "$INDEX_ROBOTS_URL" ]; then
        ok "config_indexing_robots has no Sitemap line (the default template is rendered from the store URL)"
    elif [ "$(origin_of "$INDEX_ROBOTS_URL")" = "$STORE_ORIGIN" ]; then
        ok "config_indexing_robots Sitemap ${INDEX_ROBOTS_URL}"
    else
        bad "config_indexing_robots Sitemap ${INDEX_ROBOTS_URL} — open System → Indexing and save (the page shows the corrected URL)"
    fi

    # Multistore rows are NOT synced by the entrypoint (store_id = 0 only).
    while IFS=$'\t' read -r store_id url ssl; do
        [ -n "${store_id:-}" ] || continue
        for value in "$url" "$ssl"; do
            [ -n "${value:-}" ] || continue
            if [ "$(origin_of "$value")" = "$STORE_ORIGIN" ]; then
                ok "oc_store[${store_id}] ${value}"
            else
                bad "oc_store[${store_id}] ${value} — fix in Settings → Stores"
            fi
        done
    done <<< "$(sql "SELECT store_id, COALESCE(url,''), COALESCE(\`ssl\`,'') FROM \`${DB_PREFIX}store\`")"

    # Webhook endpoints point at the operator's own services, so a different host
    # is normal — only a leftover placeholder (a subscription created on a local
    # stand, which would fire at a host nobody resolves) is a finding.
    while read -r url; do
        [ -n "${url:-}" ] || continue
        if [ "$(host_of "$url")" = "$STORE_URL_PLACEHOLDER_DOMAIN" ]; then
            bad "webhook subscription ${url} still points at the placeholder host — disable or recreate it (Integrations → Webhooks)"
        fi
    done <<< "$(sql "SELECT COALESCE(url,'') FROM \`${DB_PREFIX}dockercart_webhook_subscription\` WHERE COALESCE(url,'') <> ''")"

    # Content: absolute links to a host that is NOT the store. The "old" hosts are
    # the ones this installation knows about — the template placeholder, an
    # explicit .env URL, and whatever config_url/config_ssl/oc_store still carry.
    OLD_HOSTS="$STORE_URL_PLACEHOLDER_DOMAIN $ENV_URL $ENV_HTTPS_URL"
    OLD_HOSTS="$OLD_HOSTS $(sql "SELECT CONCAT(COALESCE(\`value\`,''),' ') FROM \`${DB_PREFIX}setting\` WHERE \`key\` IN ('config_url','config_ssl') AND store_id = 0")"
    OLD_HOSTS="$OLD_HOSTS $(sql "SELECT CONCAT(COALESCE(url,''),' ',COALESCE(\`ssl\`,'')) FROM \`${DB_PREFIX}store\`")"

    OLD_HOST_LIST=""
    for value in $OLD_HOSTS; do
        host="$(host_of "$value")"
        [ -n "$host" ] || continue
        [ "$host" = "$STORE_HOST" ] && continue
        OLD_HOST_LIST="$OLD_HOST_LIST $host"
    done
    OLD_HOST_LIST="$(printf '%s' "$OLD_HOST_LIST" | tr ' ' '\n' | sed '/^$/d' | sort -u | tr '\n' ' ')"
    OLD_HOST_LIST="${OLD_HOST_LIST# }"
    OLD_HOST_LIST="${OLD_HOST_LIST% }"

    if [ -z "$OLD_HOST_LIST" ]; then
        ok "no foreign host to look for in the content"
    else
        # Conventional content column names only: scanning every text column is
        # slow on a real catalog, and addresses/notes never hold links.
        CONTENT_COLUMNS="$(sql "SELECT CONCAT(table_name, '\t', column_name) FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND column_name IN ('description','content','value','url','html','text')
              AND table_name NOT IN ('${DB_PREFIX}setting','${DB_PREFIX}store','${DB_PREFIX}order',
                    '${DB_PREFIX}dockercart_webhook_subscription','${DB_PREFIX}customer_search',
                    '${DB_PREFIX}api_idempotency','${DB_PREFIX}api_log','${DB_PREFIX}session',
                    '${DB_PREFIX}customer_ip')
            ORDER BY table_name, column_name")"

        FOUND_CONTENT=0
        while IFS=$'\t' read -r table column; do
            [ -n "${table:-}" ] || continue
            for host in $OLD_HOST_LIST; do
                count="$(sql "SELECT COUNT(*) FROM \`${table}\` WHERE \`${column}\` LIKE '%${host}%'")"
                [ -n "${count:-}" ] || continue
                [ "$count" -gt 0 ] 2>/dev/null || continue
                FOUND_CONTENT=1
                bad "${table}.${column}: ${count} row(s) mention ${host} — rewrite them in the admin (or with your own SQL)"
            done
        done <<< "$CONTENT_COLUMNS"
        [ "$FOUND_CONTENT" = "0" ] && ok "no content table references ${OLD_HOST_LIST}"
    fi

    # Order history keeps the URL the order was placed at — informational only.
    while read -r count host; do
        [ -n "${host:-}" ] || continue
        note "order history: ${count} order(s) were placed on ${host} (kept on purpose)"
    done <<< "$(sql "SELECT CONCAT(COUNT(*), ' ', SUBSTRING_INDEX(SUBSTRING_INDEX(store_url,'//',-1),'/',1)) FROM \`${DB_PREFIX}order\` WHERE COALESCE(store_url,'') <> '' GROUP BY 2 ORDER BY 1 DESC")"
fi

# ── Summary ──────────────────────────────────────────────────────────────────
echo ""
if [ "$ISSUES" -eq 0 ]; then
    if [ "$NOTES" -gt 0 ]; then
        echo -e "${GREEN}✓ Nothing points at another host.${NC} (${NOTES} note(s) above)"
    else
        echo -e "${GREEN}✓ Nothing points at another host.${NC}"
    fi
    exit 0
fi

echo -e "${RED}✗ ${ISSUES} finding(s) to fix.${NC}"
echo -e "  After changing the domain/ports in .env: ${GREEN}make start${NC} (pick the TLS mode), then"
echo -e "  ${GREEN}make shell${NC} → ${GREEN}php /var/www/html/bin/dockercart_sitemap_generate.php${NC}, then re-run this check."
exit 1
