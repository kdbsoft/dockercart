ifneq (,$(wildcard .env))
include .env
export
endif

.PHONY: help migrate up standalone standalone-letsencrypt ssl letsencrypt letsencrypt-ftp ftp down logs logs-follow shell mariadb backup restore dump-init clean restart

### Convenience variables
COMPOSE := docker compose
SHELL_SERVICE := apache
ifeq ($(STANDALONE),1)
COMPOSE := docker compose -f docker-compose.standalone.yml
endif

help: ## Show this help
	@echo ""
	@echo "DockerCart - Docker Compose with Traefik"
	@echo ""
	@grep -E '^[a-zA-Z-]+:.*?## .*$$$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$$$1, $$$$2}'
	@echo ""
	@echo "SSL Modes (default: HTTP without SSL):"
	@echo "  make up          HTTP mode       - http://$${DOCKERCART_DOMAIN:-dockercart.local}"
	@echo "  make ssl         HTTPS SSL       - https://$${DOCKERCART_DOMAIN:-dockercart.local} (self-signed, local testing)"
	@echo "  make letsencrypt HTTPS + LE      - Production with real domain SSL (requires SSL_DOMAIN in .env)"
	@echo ""
	@echo "Alternative:"
	@echo "  make standalone  No Traefik      - Standalone Apache + embedded port routing"
	@echo "  make standalone-letsencrypt      - Standalone + HTTPS + Let's Encrypt (no Traefik)"
	@echo "  STANDALONE=1 make letsencrypt    - Alias for standalone-letsencrypt"
	@echo ""
	@echo "Documentation:"
	@echo "  See SSL_QUICK_START.md for SSL/HTTPS quick reference"
	@echo "  See SSL_CONFIGURATION.md for detailed SSL configuration"
	@echo ""

migrate: ## Apply SQL migrations from docker/mysql/migrations (uses mariadb container)
	@echo "Applying all SQL migrations from docker/mysql/migrations/..."
	@set -e; \
	if [ -z "$(wildcard docker/mysql/migrations/*.sql)" ]; then \
		echo "No migration files found in docker/mysql/migrations/"; \
		exit 0; \
	fi; \
	for f in docker/mysql/migrations/*.sql; do \
		echo "-> Applying $$f"; \
		$(COMPOSE) exec -T mariadb mariadb -u$${MARIADB_USER:-dockercart} -p$${MARIADB_PASSWORD:-dockercart_password} $${MARIADB_DATABASE:-dockercart} < "$$f" || { echo "Failed applying $$f"; exit 1; }; \
	done; \
	echo "Migrations applied."

up: ## Start in Traefik mode, HTTP by default (use make ssl or make letsencrypt for HTTPS)
	@./start.sh

standalone: ## Start without Traefik, store on port ${DOCKERCART_HTTP_PORT:-80}
	@docker compose -f docker-compose.standalone.yml up -d --build
	@echo ""
	@echo "Store: $${DOCKERCART_URL:-http://$${DOCKERCART_DOMAIN:-dockercart.local}}"
	@echo "Admin: $${DOCKERCART_URL:-http://$${DOCKERCART_DOMAIN:-dockercart.local}}/admin"

standalone-letsencrypt: ## Start standalone mode + Let's Encrypt SSL (no Traefik)
	@if [ ! -f .env ]; then \
		echo "Creating .env"; \
		cp .env.example .env; \
	fi
	@set -e; \
	set -o allexport; . ./.env; set +o allexport; \
	if [ -z "$${SSL_DOMAIN:-}" ] || [ "$${SSL_DOMAIN}" = "example.com" ]; then \
		echo "❌ SSL_DOMAIN is not configured in .env"; \
		echo "   Set SSL_DOMAIN=your-real-domain.tld"; \
		exit 1; \
	fi; \
	if [ -z "$${SSL_EMAIL:-}" ] || [ "$${SSL_EMAIL}" = "admin@example.com" ]; then \
		echo "❌ SSL_EMAIL is not configured in .env"; \
		echo "   Set SSL_EMAIL=admin@your-domain.tld"; \
		exit 1; \
	fi; \
	mkdir -p docker/letsencrypt/live/dockercart docker/letsencrypt/www; \
	if [ ! -s docker/letsencrypt/live/dockercart/fullchain.pem ] || [ ! -s docker/letsencrypt/live/dockercart/privkey.pem ]; then \
		echo "Generating temporary self-signed certificate for first boot..."; \
		openssl req -x509 -nodes -days 1 -newkey rsa:2048 \
			-keyout docker/letsencrypt/live/dockercart/privkey.pem \
			-out docker/letsencrypt/live/dockercart/fullchain.pem \
			-subj "/CN=$${SSL_DOMAIN}" >/dev/null 2>&1 || true; \
	fi; \
	docker compose -f docker-compose.standalone.yml -f docker-compose.standalone.letsencrypt.yml up -d --build; \
	echo "Requesting/renewing Let's Encrypt certificate for $${SSL_DOMAIN}..."; \
	docker compose -f docker-compose.standalone.yml -f docker-compose.standalone.letsencrypt.yml run --rm certbot certonly \
		--webroot -w /var/www/certbot \
		--email "$${SSL_EMAIL}" \
		--agree-tos \
		--no-eff-email \
		--non-interactive \
		--keep-until-expiring \
		--cert-name dockercart \
		-d "$${SSL_DOMAIN}"; \
	docker compose -f docker-compose.standalone.yml -f docker-compose.standalone.letsencrypt.yml exec -T nginx nginx -s reload; \
	echo ""; \
	echo "Store: https://$${SSL_DOMAIN}"; \
	echo "Admin: https://$${SSL_DOMAIN}/admin"; \
	echo "HTTP challenge endpoint: http://$${SSL_DOMAIN}/.well-known/acme-challenge/"; \
	echo "Auto-renewal: certbot service renews every 12h"

ssl: ## Start with self-signed SSL certificate
	@./start.sh --ssl

letsencrypt: ## Production + Let's Encrypt SSL on a real domain
	@if [ "$(STANDALONE)" = "1" ]; then \
		$(MAKE) standalone-letsencrypt; \
	else \
		./start.sh --letsencrypt; \
	fi

letsencrypt-ftp: ## Start Let's Encrypt mode and enable FTP profile (images only)
	@./start.sh --letsencrypt
	@docker compose -f docker-compose.yml -f docker-compose.letsencrypt.yml --profile ftp up -d ftp
	@echo ""
	@echo "Let's Encrypt + FTP enabled"
	@echo "FTP port: $${FTP_PORT:-21} (passive: $${FTP_PASV_MIN_PORT:-21100}-$${FTP_PASV_MAX_PORT:-21110})"

ftp: ## Start stack with optional FTP server (access only to ./upload/image)
	@docker compose --profile ftp up -d ftp
	@echo ""
	@echo "FTP enabled on port $${FTP_PORT:-21} (passive: $${FTP_PASV_MIN_PORT:-21100}-$${FTP_PASV_MAX_PORT:-21110})"
	@echo "User: $${FTP_USER:-images}"

down: ## Stop containers
	@$(COMPOSE) down || true

restart: ## Restart containers
	@$(COMPOSE) restart

logs: ## Show last 100 log lines
	@$(COMPOSE) logs --tail=100

logs-follow: ## Follow logs in real time
	@$(COMPOSE) logs -f

shell: ## Open bash shell in the app container
	@$(COMPOSE) exec $(SHELL_SERVICE) bash

mariadb: ## Open MariaDB CLI
	@$(COMPOSE) exec mariadb mariadb -u$${MARIADB_USER:-dockercart} -p$${MARIADB_PASSWORD:-dockercart_password} $${MARIADB_DATABASE:-dockercart}

backup: ## Dump database to ./backups/
	@mkdir -p backups
	@$(COMPOSE) exec mariadb mariadb-dump -u$${MARIADB_USER:-dockercart} -p$${MARIADB_PASSWORD:-dockercart_password} $${MARIADB_DATABASE:-dockercart} > backups/backup_$$(date +%Y%m%d_%H%M%S).sql
	@echo "Backup created"

restore: ## Restore from the latest dump in ./backups/
	@if [ -z "$$(ls -A backups/*.sql 2>/dev/null)" ]; then \
		echo "No backups found in ./backups/"; exit 1; \
	fi
	@LATEST=$$(ls -t backups/*.sql | head -1); \
	echo "Restoring $$LATEST"; \
	$(COMPOSE) exec -T mariadb mariadb -u$${MARIADB_USER:-dockercart} -p$${MARIADB_PASSWORD:-dockercart_password} $${MARIADB_DATABASE:-dockercart} < $$LATEST
	@echo "Restored"

dump-init: ## Regenerate docker/mysql/init.sql from running MariaDB (full dump: data, routines, triggers, events)
	@mkdir -p docker/mysql
	@echo "Backing up existing docker/mysql/init.sql to docker/mysql/init.sql.bak.$$(date -u +%Y%m%dT%H%M%SZ)"
	@cp -a docker/mysql/init.sql docker/mysql/init.sql.bak.$$(date -u +%Y%m%dT%H%M%SZ) || true
	@TMP_FILE=$$(mktemp docker/mysql/init.sql.tmp.XXXXXX); \
	echo "Generating new dump (may take some time)..."; \
	if ! $(COMPOSE) exec -T mariadb sh -c 'mariadb-dump -u"$${MARIADB_USER:-dockercart}" -p"$${MARIADB_PASSWORD:-dockercart_password}" "$${MARIADB_DATABASE:-dockercart}" --single-transaction --quick --hex-blob --routines --triggers --events --default-character-set=utf8mb4' | sed -e 's/DEFINER=[^ ]*//g' > $$TMP_FILE; then \
		rm -f $$TMP_FILE; \
		echo "Dump failed"; \
		exit 1; \
	fi; \
	mv $$TMP_FILE docker/mysql/init.sql; \
	echo "Dump written to docker/mysql/init.sql — review and commit when ready."

clean: down ## DESTRUCTIVE: Stop containers and remove all volumes
	@echo "WARNING: All database data will be lost."
	@read -p "Continue? (y/N): " confirm && [ "$$confirm" = "y" ] || exit 1
	@$(COMPOSE) down -v
	@echo "Cleaned"
