.PHONY: help up standalone ssl letsencrypt down logs logs-follow shell mariadb backup restore clean restart

### Convenience variables
COMPOSE := docker compose
SHELL_SERVICE := apache
ifeq ($(STANDALONE),1)
COMPOSE := docker compose -f docker-compose.standalone.yml
endif

help: ## Show this help
	@echo ""
	@echo "DockerCart"
	@echo ""
	@grep -E '^[a-zA-Z-]+:.*?## .*$$$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$$$1, $$$$2}'
	@echo ""
	@echo "  make up          Traefik mode   - http://$${DOCKERCART_DOMAIN:-dockercart.local}"
	@echo "  make standalone  Standalone     - http://$${DOCKERCART_DOMAIN:-dockercart.local} (port $${DOCKERCART_HTTP_PORT:-80})"
	@echo "  make ssl         Self-signed SSL - https://$${DOCKERCART_DOMAIN:-dockercart.local} (local testing)"
	@echo "  make letsencrypt Let's Encrypt  - Production with real domain SSL"
	@echo ""

up: ## Start in Traefik mode (docker-compose.yml)
	@./start.sh

standalone: ## Start without Traefik, store on port ${DOCKERCART_HTTP_PORT:-80}
	@docker compose -f docker-compose.standalone.yml up -d --build
	@echo ""
	@echo "Store: $${DOCKERCART_URL:-http://$${DOCKERCART_DOMAIN:-dockercart.local}}"
	@echo "Admin: $${DOCKERCART_URL:-http://$${DOCKERCART_DOMAIN:-dockercart.local}}/admin"

ssl: ## Start with self-signed SSL certificate
	@./start.sh --ssl

letsencrypt: ## Production + Let's Encrypt SSL on a real domain
	@./start.sh --letsencrypt

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
	@$(COMPOSE) exec mariadb mariadb -udockercart -pdockercart_password dockercart

backup: ## Dump database to ./backups/
	@mkdir -p backups
	@$(COMPOSE) exec mariadb mariadb-dump -udockercart -pdockercart_password dockercart > backups/backup_$$(date +%Y%m%d_%H%M%S).sql
	@echo "Backup created"

restore: ## Restore from the latest dump in ./backups/
	@if [ -z "$$(ls -A backups/*.sql 2>/dev/null)" ]; then \
		echo "No backups found in ./backups/"; exit 1; \
	fi
	@LATEST=$$(ls -t backups/*.sql | head -1); \
	echo "Restoring $$LATEST"; \
	$(COMPOSE) exec -T mariadb mariadb -udockercart -pdockercart_password dockercart < $$LATEST
	@echo "Restored"

clean: down ## DESTRUCTIVE: Stop containers and remove all volumes
	@echo "WARNING: All database data will be lost."
	@read -p "Continue? (y/N): " confirm && [ "$$confirm" = "y" ] || exit 1
	@$(COMPOSE) down -v
	@echo "Cleaned"
