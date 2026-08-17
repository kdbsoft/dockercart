#!/bin/bash
# shellcheck source=./scripts/project-name.sh
# First-run .env configuration for DockerCart.
# Sourced by start.sh: creates .env from .env.example when it is missing (or
# when DOCKERCART_ENV_FORCE_WIZARD=1 forces a redo).
#
# Design goals:
#   - Minimal interaction. Ask only for the store domain and the admin email.
#     Everything else (timezone, project name, ports, admin username, seed mode)
#     gets a sane default, and every password is generated randomly.
#   - Safe for automation. With no TTY or DOCKERCART_NONINTERACTIVE=1 the script
#     copies .env.example and generates random passwords without any prompts.
#   - No separate mode wizard: the launch/SSL mode is chosen via start.sh flags
#     (--traefik/--ssl/--le) and remembered in DOCKERCART_COMPOSE_FILES.

# Colors (redefine locally — start.sh may source us before its own color vars)
BLUE='\033[0;34m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
RED='\033[0;31m'
NC='\033[0m'

# --- Helpers ---------------------------------------------------------------

# Load the shared project-name sanitizer (also sourced by start.sh).
# shellcheck disable=SC1091
. ./scripts/project-name.sh

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
        printf '\n# Configured by DockerCart setup\n%s=%s\n' "$key" "$value" >> "$file"
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
# variable. Enter accepts the default; empty default means the answer is
# mandatory. Returns 0 on success, 2 when stdin is closed (abort).
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

validate_email() { # must contain '@'
    printf '%s' "$1" | grep -q '@'
}

# Generate a fresh random password for every secret key that still carries a
# known default/placeholder, and write it to .env. Prints a summary of what was
# generated. This runs for BOTH interactive and non-interactive first runs so a
# freshly generated .env never ships with the well-known example passwords.
generate_secrets() {
    local env_file="$1"

    # key -> default that should be replaced
    local pairs="DB_PASSWORD:dockercart_password \
MARIADB_ROOT_PASSWORD:root_password \
MARIADB_PASSWORD:dockercart_password \
REDIS_PASSWORD:dockercart_redis_pass \
ADMIN_PASSWORD:type_password_here \
FTP_PASS:change_me_please \
HEALTHCHECK_TOKEN:"

    local generated=()
    local key default current gen
    for pair in $pairs; do
        key="${pair%%:*}"
        default="${pair##*:}"
        current="$(get_env_key "$env_file" "$key")"
        if [ -z "$current" ] || [ "$current" = "$default" ]; then
            gen="$(gen_password)"
            set_env_key "$env_file" "$key" "$gen"
            # Keep MARIADB_PASSWORD in sync with DB_PASSWORD (same account).
            if [ "$key" = "DB_PASSWORD" ]; then
                set_env_key "$env_file" "MARIADB_PASSWORD" "$gen"
                gen="$gen  (shared by DB_PASSWORD and MARIADB_PASSWORD)"
            fi
            generated+=("${key}=${gen}")
        fi
    done

    if [ "${#generated[@]}" -gt 0 ]; then
        echo -e "${YELLOW}Generated random passwords (saved to .env):${NC}"
        for line in "${generated[@]}"; do
            echo -e "  ${GREEN}${line}${NC}"
        done
        echo ""
    fi
}

# --- Wizard ----------------------------------------------------------------

configure_env() {
    local ENV_FILE=".env"

    # Non-interactive (CI, pipes, DOCKERCART_NONINTERACTIVE=1): copy the template
    # and generate random secrets. No prompts, identical behavior every time.
    if ! is_interactive; then
        if [ ! -f "$ENV_FILE" ] && [ -f .env.example ]; then
            cp .env.example "$ENV_FILE"
            echo -e "${YELLOW}Creating .env from .env.example (non-interactive)${NC}"
        fi
        if [ -f "$ENV_FILE" ]; then
            generate_secrets "$ENV_FILE"
        fi
        return 0
    fi

    # First run: copy the template, then ask the two questions.
    if [ ! -f "$ENV_FILE" ]; then
        if [ ! -f .env.example ]; then
            echo -e "${RED}❌ .env.example not found — cannot create .env${NC}"
            return 1
        fi
        cp .env.example "$ENV_FILE"
        echo -e "${GREEN}✓ Created .env from .env.example${NC}"
        local first_run=true

        # Existing database volume predates this .env: MariaDB bakes
        # user/password at first volume init and ignores new MARIADB_* values,
        # so fresh wizard passwords would not match it ('Access denied').
        local project="${COMPOSE_PROJECT_NAME:-$(basename "$PWD")}"
        local db_volume="${project}_mariadb-data"
        if command -v docker >/dev/null 2>&1 && docker volume inspect "$db_volume" >/dev/null 2>&1; then
            echo ""
            echo -e "${YELLOW}⚠ Existing database volume '${db_volume}' found.${NC}"
            echo -e "   Its passwords were set when the volume was first created; the"
            echo -e "   new passwords from this setup will NOT be applied to it and"
            echo -e "   the store would fail with 'Access denied'. Restore the old"
            echo -e "   .env (if you have a backup) or reset the database volume."
            local reset_db
            if ! ask_value reset_db "Reset the database volume now? (ALL its data will be lost) [y/N]" "n"; then
                return $?
            fi
            if [ "$reset_db" = "y" ] || [ "$reset_db" = "Y" ]; then
                if docker volume rm "$db_volume"; then
                    echo -e "${GREEN}✓ Database volume removed — it will be recreated fresh.${NC}"
                else
                    echo -e "${RED}✗ Could not remove the volume (is a container using it?).${NC}"
                    echo -e "   Stop the stack (make stop) and run 'make start' again."
                fi
            else
                echo -e "${YELLOW}Keeping the volume. If the old .env is lost, run 'make clean' to reset the database.${NC}"
            fi
        fi
        echo ""
    fi

    echo -e "${BLUE}DockerCart first-run setup${NC}"
    echo -e "${YELLOW}Only two values are required; everything else uses sane defaults.${NC}"
    echo -e "${YELLOW}Press Enter to accept the suggested value.${NC}"
    echo ""

    # --- 1. Store domain -----------------------------------------------------
    # On a true first run (just copied from the template) always prompt so the
    # operator sets the real domain; only skip when .env already had a value
    # (e.g. re-running the wizard, or FORCE_WIZARD on an existing file).
    local domain
    domain="$(get_env_key "$ENV_FILE" DOCKERCART_DOMAIN)"
    if [ "${first_run:-false}" = "true" ] || [ -z "$domain" ] || [ "$domain" = "example.com" ]; then
        ask_value domain "Store domain" "dockercart.local" || return $?
        while ! validate_no_ws "$domain"; do
            echo -e "${RED}Domain must not contain spaces or '/'.${NC}"
            ask_value domain "Store domain" "dockercart.local" || return $?
        done
        set_env_key "$ENV_FILE" DOCKERCART_DOMAIN "$domain"
        echo -e "${GREEN}✓ Domain: ${domain}${NC}"
    fi

    # --- 2. Admin email ------------------------------------------------------
    local admin_email
    admin_email="$(get_env_key "$ENV_FILE" ADMIN_EMAIL)"
    if [ "${first_run:-false}" = "true" ] || [ -z "$admin_email" ] || [ "$admin_email" = "admin@example.com" ]; then
        ask_value admin_email "Admin email" "" || return $?
        while ! validate_email "$admin_email"; do
            echo -e "${RED}Please enter a valid email address.${NC}"
            ask_value admin_email "Admin email" "" || return $?
        done
        set_env_key "$ENV_FILE" ADMIN_EMAIL "$admin_email"
        echo -e "${GREEN}✓ Admin email: ${admin_email}${NC}"
    fi

    # --- 3. Seed mode (demo / clean) -----------------------------------------
    # Only prompt on a forced re-run (make clean -> make start, which sets
    # DOCKERCART_ENV_FORCE_WIZARD=1) or when the key is missing/empty. The normal
    # first-run wizard keeps the template default (demo) to stay minimal; the
    # operator can edit DOCKERCART_SEED_MODE in .env later.
    local force_wizard="${DOCKERCART_ENV_FORCE_WIZARD:-0}"
    local seed_mode
    seed_mode="$(get_env_key "$ENV_FILE" DOCKERCART_SEED_MODE)"
    if [ "$force_wizard" = "1" ] || [ -z "$seed_mode" ]; then
        echo ""
        echo -e "${BLUE}Seed mode${NC}"
        echo -e "  ${GREEN}demo${NC}  — pre-populated sample store (products, categories)"
        echo -e "  ${GREEN}clean${NC} — empty store, no demo content"
        local seed_default="demo"
        [ -n "$seed_mode" ] && seed_default="$seed_mode"
        ask_value seed_mode "Seed mode (demo/clean)" "$seed_default" || return $?
        while [ "$seed_mode" != "demo" ] && [ "$seed_mode" != "clean" ]; do
            echo -e "${RED}Please enter 'demo' or 'clean'.${NC}"
            ask_value seed_mode "Seed mode (demo/clean)" "$seed_default" || return $?
        done
        set_env_key "$ENV_FILE" DOCKERCART_SEED_MODE "$seed_mode"
        echo -e "${GREEN}✓ Seed mode: ${seed_mode}${NC}"
    fi

    # --- Defaults (no prompts) ----------------------------------------------
    # Project name namespacing (containers, network, volumes).
    # Derive from the directory basename unless the operator explicitly set a
    # different value. A cloned/copied .env still carries the template default
    # "dockercart", which must be replaced with the real basename so sibling
    # checkouts stay isolated. Folder names with spaces or shell-special
    # characters are sanitized into a valid Compose project name.
    local project
    project="$(get_env_key "$ENV_FILE" COMPOSE_PROJECT_NAME)"
    if [ -z "$project" ] || [ "$project" = "dockercart" ]; then
        project="$(sanitize_project_name "$(basename "$PWD")")"
        set_env_key "$ENV_FILE" COMPOSE_PROJECT_NAME "$project"
        set_env_key "$ENV_FILE" DOCKERCART_NETWORK "${project}-network"
        set_env_key "$ENV_FILE" DOCKERCART_ROUTER_NAME "$project"
    else
        # Explicit override: ensure it is a valid Compose project name. Only
        # rewrite (with a warning) when it contains illegal characters; a
        # syntactically valid value (incl. uppercase) is left untouched.
        if ! printf '%s' "$project" | grep -qE '^[a-zA-Z0-9][a-zA-Z0-9_.-]*$'; then
            local fixed
            fixed="$(sanitize_project_name "$project")"
            echo -e "${YELLOW}⚠ COMPOSE_PROJECT_NAME '$project' is not a valid project name; using '$fixed'.${NC}"
            project="$fixed"
            set_env_key "$ENV_FILE" COMPOSE_PROJECT_NAME "$project"
            set_env_key "$ENV_FILE" DOCKERCART_NETWORK "${project}-network"
            set_env_key "$ENV_FILE" DOCKERCART_ROUTER_NAME "$project"
        fi
    fi

    # Timezone: auto-detect from the host.
    local tz
    tz="$(get_env_key "$ENV_FILE" TZ)"
    if [ -z "$tz" ]; then
        set_env_key "$ENV_FILE" TZ "$(detect_tz)"
    fi

    # Admin username default.
    local admin_user
    admin_user="$(get_env_key "$ENV_FILE" ADMIN_USERNAME)"
    if [ -z "$admin_user" ]; then
        set_env_key "$ENV_FILE" ADMIN_USERNAME "admin"
    fi

    # Ports default to 80/443 (omitted from the derived URL by start.sh).
    local http_port
    http_port="$(get_env_key "$ENV_FILE" DOCKERCART_HTTP_PORT)"
    if [ -z "$http_port" ]; then
        set_env_key "$ENV_FILE" DOCKERCART_HTTP_PORT "80"
        set_env_key "$ENV_FILE" DOCKERCART_HTTPS_PORT "443"
    fi

    # Seed mode default (demo; edit .env for a clean store).
    local seed_mode
    seed_mode="$(get_env_key "$ENV_FILE" DOCKERCART_SEED_MODE)"
    if [ -z "$seed_mode" ]; then
        set_env_key "$ENV_FILE" DOCKERCART_SEED_MODE "demo"
    fi

    # --- Random secrets ------------------------------------------------------
    generate_secrets "$ENV_FILE"

    # --- Summary -------------------------------------------------------------
    echo -e "${GREEN}✓ Setup complete.${NC}"
    echo ""
    echo -e "${BLUE}Summary${NC}"
    echo -e "  Domain:        ${GREEN}${domain:-$(get_env_key "$ENV_FILE" DOCKERCART_DOMAIN)}${NC}"
    echo -e "  Admin URL:     ${GREEN}http://${domain:-$(get_env_key "$ENV_FILE" DOCKERCART_DOMAIN)}/admin${NC}"
    echo -e "  Admin user:    ${GREEN}$(get_env_key "$ENV_FILE" ADMIN_USERNAME)${NC}"
    echo -e "  Admin email:   ${GREEN}$(get_env_key "$ENV_FILE" ADMIN_EMAIL)${NC}"
    echo -e "  Seed mode:     ${GREEN}$(get_env_key "$ENV_FILE" DOCKERCART_SEED_MODE)${NC}"
    echo -e "  Timezone:      ${GREEN}$(get_env_key "$ENV_FILE" TZ)${NC}"
    echo ""
    echo -e "${YELLOW}All passwords were generated randomly and saved to .env.${NC}"
    echo -e "${YELLOW}Edit .env to change any value, then re-run 'make start' if needed.${NC}"
    echo ""

    # If this was a forced re-run (make clean -> make start), consume the flag so
    # the next ordinary `make start` does not re-prompt the wizard every time.
    if [ "${DOCKERCART_ENV_FORCE_WIZARD:-0}" = "1" ]; then
        if grep -qE '^DOCKERCART_ENV_FORCE_WIZARD=' "$ENV_FILE"; then
            sed -i '/^DOCKERCART_ENV_FORCE_WIZARD=/d' "$ENV_FILE"
        fi
    fi
}

configure_env
