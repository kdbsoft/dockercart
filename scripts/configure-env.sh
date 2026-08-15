#!/bin/bash
# Interactive .env configuration wizard for DockerCart.
# Sourced by start.sh: creates .env from .env.example on first run and asks
# only for the critical settings (domain, timezone, passwords, admin account,
# seed mode, and LE domain/email when running in Let's Encrypt mode).
# Existing .env files are left alone — only missing/invalid keys are asked for
# and appended (the "memory": once answered, never asked again).
#
# Non-interactive (no TTY or DOCKERCART_NONINTERACTIVE=1): silently copies
# .env.example to .env if missing, exactly like the old behavior.

# Colors (redefine locally — start.sh may source us before its own color vars)
BLUE='\033[0;34m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
RED='\033[0;31m'
NC='\033[0m'

# --- Helpers ---------------------------------------------------------------

gen_password() {
    local len="${1:-24}"
    head -c 16 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c "$len"
}

# Set KEY=value in an env file: update the existing line or append at the end.
set_env_key() {
    local file="$1" key="$2" value="$3"
    if grep -qE "^${key}=" "$file"; then
        sed -i "s|^${key}=.*|${key}=${value}|" "$file"
    else
        printf '\n# Configured by DockerCart setup wizard\n%s=%s\n' "$key" "$value" >> "$file"
    fi
}

# Current value of a key from the env file (first match, trailing spaces trimmed).
get_env_key() {
    local file="$1" key="$2"
    grep -E "^${key}=" "$file" | tail -1 | cut -d= -f2- | tr -d '[:space:]'
}

# Whether we can ask questions at all.
is_interactive() {
    [ "${DOCKERCART_NONINTERACTIVE:-0}" != "1" ] && [ -t 0 ]
}

# ask_value <varname> <prompt> <default>: writes the answer into the named
# variable (not via command substitution, so EOF can abort the whole wizard).
# Enter accepts the default; empty default means the answer is mandatory.
# Returns 0 on success, 2 when stdin is closed (wizard aborts).
ask_value() {
    local varname="$1" prompt="$2" default="$3" answer
    while true; do
        if [ -n "$default" ]; then
            printf '%s [%s]: ' "$prompt" "$default"
        else
            printf '%s: ' "$prompt"
        fi
        if ! IFS= read -r answer; then
            echo -e "${RED}Input closed — aborting setup.${NC}"
            return 2
        fi
        answer="${answer%%[[:space:]]}"
        if [ -n "$answer" ]; then
            printf -v "$varname" '%s' "$answer"
            return 0
        fi
        if [ -n "$default" ]; then
            printf -v "$varname" '%s' "$default"
            return 0
        fi
        echo -e "${RED}This value is required.${NC}"
    done
}

# confirm_yes <varname> <prompt> [default] — Y/n or y/N; writes "y"/"n".
confirm_yes() {
    local varname="$1" prompt="$2" default="${3:-y}" answer
    while true; do
        if [ "$default" = "y" ]; then
            printf '%s [Y/n]: ' "$prompt"
        else
            printf '%s [y/N]: ' "$prompt"
        fi
        if ! IFS= read -r answer; then
            echo -e "${RED}Input closed — aborting setup.${NC}"
            return 2
        fi
        case "${answer:-$default}" in
            [Yy]|[Yy][Ee][Ss]) printf -v "$varname" '%s' "y"; return 0 ;;
            [Nn]|[Nn][Oo])     printf -v "$varname" '%s' "n"; return 0 ;;
            *) echo -e "${RED}Please answer y or n.${NC}" ;;
        esac
    done
}

# Host timezone: /etc/timezone (Debian/Ubuntu) or /etc/localtime symlink; fallback UTC.
detect_tz() {
    local tz=""
    if [ -f /etc/timezone ]; then
        tz="$(tr -d '[:space:]' < /etc/timezone)"
    elif [ -L /etc/localtime ]; then
        tz="$(readlink /etc/localtime | sed 's|^.*/zoneinfo/||')"
    fi
    if [ -z "$tz" ] || [ "$tz" = "Etc/UTC" ] || [ "$tz" = "Etc/GMT" ]; then
        tz="UTC"
    fi
    echo "$tz"
}

validate_no_ws() { # value must be non-empty and free of whitespace and '/'
    [ -n "$1" ] && ! printf '%s' "$1" | grep -qE '[[:space:]]|/'
}

validate_tz() { # timezone must look like Area/City (may contain '/', no spaces)
    [ -n "$1" ] && ! printf '%s' "$1" | grep -qE '[[:space:]]'
}

validate_email() { # must contain '@'
    printf '%s' "$1" | grep -q '@'
}

validate_port() { # integer 1..65535, no whitespace
    printf '%s' "$1" | grep -qE '^[0-9]+$' && [ "$1" -ge 1 ] && [ "$1" -le 65535 ]
}

# --- Wizard ----------------------------------------------------------------

configure_env() {
    local ENV_FILE=".env"
    local FIRST_RUN=false

    # Non-interactive (CI, scripts): keep the old silent behavior.
    if ! is_interactive; then
        if [ ! -f "$ENV_FILE" ] && [ -f .env.example ]; then
            cp .env.example "$ENV_FILE"
            echo -e "${YELLOW}Creating .env from .env.example (non-interactive)${NC}"
        fi
        return 0
    fi

    # First run: copy the template, then walk through the questions.
    if [ ! -f "$ENV_FILE" ]; then
        if [ ! -f .env.example ]; then
            echo -e "${RED}❌ .env.example not found — cannot create .env${NC}"
            return 1
        fi
        cp .env.example "$ENV_FILE"
        FIRST_RUN=true
        echo -e "${GREEN}✓ Created .env from .env.example${NC}"

        # Existing database volume predates this .env: MariaDB bakes
        # user/password at first volume init and ignores new MARIADB_* values,
        # so fresh wizard passwords would not match it ('Access denied').
        local project="${COMPOSE_PROJECT_NAME:-$(basename "$PWD")}"
        local db_volume="${project}_mariadb-data"
        if command -v docker >/dev/null 2>&1 && docker volume inspect "$db_volume" >/dev/null 2>&1; then
            echo ""
            echo -e "${YELLOW}⚠ Existing database volume '${db_volume}' found.${NC}"
            echo -e "   Its passwords were set when the volume was first created; the"
            echo -e "   new passwords from this wizard will NOT be applied to it and"
            echo -e "   the store would fail with 'Access denied'. Restore the old"
            echo -e "   .env (if you have a backup) or reset the database volume."
            local reset_db
            confirm_yes reset_db "Reset the database volume now (ALL its data will be lost)?" "n" || return $?
            if [ "$reset_db" = "y" ]; then
                if docker volume rm "$db_volume"; then
                    echo -e "${GREEN}✓ Database volume removed — it will be recreated fresh.${NC}"
                else
                    echo -e "${RED}✗ Could not remove the volume (is a container using it?).${NC}"
                    echo -e "   Stop the stack (make down) and run 'make start' again."
                fi
            else
                echo -e "${YELLOW}Keeping the volume. If the old .env is lost, run 'make down -v' to reset the database.${NC}"
            fi
        fi
        echo ""
    fi

    echo -e "${BLUE}Step 3 — Configure your DockerCart environment${NC}"
    echo -e "${YELLOW}Press Enter to accept the suggested value (passwords are generated randomly).${NC}"
    echo ""

    local answer=""

    # --- 0. Instance / project name ------------------------------------------
    # Namespaces containers, network and volumes so multiple instances can run
    # on one host. Defaults to the directory basename (already used by Compose
    # for the project name), so a clone in a new folder is isolated with no
    # manual .env edits. Must be unique per instance.
    local project
    project="$(get_env_key "$ENV_FILE" COMPOSE_PROJECT_NAME)"
    if [ -z "$project" ]; then
        project="${COMPOSE_PROJECT_NAME:-$(basename "$PWD")}"
    fi
    if [ "$FIRST_RUN" = true ]; then
        ask_value project "Instance/project name (namespaces containers, network and volumes)" "$project" || return $?
        while ! validate_no_ws "$project"; do
            echo -e "${RED}Name must not contain spaces or '/'.${NC}"
            ask_value project "Instance/project name (namespaces containers, network and volumes)" "$project" || return $?
        done
        set_env_key "$ENV_FILE" COMPOSE_PROJECT_NAME "$project"
        set_env_key "$ENV_FILE" DOCKERCART_NETWORK "${project}-network"
        set_env_key "$ENV_FILE" DOCKERCART_ROUTER_NAME "$project"
        echo -e "${GREEN}✓ Project: ${project} (network ${project}-network, router ${project})${NC}"
    fi

    # --- 1. Store domain -----------------------------------------------------
    local domain
    domain="$(get_env_key "$ENV_FILE" DOCKERCART_DOMAIN)"
    # On the very first run always ask (Enter = default); later only when the
    # stored value is missing or invalid ("memory").
    if [ "$FIRST_RUN" = true ] || [ -z "$domain" ] || [ "$domain" = "example.com" ]; then
        ask_value domain "Store domain" "dockercart.local" || return $?
        while ! validate_no_ws "$domain"; do
            echo -e "${RED}Domain must not contain spaces or '/'.${NC}"
            ask_value domain "Store domain" "$domain" || return $?
        done
        set_env_key "$ENV_FILE" DOCKERCART_DOMAIN "$domain"
        echo -e "${GREEN}✓ Domain: ${domain}${NC}"
    fi

    # --- 1b. Listen port (HTTP) ----------------------------------------------
    # Nginx binds DOCKERCART_HTTP_PORT on the host; the app URL is derived from
    # it so internal links / config_url / robots.txt stay consistent. 80 is the
    # default and is omitted from the URL; :443 is likewise omitted for HTTPS.
    local http_port
    http_port="$(get_env_key "$ENV_FILE" DOCKERCART_HTTP_PORT)"
    if [ "$FIRST_RUN" = true ] || [ -z "$http_port" ]; then
        ask_value http_port "HTTP listen port (host; 80 = default, omit from URL)" "80" || return $?
        while ! validate_port "$http_port"; do
            echo -e "${RED}Port must be an integer between 1 and 65535.${NC}"
            ask_value http_port "HTTP listen port (host; 80 = default, omit from URL)" "80" || return $?
        done
        set_env_key "$ENV_FILE" DOCKERCART_HTTP_PORT "$http_port"

        local https_port
        https_port="$(get_env_key "$ENV_FILE" DOCKERCART_HTTPS_PORT)"
        if [ -z "$https_port" ]; then
            https_port="443"
        fi
        set_env_key "$ENV_FILE" DOCKERCART_HTTPS_PORT "$https_port"

        # Derive DOCKERCART_URL / DOCKERCART_HTTPS_URL with the port embedded
        # (omit :80 / :443). start.sh re-derives these on every run, but we keep
        # the stored values correct here for non-start.sh consumers.
        local url_suffix=""
        if [ "$http_port" != "80" ]; then
            url_suffix=":${http_port}"
        fi
        set_env_key "$ENV_FILE" DOCKERCART_URL "http://${domain}${url_suffix}"

        local https_suffix=""
        if [ "$https_port" != "443" ]; then
            https_suffix=":${https_port}"
        fi
        set_env_key "$ENV_FILE" DOCKERCART_HTTPS_URL "https://${domain}${https_suffix}"
        echo -e "${GREEN}✓ Store URL: http://${domain}${url_suffix}${NC}"
    fi

    # --- 2. Timezone ---------------------------------------------------------
    local tz
    tz="$(get_env_key "$ENV_FILE" TZ)"
    if [ "$FIRST_RUN" = true ] || [ -z "$tz" ]; then
        local host_tz
        host_tz="$(detect_tz)"
        ask_value tz "Timezone (Area/City)" "$host_tz" || return $?
        while ! validate_tz "$tz"; do
            echo -e "${RED}Timezone must look like Area/City (e.g. Europe/Kiev).${NC}"
            ask_value tz "Timezone (Area/City)" "$tz" || return $?
        done
        set_env_key "$ENV_FILE" TZ "$tz"
        echo -e "${GREEN}✓ Timezone: ${tz}${NC}"
    fi

    # --- 3. Database password ------------------------------------------------
    local db_pass
    db_pass="$(get_env_key "$ENV_FILE" DB_PASSWORD)"
    if [ -z "$db_pass" ] || [ "$db_pass" = "dockercart_password" ]; then
        local gen
        gen="$(gen_password)"
        ask_value db_pass "Database password (Enter = generated)" "$gen" || return $?
        set_env_key "$ENV_FILE" DB_PASSWORD "$db_pass"
        set_env_key "$ENV_FILE" MARIADB_PASSWORD "$db_pass"
        echo -e "${GREEN}✓ Database password set${NC}"
    fi

    # --- 4. MariaDB root password --------------------------------------------
    local root_pass
    root_pass="$(get_env_key "$ENV_FILE" MARIADB_ROOT_PASSWORD)"
    if [ -z "$root_pass" ] || [ "$root_pass" = "root_password" ]; then
        local gen_root
        gen_root="$(gen_password)"
        ask_value root_pass "MariaDB root password (Enter = generated)" "$gen_root" || return $?
        set_env_key "$ENV_FILE" MARIADB_ROOT_PASSWORD "$root_pass"
        echo -e "${GREEN}✓ MariaDB root password set${NC}"
    fi

    # --- 4b. Redis password -------------------------------------------------
    local redis_pass
    redis_pass="$(get_env_key "$ENV_FILE" REDIS_PASSWORD)"
    if [ -z "$redis_pass" ] || [ "$redis_pass" = "dockercart_redis_pass" ]; then
        local gen_redis
        gen_redis="$(gen_password)"
        ask_value redis_pass "Redis password (Enter = generated)" "$gen_redis" || return $?
        set_env_key "$ENV_FILE" REDIS_PASSWORD "$redis_pass"
        echo -e "${GREEN}✓ Redis password set${NC}"
    fi

    # --- 4c. FTP password (only when ftp profile is enabled) ---------------
    local ftp_profile
    ftp_profile="$(get_env_key "$ENV_FILE" FTP_PROFILE)"
    if [ "${ftp_profile:-ftp}" = "ftp" ]; then
        local ftp_pass
        ftp_pass="$(get_env_key "$ENV_FILE" FTP_PASS)"
        if [ -z "$ftp_pass" ] || [ "$ftp_pass" = "change_me_please" ]; then
            local gen_ftp
            gen_ftp="$(gen_password)"
            ask_value ftp_pass "FTP password (Enter = generated)" "$gen_ftp" || return $?
            set_env_key "$ENV_FILE" FTP_PASS "$ftp_pass"
            echo -e "${GREEN}✓ FTP password set${NC}"
        fi
    fi

    # --- 5. Admin account ----------------------------------------------------
    local admin_user
    admin_user="$(get_env_key "$ENV_FILE" ADMIN_USERNAME)"
    if [ -z "$admin_user" ]; then
        ask_value admin_user "Admin username" "admin" || return $?
        while ! printf '%s' "$admin_user" | grep -qE '^[A-Za-z0-9_.-]+$'; do
            echo -e "${RED}Username may contain only letters, digits, '.', '_' and '-'.${NC}"
            ask_value admin_user "Admin username" "$admin_user" || return $?
        done
        set_env_key "$ENV_FILE" ADMIN_USERNAME "$admin_user"
        echo -e "${GREEN}✓ Admin username: ${admin_user}${NC}"
    fi

    local admin_pass
    admin_pass="$(get_env_key "$ENV_FILE" ADMIN_PASSWORD)"
    if [ -z "$admin_pass" ] || [ "$admin_pass" = "admin123" ]; then
        local gen_admin
        gen_admin="$(gen_password)"
        ask_value admin_pass "Admin password (Enter = generated)" "$gen_admin" || return $?
        set_env_key "$ENV_FILE" ADMIN_PASSWORD "$admin_pass"
        echo -e "${GREEN}✓ Admin password set${NC}"
    fi

    local admin_email
    admin_email="$(get_env_key "$ENV_FILE" ADMIN_EMAIL)"
    if [ -z "$admin_email" ] || [ "$admin_email" = "admin@example.com" ]; then
        ask_value admin_email "Admin email" "" || return $?
        while ! validate_email "$admin_email"; do
            echo -e "${RED}Please enter a valid email address.${NC}"
            ask_value admin_email "Admin email" "" || return $?
        done
        set_env_key "$ENV_FILE" ADMIN_EMAIL "$admin_email"
        echo -e "${GREEN}✓ Admin email: ${admin_email}${NC}"
    fi

    # --- 6. Seed mode (demo data on first install) ---------------------------
    local seed_mode
    seed_mode="$(get_env_key "$ENV_FILE" DOCKERCART_SEED_MODE)"
    if [ -z "$seed_mode" ]; then
        local seed_answer
        confirm_yes seed_answer "Install with demo data?" "y" || return $?
        if [ "$seed_answer" = "y" ]; then
            set_env_key "$ENV_FILE" DOCKERCART_SEED_MODE "demo"
            echo -e "${GREEN}✓ Installing WITH demo data${NC}"
        else
            set_env_key "$ENV_FILE" DOCKERCART_SEED_MODE "clean"
            echo -e "${GREEN}✓ Installing WITHOUT demo data (clean store)${NC}"
        fi
    fi

    # --- 7. Let's Encrypt (only when running in LE mode) ---------------------
    if [ "${SSL_MODE:-none}" = "letsencrypt" ]; then
        local ssl_domain
        ssl_domain="$(get_env_key "$ENV_FILE" SSL_DOMAIN)"
        if [ -z "$ssl_domain" ] || [ "$ssl_domain" = "example.com" ]; then
            echo -e "${YELLOW}Let's Encrypt requires a real domain and email.${NC}"
            ask_value ssl_domain "SSL domain (public DNS, ports 80/443 open)" "" || return $?
            while ! validate_no_ws "$ssl_domain" || [ "$ssl_domain" = "example.com" ]; do
                echo -e "${RED}Please enter a real domain (not example.com).${NC}"
                ask_value ssl_domain "SSL domain (public DNS, ports 80/443 open)" "" || return $?
            done
            set_env_key "$ENV_FILE" SSL_DOMAIN "$ssl_domain"
            echo -e "${GREEN}✓ SSL domain: ${ssl_domain}${NC}"
        fi

        local ssl_email
        ssl_email="$(get_env_key "$ENV_FILE" SSL_EMAIL)"
        if [ -z "$ssl_email" ] || [ "$ssl_email" = "admin@example.com" ]; then
            ask_value ssl_email "SSL certificate email (for Let's Encrypt)" "" || return $?
            while ! validate_email "$ssl_email"; do
                echo -e "${RED}Please enter a valid email address.${NC}"
                ask_value ssl_email "SSL certificate email (for Let's Encrypt)" "" || return $?
            done
            set_env_key "$ENV_FILE" SSL_EMAIL "$ssl_email"
            echo -e "${GREEN}✓ SSL email: ${ssl_email}${NC}"
        fi
    fi

    echo -e "${GREEN}✓ Environment configuration complete.${NC}"
    echo ""
}

configure_env
