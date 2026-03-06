#!/bin/bash
set -e

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
        chown www-data:www-data "$root_config" "$admin_config" 2>/dev/null || true
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
    chown -R www-data:www-data "$TARGET_STORAGE"
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

        # Try to align owner/group for image cache with Apache runtime user.
        chown -R www-data:www-data /var/www/html/image/cache 2>/dev/null || true
        chgrp -R www-data /var/www/html/image/cache 2>/dev/null || true

        # Don't chown the entire webroot (bind-mount). Only ensure storage ownership.
        chown -R www-data:www-data /var/www/storage || true

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

# Основная логика
# Emit a small diagnostic header so logs show which entrypoint version ran.
# We print the script modification time (as embedded in the image at build time)
# and the current UTC timestamp. This helps quickly identify whether the
# running container uses the updated entrypoint after rebuilds.
script_mtime="$(stat -c '%y' "$0" 2>/dev/null || echo 'unknown')"
echo "Entrypoint: $0 (modified: ${script_mtime})"
echo "Entrypoint started at UTC: $(date -u '+%Y-%m-%d %H:%M:%S')"

echo "Starting DockerCart container..."

# Создаем конфиги приложения, если отсутствуют
ensure_app_configs

# Ждем MariaDB
wait_for_mysql

# Миграция storage из upload/system/storage в /var/www/storage
migrate_storage

# Устанавливаем права
set_permissions

echo "DockerCart is ready!"

# Запускаем Apache
exec apache2-foreground
