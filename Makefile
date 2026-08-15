ifneq (,$(wildcard .env))
include .env
export
endif

.PHONY: help migrate up update start stop ssl le le-ftp ftp down logs logs-follow shell mariadb backup restore backup-s3 dump-init clean restart traefik traefik-ssl traefik-le scheduler-logs scheduler-restart scheduler-reload scheduler-shell scheduler-status

### Convenience variables
# Base compose file used by commands that don't need to match the running mode
# (e.g. migrate, mariadb, shell — single service, base file is always present).
COMPOSE := docker compose -f docker-compose.yml

# Active compose file set: when the stack was started via start.sh (or a `make`
# mode target), the chosen -f files are recorded in .env as
# DOCKERCART_COMPOSE_FILES (space-separated filenames). If present, restart/stop/down
# reconstruct the exact same mode (Traefik + SSL overrides) instead of falling
# back to standalone HTTP.
ifeq ($(DOCKERCART_COMPOSE_FILES),)
ACTIVE_COMPOSE := $(COMPOSE)
else
ACTIVE_COMPOSE_FILES := $(foreach f,$(DOCKERCART_COMPOSE_FILES),-f $(f))
ACTIVE_COMPOSE := docker compose $(ACTIVE_COMPOSE_FILES)
endif

help: ## Show this help
	@echo ""
	@echo "DockerCart - Docker Compose Stack"
	@echo ""
	@grep -hE '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'
	@echo ""
	@echo "Modes (default: standalone, no Traefik needed):"
	@echo "  make start       Two-step interactive setup (1: Standalone/Traefik, 2: SSL; remembers last choice)"
	@echo "  make up          HTTP mode       - http://$${DOCKERCART_DOMAIN:-dockercart.local}"
	@echo "  make ssl         HTTPS SSL       - https://$${DOCKERCART_DOMAIN:-dockercart.local} (self-signed, local testing)"
	@echo "  make le          HTTPS + LE      - Production with real domain SSL (requires SSL_DOMAIN in .env)"
	@echo ""
	@echo "Traefik mode (external reverse proxy):"
	@echo "  make traefik          No SSL      - Traefik, HTTP"
	@echo "  make traefik-ssl      HTTPS       - Traefik + self-signed"
	@echo "  make traefik-le       HTTPS       - Traefik + Let's Encrypt"
	@echo ""
	@echo "Other commands:"
	@echo "  make down         Stop containers (base compose only)"
	@echo "  make stop         Stop all containers, regardless of start mode"
	@echo "  make restart      Restart containers"
	@echo "  make logs         Show logs"
	@echo "  make shell        Bash into app container"
	@echo "  make mariadb      Open MariaDB CLI"
	@echo "  make backup       Dump DB to ./backups/"
	@echo ""
	@echo "See README.md for full documentation."

migrate: ## Apply tracked SQL migrations from docker/mysql/migrations (idempotent)
	@echo "Applying tracked SQL migrations from docker/mysql/migrations/..."
	@$(ACTIVE_COMPOSE) exec -T apache bash /var/www/dc-scripts/run-migrations.sh || { \
		echo "Migration runner failed (DB not ready or apache not running?)."; exit 1; }

update: ## Pull code changes and apply migrations via update.sh
	@./update.sh

up: ## Start in standalone mode, HTTP by default (use make ssl or make le for HTTPS)
	@./start.sh

start: ## Start the stack — interactive mode selection (choice is remembered in .env)
	@./start.sh --menu

ssl: ## Start standalone with self-signed SSL (HTTPS, local testing)
	@./start.sh --ssl

le: ## Start standalone with Let's Encrypt SSL (production, requires real domain)
	@./start.sh --le

le-ftp: ## Start Let's Encrypt mode and enable FTP profile (images only)
	@$(MAKE) le
	@docker compose --profile ftp up -d ftp
	@echo ""
	@echo "Let's Encrypt + FTP enabled"

ftp: ## Start stack with optional FTP server (access only to ./upload/image)
	@docker compose --profile ftp up -d ftp
	@echo ""
	@echo "FTP enabled on port $${FTP_PORT:-21} (passive: $${FTP_PASV_MIN_PORT:-21100}-$${FTP_PASV_MAX_PORT:-21110})"
	@echo "User: $${FTP_USER:-images}"

traefik: ## Start in Traefik mode, HTTP (use traefik-ssl or traefik-le for HTTPS)
	@./start.sh --traefik

traefik-ssl: ## Start Traefik mode with self-signed SSL (HTTPS)
	@./start.sh --traefik --ssl

traefik-le: ## Start Traefik mode with Let's Encrypt SSL (production)
	@./start.sh --traefik --le

down: ## Stop containers (uses the active mode's compose files when recorded)
	@$(ACTIVE_COMPOSE) down || true

stop: ## Stop all containers, regardless of the mode the stack was started in
	@$(ACTIVE_COMPOSE) down || true
	@echo "Containers stopped"

restart: ## Restart containers (rebuild + recreate) in the active mode
	@$(ACTIVE_COMPOSE) up -d --build --force-recreate --remove-orphans

logs: ## Show last 100 log lines
	@$(ACTIVE_COMPOSE) logs --tail=100

logs-follow: ## Follow logs in real time
	@$(ACTIVE_COMPOSE) logs -f

shell: ## Open bash shell in the app container
	@$(ACTIVE_COMPOSE) exec apache bash

scheduler-logs: ## Show scheduler container logs
	@$(ACTIVE_COMPOSE) logs -f scheduler

scheduler-restart: ## Restart scheduler container
	@$(ACTIVE_COMPOSE) restart scheduler

scheduler-reload: ## Reload scheduler code without restart (SIGHUP)
	@echo "Sending SIGHUP to scheduler (code reload)..."
	@$(ACTIVE_COMPOSE) kill -s HUP scheduler

scheduler-shell: ## Open bash in scheduler container
	@$(ACTIVE_COMPOSE) exec scheduler bash

scheduler-status: ## Check scheduler health
	@$(ACTIVE_COMPOSE) exec scheduler pgrep -f dockercart_scheduler.php && echo "Scheduler: RUNNING" || echo "Scheduler: NOT RUNNING"

mariadb: ## Open MariaDB CLI
	@$(COMPOSE) exec -e MYSQL_PWD=$${MARIADB_PASSWORD:-dockercart_password} mariadb mariadb -u$${MARIADB_USER:-dockercart} $${MARIADB_DATABASE:-dockercart}

backup: ## Dump database to ./backups/
	@mkdir -p backups
	@$(COMPOSE) exec -e MYSQL_PWD=$${MARIADB_PASSWORD:-dockercart_password} mariadb mariadb-dump -u$${MARIADB_USER:-dockercart} $${MARIADB_DATABASE:-dockercart} > backups/backup_$$(date +%Y%m%d_%H%M%S).sql
	@echo "Backup created"

restore: ## Restore from the latest dump in ./backups/
	@if [ -z "$$(ls -A backups/*.sql 2>/dev/null)" ]; then \
		echo "No backups found in ./backups/"; exit 1; \
	fi
	@LATEST=$$(ls -t backups/*.sql | head -1); \
	echo "Restoring $$LATEST"; \
	$(COMPOSE) exec -T -e MYSQL_PWD=$${MARIADB_PASSWORD:-dockercart_password} mariadb mariadb -u$${MARIADB_USER:-dockercart} $${MARIADB_DATABASE:-dockercart} < $$LATEST
	@echo "Restored"

backup-s3: ## Run S3 backup worker manually (one-shot; needs BACKUP_S3_* in .env)
	@COMPOSE_PROFILES=backup $(COMPOSE) run --rm --no-deps backup-worker

dump-init: ## Regenerate docker/mysql/init.sql from running MariaDB
	@mkdir -p docker/mysql
	@echo "Backing up existing docker/mysql/init.sql to docker/mysql/init.sql.bak.$$(date -u +%Y%m%dT%H%M%SZ)"
	@cp -a docker/mysql/init.sql docker/mysql/init.sql.bak.$$(date -u +%Y%m%dT%H%M%SZ) || true
	@TMP_FILE=$$(mktemp docker/mysql/init.sql.tmp.XXXXXX); \
	echo "Generating new dump (may take some time)..."; \
	if ! $(COMPOSE) exec -T -e MYSQL_PWD=$${MARIADB_PASSWORD:-dockercart_password} mariadb sh -c 'mariadb-dump -u"$${MARIADB_USER:-dockercart}" "$${MARIADB_DATABASE:-dockercart}" --single-transaction --quick --hex-blob --routines --triggers --events --default-character-set=utf8mb4' | sed -e 's/DEFINER=[^ ]*//g' | sed "s/,'config_encryption','[^']*'/,'config_encryption',''/g" > $$TMP_FILE; then \
		rm -f $$TMP_FILE; \
		echo "Dump failed"; \
		exit 1; \
	elif [ $${PIPESTATUS[0]} -ne 0 ]; then \
		rm -f $$TMP_FILE; \
		echo "Dump failed (mariadb-dump exit code $${PIPESTATUS[0]})"; \
		exit 1; \
	fi; \
	mv $$TMP_FILE docker/mysql/init.sql; \
	echo "Dump written to docker/mysql/init.sql — review and commit when ready."

clean: ## DESTRUCTIVE: Stop containers and remove all volumes
	@echo "WARNING: All database data will be lost."
	@read -p "Continue? (y/N): " confirm && [ "$$confirm" = "y" ] || exit 1
	@$(COMPOSE) down -v
	@echo "Cleaned"
