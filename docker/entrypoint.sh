#!/bin/bash
set -e

# Fix permissions for mounted volumes (они приходят с правами хоста)
# Combines base permissions + SGID/group setup for FTP+www-data access.
fix_permissions() {
    echo "Fixing permissions for mounted volumes..."

    # ---- Base permissions (files/dirs readable, some writable) ----
    if [ -d "/var/www/html" ]; then
        find /var/www/html -type d -exec chmod 755 {} \; 2>/dev/null || true
        find /var/www/html -type f -exec chmod 644 {} \; 2>/dev/null || true
    fi

    if [ -d "/var/www/storage" ]; then
        chmod -R 775 /var/www/storage 2>/dev/null || true
    fi

    # Writable dirs for uploads
    chmod -R 775 /var/www/html/image/catalog 2>/dev/null || true
    chmod -R 775 /var/www/html/image/cache 2>/dev/null || true

    # ---- SGID + group write for FTP (staff) + www-data shared access ----
    chgrp -R staff /var/www/html/image/ 2>/dev/null || true
    find /var/www/html/image/catalog /var/www/html/image/cache -type d -exec chmod g+ws {} \; 2>/dev/null || true
    find /var/www/html/image/catalog /var/www/html/image/cache -type f -exec chmod g+w {} \; 2>/dev/null || true

    # ---- Extended permissions (run when we are root) ----
    if [ "$(id -u)" -eq 0 ]; then
        # SGID on all webroot dirs so new files inherit group
        find /var/www/html -type d -exec chmod 2775 {} \; || true
        # Files: group write so www-data and host users can edit
        find /var/www/html -type f -exec chmod 664 {} \; || true

        # Ensure webroot group is staff so www-data can write via group permissions
        chgrp -R staff /var/www/html/ 2>/dev/null || true

        # Storage dirs: SGID + group write (www-data через staff group)
        chgrp -R staff /var/www/storage/ || true
        chmod -R 2775 /var/www/storage/ || true
        chmod -R 2777 /var/www/html/image/cache/ || true

        # Ensure modification cache is owned by www-data (refresh runs as root)
        chown -R www-data:staff /var/www/storage/modification/ || true

        # Final safety net for restrictive host FS mappings
        find /var/www/html/image/cache -type d -exec chmod 2777 {} \; || true
        find /var/www/html/image/cache -type f -exec chmod 666 {} \; || true

        # Git exclude file for extension installer (mounted from host .git/info/exclude)
        if [ -f "/var/www/git-exclude" ]; then
            chmod 666 /var/www/git-exclude 2>/dev/null || true
        else
            touch /var/www/git-exclude 2>/dev/null || true
            chmod 666 /var/www/git-exclude 2>/dev/null || true
        fi

        # Diagnostic write test
        if ! su -s /bin/sh www-data -c 'touch /var/www/html/image/cache/.perm_test && rm -f /var/www/html/image/cache/.perm_test' 2>/dev/null; then
            echo "WARNING: /var/www/html/image/cache is still not writable by www-data."
            echo "WARNING: Check host-side ownership/ACL on bind mount: ./upload/image/cache"
        fi
    else
		echo "WARNING: not running as root, skipping ownership changes."
		echo "Ensure host ownership/group for bind mounts (upload/storage) allows write by group www-data."
	fi

	echo "Permissions fixed!"
}

# Install Composer dependencies when composer.lock changes
install_composer_deps() {
	if [ ! -f /var/www/composer.lock ]; then
		return
	fi

	LOCK_HASH=$(md5sum /var/www/composer.lock | cut -d' ' -f1)
	STORED_HASH=""
	HASH_FILE="/var/www/storage/vendor/.lock_hash"

	if [ -f "$HASH_FILE" ]; then
		STORED_HASH=$(cat "$HASH_FILE")
	fi

	if [ "$LOCK_HASH" != "$STORED_HASH" ]; then
		echo "composer.lock changed — installing dependencies..."
		cd /var/www && composer install --no-dev --optimize-autoloader --no-interaction || {
			echo "ERROR: Composer install failed!"
			exit 1
		}
		echo "$LOCK_HASH" > "$HASH_FILE"
		chmod 664 "$HASH_FILE" 2>/dev/null || true
		echo "Composer dependencies installed."
	else
		echo "Composer dependencies up to date."
	fi
}

# Функция для ожидания MariaDB
wait_for_mysql() {
    echo "Waiting for MariaDB to be ready..."
    local max_attempts=30
    local attempt=0

    # Используем mysqladmin ping для проверки доступности MariaDB (без SSL)
    until MYSQL_PWD="${DB_PASSWORD:-dockercart_password}" mysqladmin ping -h"${DB_HOSTNAME:-mariadb}" -u"${DB_USERNAME:-dockercart}" --skip-ssl 2>/dev/null; do
        attempt=$((attempt + 1))
        if [ $attempt -ge $max_attempts ]; then
            echo "ERROR: MariaDB did not become available in time!"
            exit 1
        fi
        echo "MariaDB is unavailable (attempt $attempt/$max_attempts) - sleeping"
        sleep 3
    done

    # Дополнительная проверка что MariaDB действительно готова принимать запросы
    echo "MariaDB is responding, checking database readiness..."
    until MYSQL_PWD="${DB_PASSWORD:-dockercart_password}" mysql -h"${DB_HOSTNAME:-mariadb}" -u"${DB_USERNAME:-dockercart}" --skip-ssl -e "SELECT 1" >/dev/null 2>&1; do
        attempt=$((attempt + 1))
        if [ $attempt -ge $max_attempts ]; then
            echo "ERROR: MariaDB database is not ready!"
            exit 1
        fi
        echo "Database not ready yet (attempt $attempt/$max_attempts) - sleeping"
        sleep 2
    done

    echo "MariaDB is up and running!"
}

# Генерация robots.txt при каждом старте (как config.php)
ensure_robots_txt() {
    local robots_file="/var/www/html/robots.txt"

    echo "Regenerating $robots_file ..."

    local url="${DOCKERCART_URL:-http://dockercart.local}"
    url="${url%/}"

    if [ "${DOCKERCART_SSL_ENABLED:-false}" = "true" ] && [ -n "${DOCKERCART_HTTPS_URL:-}" ]; then
        url="${DOCKERCART_HTTPS_URL%/}"
    fi

    if [ "${DISALLOW_INDEXING:-NO}" = "YES" ]; then
        cat > "$robots_file" <<-EOF
	User-agent: *
	Disallow: /
	EOF
    else
        cat > "$robots_file" <<-EOF
	User-agent: *
	Allow: /catalog/view/javascript/
	Allow: /catalog/view/theme/
	Allow: /image/

	Disallow: /admin/
	Disallow: /system/
	Disallow: /storage/
	Disallow: /tool-
	Disallow: /*/tool-
	Disallow: /account-
	Disallow: /*/account-
	Disallow: /checkout-
	Disallow: /*/checkout-
	Disallow: /affiliate-
	Disallow: /*/affiliate-
	Disallow: /product-search
	Disallow: /*/product-search
	Disallow: /product-compare
	Disallow: /*/product-compare
	Disallow: /*?*sort=
	Disallow: /*?*order=
	Disallow: /*?*limit=
	Disallow: /*?*page=
	Disallow: /*?*tracking=
	Disallow: /*?*utm_

	Sitemap: ${url}/sitemap.xml
	EOF
    fi

    if [ "$(id -u)" -eq 0 ]; then
        chown www-data:staff "$robots_file" 2>/dev/null || true
        chmod 664 "$robots_file" 2>/dev/null || true
    fi

    echo "$robots_file regenerated (sitemap: ${url}/sitemap.xml)"
}

# Гарантированно создаем config.php файлы (если их нет на хосте/в bind mount)
ensure_app_configs() {
    local root_config="/var/www/html/config.php"
    local admin_config="/var/www/html/admin/config.php"

    echo "Regenerating $root_config ..."
    cat > "$root_config" <<'PHP'
<?php
// * Catalog Configuration File

$env = static function (string $key, string $default): string {
	$value = getenv($key);

	return ($value === false || $value === '') ? $default : $value;
};

$httpServer = rtrim($env('DOCKERCART_URL', 'http://dockercart.local'), '/') . '/';
$sslEnabled = filter_var($env('DOCKERCART_SSL_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN);
$httpsServer = $sslEnabled
	? rtrim($env('DOCKERCART_HTTPS_URL', $httpServer), '/') . '/'
	: $httpServer;

// HTTP
define('HTTP_SERVER', $httpServer);

// HTTPS
define('HTTPS_SERVER', $httpsServer);


// DIR
define('DIR_APPLICATION', '/var/www/html/catalog/');
define('DIR_SYSTEM', '/var/www/html/system/');
define('DIR_IMAGE', '/var/www/html/image/');
define('DIR_STORAGE', '/var/www/storage/');
define('DIR_LANGUAGE', DIR_APPLICATION . 'language/');
define('DIR_TEMPLATE', DIR_APPLICATION . 'view/theme/');
define('DIR_CONFIG', DIR_SYSTEM . 'config/');
define('DIR_CACHE', DIR_STORAGE . 'cache/');
define('DIR_DOWNLOAD', DIR_STORAGE . 'download/');
define('DIR_LOGS', DIR_STORAGE . 'logs/');
define('DIR_MODIFICATION', DIR_STORAGE . 'modification/');
define('DIR_SESSION', DIR_STORAGE . 'session/');
define('DIR_UPLOAD', DIR_STORAGE . 'upload/');

// DB
define('DB_DRIVER', 'mysqli');
define('DB_HOSTNAME', $env('DB_HOSTNAME', 'mariadb'));
define('DB_USERNAME', $env('DB_USERNAME', 'dockercart'));
define('DB_PASSWORD', $env('DB_PASSWORD', 'dockercart_password'));
define('DB_DATABASE', $env('DB_DATABASE', 'dockercart'));
define('DB_PORT', $env('DB_PORT', '3306'));
define('DB_PREFIX', $env('DB_PREFIX', 'oc_'));

// Cache
$cache_engine = $env('CACHE_ENGINE', 'redis');
if ($cache_engine === 'redis' && !class_exists('Redis', false)) {
	$cache_engine = 'file';
}
define('CACHE_ENGINE', $cache_engine);
define('REDIS_HOSTNAME', $env('REDIS_HOSTNAME', 'redis'));
define('REDIS_PORT', $env('REDIS_PORT', '6379'));
define('REDIS_PASSWORD', $env('REDIS_PASSWORD', 'dockercart_redis_pass'));
define('CACHE_PREFIX', $env('CACHE_PREFIX', 'oc_'));
define('IMAGE_MAX_DIMENSION', getenv('IMAGE_MAX_DIMENSION') ?: '2560');

// Session
define('SESSION_ENGINE', $env('SESSION_ENGINE', 'redis'));
PHP

    echo "Regenerating $admin_config ..."
    mkdir -p /var/www/html/admin
    cat > "$admin_config" <<'PHP'
<?php
// * Admin Configuration File

$env = static function (string $key, string $default): string {
	$value = getenv($key);

	return ($value === false || $value === '') ? $default : $value;
};

$catalogHttpServer = rtrim($env('DOCKERCART_URL', 'http://dockercart.local'), '/') . '/';
$sslEnabled = filter_var($env('DOCKERCART_SSL_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN);
$catalogHttpsServer = $sslEnabled
	? rtrim($env('DOCKERCART_HTTPS_URL', $catalogHttpServer), '/') . '/'
	: $catalogHttpServer;

// HTTP
define('HTTP_SERVER', $catalogHttpServer . 'admin/');
define('HTTP_CATALOG', $catalogHttpServer);

// HTTPS
define('HTTPS_SERVER', $catalogHttpsServer . 'admin/');
define('HTTPS_CATALOG', $catalogHttpsServer);

// DIR
define('DIR_APPLICATION', '/var/www/html/admin/');
define('DIR_SYSTEM', '/var/www/html/system/');
define('DIR_IMAGE', '/var/www/html/image/');
define('DIR_STORAGE', '/var/www/storage/');
define('DIR_CATALOG', '/var/www/html/catalog/');
define('DIR_LANGUAGE', DIR_APPLICATION . 'language/');
define('DIR_TEMPLATE', DIR_APPLICATION . 'view/template/');
define('DIR_CONFIG', DIR_SYSTEM . 'config/');
define('DIR_CACHE', DIR_STORAGE . 'cache/');
define('DIR_DOWNLOAD', DIR_STORAGE . 'download/');
define('DIR_LOGS', DIR_STORAGE . 'logs/');
define('DIR_MODIFICATION', DIR_STORAGE . 'modification/');
define('DIR_SESSION', DIR_STORAGE . 'session/');
define('DIR_UPLOAD', DIR_STORAGE . 'upload/');
define('GIT_EXCLUDE_FILE', '/var/www/git-exclude');

// DB
define('DB_DRIVER', 'mysqli');
define('DB_HOSTNAME', $env('DB_HOSTNAME', 'mariadb'));
define('DB_USERNAME', $env('DB_USERNAME', 'dockercart'));
define('DB_PASSWORD', $env('DB_PASSWORD', 'dockercart_password'));
define('DB_DATABASE', $env('DB_DATABASE', 'dockercart'));
define('DB_PORT', $env('DB_PORT', '3306'));
define('DB_PREFIX', $env('DB_PREFIX', 'oc_'));

// Cache
$cache_engine = $env('CACHE_ENGINE', 'redis');
if ($cache_engine === 'redis' && !class_exists('Redis', false)) {
	$cache_engine = 'file';
}
define('CACHE_ENGINE', $cache_engine);
define('REDIS_HOSTNAME', $env('REDIS_HOSTNAME', 'redis'));
define('REDIS_PORT', $env('REDIS_PORT', '6379'));
define('REDIS_PASSWORD', $env('REDIS_PASSWORD', 'dockercart_redis_pass'));
define('CACHE_PREFIX', $env('CACHE_PREFIX', 'oc_'));
define('IMAGE_MAX_DIMENSION', getenv('IMAGE_MAX_DIMENSION') ?: '2560');

// Session
define('SESSION_ENGINE', $env('SESSION_ENGINE', 'redis'));
PHP

    if [ "$(id -u)" -eq 0 ]; then
        chown www-data:www-data "$root_config" "$admin_config" 2>/dev/null || true
        chmod 640 "$root_config" "$admin_config" 2>/dev/null || true
    fi
}

# Инициализация БД (fallback — если MariaDB пропустила init скрипты из-за существующего volume)
initialize_database() {
    local db_host="${DB_HOSTNAME:-mariadb}"
    local db_user="${DB_USERNAME:-dockercart}"
    local db_pass="${DB_PASSWORD:-dockercart_password}"
    local db_name="${DB_DATABASE:-dockercart}"
    local db_prefix="${DB_PREFIX:-oc_}"
    local admin_user="${ADMIN_USERNAME:-admin}"
    local admin_pass="${ADMIN_PASSWORD:-admin123}"
    local admin_email="${ADMIN_EMAIL:-admin@example.com}"

    echo "Checking if database needs initialization..."

    # Проверяем, есть ли таблицы в БД
    local table_count
    table_count=$(MYSQL_PWD="${db_pass}" mysql -h"${db_host}" -u"${db_user}" --skip-ssl \
        -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${db_name}'" \
        2>/dev/null || echo "0")
    table_count="$(echo "${table_count}" | tr -d '[:space:]')"

    if [ "${table_count}" != "0" ] && [ "${table_count}" != "NULL" ]; then
        echo "Database already has ${table_count} tables — skipping initialization."
        return 0
    fi

    # БД пуста — инициализируем
    echo "Database is empty. Initializing from seed SQL..."

    local seed_sql="/opt/dockercart-seed/init.sql"
    if [ ! -f "${seed_sql}" ]; then
        echo "ERROR: Seed SQL not found at ${seed_sql}" >&2
        return 1
    fi

    echo "Importing seed SQL into database '${db_name}'..."
    if ! MYSQL_PWD="${db_pass}" mysql -h"${db_host}" -u"${db_user}" --skip-ssl "${db_name}" < "${seed_sql}"; then
        echo "ERROR: Failed to import seed SQL" >&2
        return 1
    fi
    echo "Seed SQL imported successfully (${table_count} tables)."

    # Bootstrap: admin user, API key, store settings
    local url="${DOCKERCART_URL:-http://dockercart.local}"
    url="${url%/}/"

    echo "Applying DockerCart bootstrap settings..."
    local admin_hash
    admin_hash=$(php -r "echo password_hash('${admin_pass}', PASSWORD_ARGON2ID);")

    MYSQL_PWD="${db_pass}" mysql -h"${db_host}" -u"${db_user}" --skip-ssl "${db_name}" <<SQL
SET NAMES utf8mb4;

DELETE FROM \`${db_prefix}user\` WHERE user_id = 1;
INSERT INTO \`${db_prefix}user\` \
  (user_id, user_group_id, username, salt, password, firstname, lastname, email, image, code, ip, status, date_added) \
VALUES \
  (1, 1, '${admin_user}', '', \
   '${admin_hash}', \
   'DockerCart', 'Admin', '${admin_email}', '', '', '', 1, NOW());

DELETE FROM \`${db_prefix}setting\` WHERE \`key\` IN ('config_email', 'config_url', 'config_ssl', 'config_encryption', 'config_api_id');
INSERT INTO \`${db_prefix}setting\` (store_id, \`code\`, \`key\`, \`value\`, serialized) VALUES
  (0, 'config', 'config_email', '${admin_email}', 0),
  (0, 'config', 'config_url', '${url}', 0),
  (0, 'config', 'config_ssl', '${url}', 0),
  (0, 'config', 'config_encryption', REPLACE(UUID(), '-', ''), 0);

DELETE FROM \`${db_prefix}api\` WHERE username = 'Default';
INSERT INTO \`${db_prefix}api\` (username, \`key\`, status, date_added, date_modified)
VALUES ('Default', REPLACE(UUID(), '-', ''), 1, NOW(), NOW());
SET @api_id = LAST_INSERT_ID();
INSERT INTO \`${db_prefix}setting\` (store_id, \`code\`, \`key\`, \`value\`, serialized)
VALUES (0, 'config', 'config_api_id', @api_id, 0);
SQL
    echo "DockerCart bootstrap finished."
}

wait_for_manticore() {
    echo "Waiting for Manticore to be ready..."
    local max_attempts=30
    local attempt=0
    local manticore_host="${MANTICORE_HOST:-manticore}"
    local manticore_port="${MANTICORE_PORT:-9306}"

    until mysql -h"${manticore_host}" -P"${manticore_port}" -e "SHOW TABLES" >/dev/null 2>&1; do
        attempt=$((attempt + 1))
        if [ $attempt -ge $max_attempts ]; then
            echo "WARNING: Manticore did not become available in time — skipping background reindex"
            return 1
        fi
        echo "Manticore is unavailable (attempt $attempt/$max_attempts) - sleeping"
        sleep 3
    done

    echo "Manticore is up and running!"
    return 0
}

initialize_manticore_index() {
    local php_script="/var/www/html/admin/cli/dockercart_search_reindex.php"

    if [ ! -f "$php_script" ]; then
        echo "WARNING: Manticore reindex script not found at $php_script — skipping"
        return
    fi

    if ! wait_for_manticore; then
        return
    fi

    echo "Starting background Manticore reindex..."
    (
        php "$php_script" 2>&1
    ) &
}

# Применяем PHP настройки из переменных окружения (если заданы)
apply_php_settings() {
    local ini_file="/usr/local/etc/php/conf.d/zzz-dockercart-env.ini"

    cat > "$ini_file" <<PHP
[PHP]
; DockerCart runtime overrides from environment variables
memory_limit = ${PHP_MEMORY_LIMIT:-256M}
max_execution_time = ${PHP_MAX_EXECUTION_TIME:-300}
upload_max_filesize = ${PHP_UPLOAD_MAX_FILESIZE:-100M}
post_max_size = ${PHP_POST_MAX_SIZE:-100M}
date.timezone = ${TZ:-UTC}
PHP

    if [ "${DOCKERCART_SSL_ENABLED:-false}" = "true" ] || [ "${DOCKERCART_FORCE_SSL:-0}" -eq 1 ]; then
        echo "session.cookie_secure = On" >> "$ini_file"
    fi

    echo "Applied PHP settings from environment (${ini_file})"
}

# Generate rclone config for S3 backups from BACKUP_S3_* env vars.
# Writes /var/www/storage/.rclone.conf (chmod 600) and exports RCLONE_CONFIG.
# No-op when BACKUP_S3_ENABLED != true or required creds are missing.
ensure_rclone_config() {
    local rc_conf="/var/www/storage/.rclone.conf"

    if [ "${BACKUP_S3_ENABLED:-false}" != "true" ]; then
        return 0
    fi

    if [ -z "${BACKUP_S3_BUCKET:-}" ] || [ -z "${BACKUP_S3_ACCESS_KEY_ID:-}" ] || [ -z "${BACKUP_S3_SECRET_ACCESS_KEY:-}" ]; then
        echo "WARNING: BACKUP_S3_ENABLED=true but BACKUP_S3_BUCKET/ACCESS_KEY_ID/SECRET_ACCESS_KEY not set — skipping rclone config."
        return 0
    fi

    echo "Generating rclone config at ${rc_conf}..."
    cat > "$rc_conf" <<EOF
[s3]
type = s3
provider = ${BACKUP_S3_PROVIDER:-Other}
endpoint = ${BACKUP_S3_ENDPOINT:-}
region = ${BACKUP_S3_REGION:-}
access_key_id = ${BACKUP_S3_ACCESS_KEY_ID}
secret_access_key = ${BACKUP_S3_SECRET_ACCESS_KEY}
no_check_certificate = ${BACKUP_S3_INSECURE:-false}
EOF
    chmod 600 "$rc_conf" 2>/dev/null || true
    if [ "$(id -u)" -eq 0 ]; then
        chown www-data:staff "$rc_conf" 2>/dev/null || true
    fi
    export RCLONE_CONFIG="$rc_conf"
    echo "rclone config generated (RCLONE_CONFIG=${rc_conf})."
}

# Основная логика
# Emit a small diagnostic header so logs show which entrypoint version ran.
# We print the script modification time (as embedded in the image at build time)
# and the current UTC timestamp. This helps quickly identify whether the
# running container uses the updated entrypoint after rebuilds.
script_mtime="$(stat -c '%y' "$0" 2>/dev/null || echo 'unknown')"
echo "Entrypoint: $0 (modified: ${script_mtime})"
echo "Entrypoint started at UTC: $(date -u '+%Y-%m-%d %H:%M:%S')"

echo "Starting DockerCart container..."

# Исправляем права на смонтированные volume'ы (первое действие!)
fix_permissions

# Устанавливаем Composer зависимости, если vendor отсутствует (первый запуск / свежий clone)
install_composer_deps

# Backup role: one-shot worker (started by host cron via
# `docker compose run --rm --no-deps backup-worker`). Runs the PHP worker
# directly, then exits. No Apache/OCMOD/Manticore.
if [ "$DOCKERCART_ROLE" = "backup" ]; then
    ensure_app_configs
    wait_for_mysql
    apply_php_settings
    mkdir -p /var/www/storage/backup
    ensure_rclone_config
    echo "Starting DockerCart backup worker..."
    exec php /var/www/html/bin/dockercart_backup_s3.php "$@"
fi

# Scheduler role: lightweight startup, no Apache/OCMOD/Manticore
if [ "$DOCKERCART_ROLE" = "scheduler" ]; then
    ensure_app_configs
    wait_for_mysql
    apply_php_settings
    mkdir -p /var/www/storage/logs/scheduler
    echo "Starting DockerCart scheduler..."
    exec php /var/www/html/bin/dockercart_scheduler.php
fi

# Создаем конфиги приложения, если отсутствуют
ensure_app_configs

# Генерируем robots.txt при каждом старте
ensure_robots_txt

# Ждем MariaDB
wait_for_mysql

# Инициализация БД (если MariaDB пропустила init из-за существующего volume)
initialize_database || echo "WARNING: Database initialization failed — continuing anyway"

apply_php_settings

# rclone config for S3 backup worker (also lets `make shell` users run the
# worker manually; no-op when BACKUP_S3_ENABLED != true).
mkdir -p /var/www/storage/backup
ensure_rclone_config

# Перестраиваем OCMOD модификации (читает XML из БД и файлов, пересоздаёт кэш)
echo "Refreshing OCMOD modifications..."
php /var/www/html/admin/cli/dockercart_modification_refresh.php || echo "WARNING: OCMOD modification refresh failed (non-fatal)"
chown -R www-data:staff /var/www/storage/modification/ || true

# Запускаем фоновую индексацию Manticore (не блокирует Apache)
initialize_manticore_index

echo "DockerCart is ready!"

# Запускаем Apache
exec apache2-foreground
