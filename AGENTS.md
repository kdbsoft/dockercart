# DockerCart — AGENTS.md
Critical facts an agent would likely miss. Skip anything obvious.
Keep your answers short and to the point. Don't create unnecessary documentation unless asked.
## General Guidelines
- **Surgical Updates:** Always prefer surgical, partial file edits using appropriate tools rather than overwriting files entirely. This preserves existing logic, reduces risk, and maintains context efficiency.
- **Skip obvious:** Keep your answers short and to the point. Don't create unnecessary documentation unless asked.
---
## Config Generation
`upload/config.php` and `upload/admin/config.php` are **generated at container start**
by `docker/entrypoint.sh` from environment variables — never edit or commit these files.
All runtime settings flow through `.env` (copy from `.env.example`). Never commit `.env`.
---
## OpenCart MVC Conventions
Strict OpenCart 3 patterns — never deviate.
| Type | Catalog path | Admin path |
|---|---|---|
| Controller | `upload/catalog/controller/{section}/{name}.php` | `upload/admin/controller/extension/module/{name}.php` |
| Model | `upload/catalog/model/{section}/{name}.php` | `upload/admin/model/extension/module/{name}.php` |
| View (Twig) | `upload/catalog/view/theme/dockercart/template/{section}/{name}.twig` | `upload/admin/view/template/extension/module/{name}.twig` |
| Language | `upload/catalog/language/en-gb/{section}/{name}.php` | `upload/admin/language/en-gb/extension/module/{name}.php` |
### JavaScript
- ES6+ syntax
- Tailwind CSS 3 + Lucide icons for all frontend components
- Vanilla JS for all interactive features
### Code style (PHP)
- **Indentation: tabs** (not spaces) — enforced by `.php-cs-fixer.php`
- `declare(strict_types=1);` at file top for new files
- Load models via `$this->load->model()` — never instantiate directly
- Load language via `$this->language->load()` before `$this->language->get()`
### Multi-language rule
- **Always update ALL language files** when adding/changing language strings. Check `upload/admin/language/*/` and `upload/catalog/language/*/` for all variants (en-gb, ru-ua, uk-ua, etc.). Never update only one language.
### Database rules
- Table prefix always `oc_` — DockerCart tables use `oc_dockercart_`
- DB driver: `mysqli` — access via `$this->db->query()`, never raw `mysqli_*` or PDO
---
## Storage Paths
Use `/var/www/storage/` (host: `./storage/`), NOT `/var/www/html/system/storage/`.
| What | Container path | Host path |
|---|---|---|
| Webroot | `/var/www/html` | `./upload` |
| Logs / Cache / Sessions | `/var/www/storage/*` | `./storage/*` |
| Images | `/var/www/html/image` | `./upload/image` |
---
## Database Migrations
- Location: `docker/mysql/migrations/`
- Naming: `YYYYMMDD_short_description.sql`
- Always idempotent: `CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`
- Charset: `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`
- Run via: `make migrate` (applies to running MariaDB container)
- Base schema: `docker/mysql/init.sql` — regenerate with `make dump-init`
---
## Frontend (DockerCart Theme)
- **Tailwind CSS 3** + **Lucide icons** (not Font Awesome) + **ES6+** vanilla JS
- Build: `npm run build:css` — compiles to `upload/catalog/view/theme/dockercart/stylesheet/tailwind.css`
- Watch: `npm run watch:css`
- Do NOT use jQuery/Bootstrap/Font Awesome in new DockerCart theme code
- Do NOT modify `upload/catalog/view/theme/default/` (OpenCart built-in)
---
## Static Analysis & Lint
```bash
# PHP syntax check (CI: runs on push to main + PRs)
find upload -type f -name "*.php" ! -path 'storage/vendor/*' -print0 | xargs -0 -P4 php -l -n
# PHPStan (level 1, config: phpstan.neon)
./storage/vendor/bin/phpstan analyze -a ./storage/vendor/autoload.php --no-progress
# PHP-CS-Fixer (tabs, config: .php-cs-fixer.php)
./storage/vendor/bin/php-cs-fixer fix --dry-run --diff
```
---
## Docker Compose Modes
| Make target | Use case |
|---|---|
| `make dev` / `make up` | Local dev with Traefik (HTTP) |
| `make dev-standalone` | No Traefik, port 80 |
| `make dev-ssl` | Traefik + self-signed HTTPS |
| `make prod` | Traefik + Let's Encrypt |
| `make prod-standalone` | No Traefik + Let's Encrypt |
| `make ftp` | Enable FTP (images only) |
Traefik is optional — standalone modes don't require it.
Container names: `dockercart_apache`, `dockercart_nginx`, `dockercart_mariadb`, `dockercart_redis`, `dockercart_memcached`, `dockercart_manticore`
---
## Commit Conventions
Conventional Commits enforced by commitlint + semantic-release.
```
feat: add product label field
fix: resolve cache invalidation on price update
feat!: breaking API change
```
Preview release: `npm run release:dry-run`
---
## What NOT To Do
- Do NOT use OpenCart web installer or create `/install` directory
- Do NOT edit `upload/config.php` or `upload/admin/config.php` — generated at startup
- Do NOT store state in `/var/www/html/system/storage/` — use `/var/www/storage/`
- Do NOT write inline SQL schema in PHP — always use a migration
- Do NOT modify `upload/system/` core files — extend via controllers/models/events
- Do NOT generate/modify `robots.txt` in code — managed by `entrypoint.sh`
- Do NOT commit `.env` files
---
## Key Commands
```bash
make migrate # Apply DB migrations
npm run build:css # Rebuild Tailwind CSS
make shell # Bash into app container
make backup / make restore
npm run release:dry-run # Preview next release
```
