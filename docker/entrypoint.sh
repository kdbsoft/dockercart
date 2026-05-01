#!/bin/bash
set -e

# Fix permissions for mounted volumes (они приходят с правами хоста)
fix_volume_permissions() {
    echo "Fixing permissions for mounted volumes..."

    # Переиминяем владельца смонтированных папок на www-data
    if [ -d "/var/www/html" ]; then
        chmod -R 755 /var/www/html 2>/dev/null || true
        find /var/www/html -type d -exec chmod 755 {} \; 2>/dev/null || true
        find /var/www/html -type f -exec chmod 644 {} \; 2>/dev/null || true
    fi

    if [ -d "/var/www/storage" ]; then
        chmod -R 777 /var/www/storage 2>/dev/null || true
    fi

    # Важные директории для загрузок должны быть writable
    chmod -R 775 /var/www/html/image/catalog 2>/dev/null || true
    chmod -R 775 /var/www/html/image/cache 2>/dev/null || true

    # Restore staff group on image/ so FTP (uid=14:staff) and www-data (member of staff)
    # can both read/write.
    chgrp -R staff /var/www/html/image/ 2>/dev/null || true
    find /var/www/html/image/catalog /var/www/html/image/cache -type d -exec chmod g+ws {} \; 2>/dev/null || true
    find /var/www/html/image/catalog /var/www/html/image/cache -type f -exec chmod g+w {} \; 2>/dev/null || true

    echo "Permissions fixed!"
}

# Функция для ожидания MariaDB
wait_for_mysql() {
    echo "Waiting for MariaDB to be ready..."
    local max_attempts=30
    local attempt=0

    # Используем mysqladmin ping для проверки доступности MariaDB (без SSL)
    until mysqladmin ping -h"${DB_HOSTNAME:-mariadb}" -u"${DB_USERNAME:-dockercart}" -p"${DB_PASSWORD:-dockercart_password}" --skip-ssl 2>/dev/null; do
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
    until mysql -h"${DB_HOSTNAME:-mariadb}" -u"${DB_USERNAME:-dockercart}" -p"${DB_PASSWORD:-dockercart_password}" --skip-ssl -e "SELECT 1" >/dev/null 2>&1; do
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

# Генерация robots.txt при первом старте (если файл отсутствует в bind mount)
ensure_robots_txt() {
    local robots_file="/var/www/html/robots.txt"

    if [ -f "$robots_file" ]; then
        echo "robots.txt already exists, skipping generation"
        return
    fi

    echo "Generating restrictive robots.txt at first start (Disallow: /)..."
    cat > "$robots_file" <<EOF
# DockerCart first-start safe default.
# Keep site closed for indexing until you review SEO settings.
# To open crawling, replace this file with robots-dist.txt and set your real domain in Sitemap.
User-agent: *
Disallow: /
EOF

    if [ "$(id -u)" -eq 0 ]; then
        chmod 664 "$robots_file" 2>/dev/null || true
    fi
}

# Гарантированно создаем config.php файлы (если их нет на хосте/в bind mount)
ensure_app_configs() {
    local root_config="/var/www/html/config.php"
    local admin_config="/var/www/html/admin/config.php"

    if [ ! -f "$root_config" ]; then
        echo "Creating missing $root_config ..."
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
define('CACHE_HOSTNAME', $env('CACHE_HOSTNAME', 'memcached'));
define('CACHE_PORT', $env('CACHE_PORT', '11211'));
define('CACHE_PREFIX', $env('CACHE_PREFIX', 'oc_'));
PHP
    fi

    if [ ! -f "$admin_config" ]; then
        echo "Creating missing $admin_config ..."
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

// DB
define('DB_DRIVER', 'mysqli');
define('DB_HOSTNAME', $env('DB_HOSTNAME', 'mariadb'));
define('DB_USERNAME', $env('DB_USERNAME', 'dockercart'));
define('DB_PASSWORD', $env('DB_PASSWORD', 'dockercart_password'));
define('DB_DATABASE', $env('DB_DATABASE', 'dockercart'));
define('DB_PORT', $env('DB_PORT', '3306'));
define('DB_PREFIX', $env('DB_PREFIX', 'oc_'));

// Cache
define('CACHE_HOSTNAME', $env('CACHE_HOSTNAME', 'memcached'));
define('CACHE_PORT', $env('CACHE_PORT', '11211'));
define('CACHE_PREFIX', $env('CACHE_PREFIX', 'oc_'));

// OpenCart API
define('OPENCART_SERVER', 'https://www.opencart.com/');
PHP
    fi

    if [ "$(id -u)" -eq 0 ]; then
        chmod 664 "$root_config" "$admin_config" 2>/dev/null || true
    fi
}

# Миграция storage из upload/system/storage в /var/www/storage
migrate_storage() {
    SOURCE_STORAGE="/var/www/html/system/storage"
    TARGET_STORAGE="/var/www/storage"

    if [ -d "$SOURCE_STORAGE" ]; then
        echo "Migrating storage from $SOURCE_STORAGE to $TARGET_STORAGE..."

        # Создаем целевую директорию если её нет
        mkdir -p "$TARGET_STORAGE"

        # Копируем содержимое, но НЕ перезаписываем существующие файлы
        # Это позволяет сохранить логи и кэш из предыдущих запусков
        if [ -d "$SOURCE_STORAGE/vendor" ] && [ ! -d "$TARGET_STORAGE/vendor" ]; then
            echo "  → Copying vendor..."
            cp -a "$SOURCE_STORAGE/vendor" "$TARGET_STORAGE/"
        fi

        if [ -d "$SOURCE_STORAGE/modification" ] && [ ! -d "$TARGET_STORAGE/modification" ]; then
            echo "  → Copying modification..."
            cp -a "$SOURCE_STORAGE/modification" "$TARGET_STORAGE/"
        fi

        # Создаем необходимые поддиректории если они не существуют
        for dir in cache logs download session upload; do
            if [ ! -d "$TARGET_STORAGE/$dir" ]; then
                echo "  → Creating $dir directory..."
                mkdir -p "$TARGET_STORAGE/$dir"
            fi
        done

        # Убеждаемся что старая директория storage в upload скрыта от web
        # (это не критично так как DIR_STORAGE теперь указывает на /var/www/storage/)
        echo "  → Storage migration complete"
    fi

    # Устанавливаем правильные права на все директории storage
    chmod -R 755 "$TARGET_STORAGE"
    chmod -R 777 "$TARGET_STORAGE/cache"
    chmod -R 777 "$TARGET_STORAGE/logs"
    chmod -R 777 "$TARGET_STORAGE/session"
    chmod -R 777 "$TARGET_STORAGE/upload"
    chmod -R 777 "$TARGET_STORAGE/download"
}

# Установка прав доступа
set_permissions() {
    echo "Setting permissions..."
    # Only perform ownership changes when running as root. For bind-mounted
    # webroot we avoid force-changing owner (host should control ownership).
    if [ "$(id -u)" -eq 0 ]; then
        # Ensure image cache base directory exists for first-run thumbnail generation.
        mkdir -p /var/www/html/image/cache || true

        # Directories in webroot: set SGID so new files inherit group, allow group write
        find /var/www/html -type d -exec chmod 2775 {} \; || true

        # Files: give group write (664) so www-data and users in group can edit
        find /var/www/html -type f -exec chmod 664 {} \; || true

        # Writable storage dirs
        chmod -R 2775 /var/www/storage/ || true
        chmod -R 775 /var/www/html/image/ || true

        # Cache path must always be writable for thumbnail/webp generation.
        chmod -R 2777 /var/www/html/image/cache/ || true

        # Final safety net for restrictive host FS mappings.
        find /var/www/html/image/cache -type d -exec chmod 2777 {} \; || true
        find /var/www/html/image/cache -type f -exec chmod 666 {} \; || true

        # Diagnostic write test helps identify host-side ACL/ownership issues quickly.
        if ! su -s /bin/sh www-data -c 'touch /var/www/html/image/cache/.perm_test && rm -f /var/www/html/image/cache/.perm_test' 2>/dev/null; then
            echo "WARNING: /var/www/html/image/cache is still not writable by www-data."
            echo "WARNING: Check host-side ownership/ACL on bind mount: ./upload/image/cache"
        fi
    else
        echo "WARNING: not running as root, skipping ownership changes."
        echo "Ensure host ownership/group for bind mounts (upload/storage) allows write by group www-data."
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
    table_count=$(mysql -h"${db_host}" -u"${db_user}" -p"${db_pass}" --skip-ssl \
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
    if ! mysql -h"${db_host}" -u"${db_user}" -p"${db_pass}" --skip-ssl "${db_name}" < "${seed_sql}"; then
        echo "ERROR: Failed to import seed SQL" >&2
        return 1
    fi
    echo "Seed SQL imported successfully (${table_count} tables)."

    # Bootstrap: admin user, API key, store settings
    local url="${DOCKERCART_URL:-http://dockercart.local}"
    url="${url%/}/"

    echo "Applying DockerCart bootstrap settings..."
    mysql -h"${db_host}" -u"${db_user}" -p"${db_pass}" --skip-ssl "${db_name}" <<SQL
SET NAMES utf8mb4;

DELETE FROM \`${db_prefix}user\` WHERE user_id = 1;
INSERT INTO \`${db_prefix}user\` \
  (user_id, user_group_id, username, salt, password, firstname, lastname, email, image, code, ip, status, date_added) \
VALUES \
  (1, 1, '${admin_user}', 'dockercart', \
   SHA1(CONCAT('dockercart', SHA1(CONCAT('dockercart', SHA1('${admin_pass}'))))), \
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
fix_volume_permissions

# Создаем конфиги приложения, если отсутствуют
ensure_app_configs

# Генерируем robots.txt, если отсутствует
ensure_robots_txt

# Ждем MariaDB
wait_for_mysql

# Инициализация БД (если MariaDB пропустила init из-за существующего volume)
initialize_database || echo "WARNING: Database initialization failed — continuing anyway"

# Миграция storage из upload/system/storage в /var/www/storage
migrate_storage

# Устанавливаем права
set_permissions

echo "DockerCart is ready!"

# Запускаем Apache
exec apache2-foreground
