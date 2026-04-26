FROM php:8.4-apache

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
    libmemcached-dev \
    libsasl2-dev \
    zlib1g-dev \
    unzip \
    default-mysql-client \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mysqli \
        gd \
        zip \
        opcache \
        intl \
        bcmath \
        xml \
        mbstring \
        curl \
    && pecl install memcached \
    && docker-php-ext-enable memcached

# Add www-data to the staff group (GID=50) so it can write to FTP-owned image dirs
RUN groupadd -f -g 50 staff && usermod -aG staff www-data

RUN a2enmod rewrite headers ssl deflate expires

WORKDIR /var/www/html

COPY upload/ /var/www/html/
COPY VERSION /var/www/VERSION

RUN mkdir -p /var/www/storage/cache \
    /var/www/storage/logs \
    /var/www/storage/download \
    /var/www/storage/upload \
    /var/www/storage/modification \
    /var/www/storage/session \
    /var/www/html/image/cache \
    /var/www/html/image/catalog

RUN chown -R www-data:www-data /var/www/html /var/www/storage \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/storage \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

COPY docker/php.ini /usr/local/etc/php/conf.d/dockercart.ini

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction || true

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80 443

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
