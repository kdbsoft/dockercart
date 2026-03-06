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
        # Don't chown the entire webroot (bind-mount). Only ensure storage ownership.
        chown -R www-data:www-data /var/www/storage || true

        # Directories in webroot: set SGID so new files inherit group, allow group write
        find /var/www/html -type d -exec chmod 2775 {} \; || true

        # Files: give group write (664) so www-data and users in group can edit
        find /var/www/html -type f -exec chmod 664 {} \; || true

        # Writable storage dirs
        chmod -R 2775 /var/www/storage/ || true
        chmod -R 775 /var/www/html/image/ || true
    else
        echo "WARNING: not running as root, skipping ownership changes."
        echo "Ensure host ownership/group for bind mounts (upload/storage) allows write by group www-data."
    fi
}

# Основная логика
echo "Starting DockerCart container..."

# Ждем MariaDB
wait_for_mysql

# Миграция storage из upload/system/storage в /var/www/storage
migrate_storage

# Устанавливаем права
set_permissions

echo "DockerCart is ready!"

# Запускаем Apache
exec apache2-foreground
