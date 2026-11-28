FROM php:8.5.12-apache

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libwebp-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libmcrypt-dev \
    libicu-dev \
    libcurl4-openssl-dev \
    libsasl2-dev \
    zlib1g-dev \
    unzip \
    cron \
    default-mysql-client \
    logrotate \
    rclone \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mysqli \
        gd \
        zip \
        intl \
        bcmath \
        xml \
        mbstring \
        curl \
        pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis

# Add www-data to the staff group (GID=50) so it can write to FTP-owned image dirs
RUN groupadd -f -g 50 staff && usermod -aG staff www-data

RUN a2enmod rewrite headers ssl deflate expires

WORKDIR /var/www/html

# --chown at copy time instead of a separate `RUN chown -R / chmod -R`:
# in overlayfs that pass copies up every file into a second full-size layer
# (the webroot is ~6 GB with product images). Modes come from the build
# context as-is (644/664 files, 755/775 dirs); docker/entrypoint.sh then
# normalises them idempotently at container start, and every compose variant
# bind-mounts ./upload + ./storage over these paths anyway.
COPY --chown=www-data:www-data upload/ /var/www/html/

RUN mkdir -p /var/www/storage/cache \
    /var/www/storage/logs \
    /var/www/storage/download \
    /var/www/storage/upload \
    /var/www/storage/modification \
    /var/www/storage/session \
    /var/www/html/image/cache \
    /var/www/html/image/catalog \
    && chown -R www-data:www-data /var/www/storage \
    && chown www-data:www-data /var/www/html/image/cache /var/www/html/image/catalog

COPY docker/php.prod.ini /usr/local/etc/php/conf.d/dockercart.ini

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

COPY docker/logrotate/dockercart /etc/logrotate.d/dockercart

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Shared store-URL resolution (sourced by the entrypoint, also sourced by
# start.sh / health-check.sh on the host from the repo copy of the same file).
COPY docker/store-url.sh /usr/local/bin/store-url.sh

EXPOSE 80 443

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
