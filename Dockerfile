# Base stage with common dependencies
FROM php:8.4-apache AS base

# Enable Apache modules
RUN a2enmod rewrite

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    sqlite3 \
    libsqlite3-dev \
    libicu-dev \
    libzip-dev \
    && docker-php-ext-install \
    pdo_sqlite \
    intl \
    opcache \
    zip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configure Apache to use Symfony's public directory
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html

# Ensure a default env file exists for console commands during build
RUN if [ ! -f .env ]; then cp .env.dist .env; fi

# Set required env vars for composer install (Symfony post-install scripts need these)
ENV APP_ENV=prod
ENV APP_SECRET=placeholder-secret-will-be-overridden-at-runtime

# Production stage
FROM base AS production

# Install PHP dependencies (no dev)
RUN composer install --no-dev --optimize-autoloader --no-scripts \
    && composer dump-autoload --optimize --classmap-authoritative

# Create necessary directories and set permissions
RUN mkdir -p var/cache var/log var/data && \
    chown -R www-data:www-data var/ && \
    chmod -R 775 var/

# Warm up cache and generate Doctrine metadata
RUN php bin/console cache:clear --no-warmup && \
    php bin/console cache:warmup && \
    php bin/console doctrine:schema:validate --skip-sync || true

# Configure PHP for production (can be overridden)
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Copy and configure entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Testing stage (includes dev dependencies)
FROM base AS testing

# Install PHP dependencies (with dev for PHPUnit, etc.)
RUN composer install --optimize-autoloader --no-scripts \
    && composer dump-autoload --optimize --classmap-authoritative

# Create necessary directories and set permissions
RUN mkdir -p var/cache var/log var/data && \
    chown -R www-data:www-data var/ && \
    chmod -R 775 var/

# Warm up cache
RUN php bin/console cache:clear --no-warmup && \
    php bin/console cache:warmup && \
    php bin/console doctrine:schema:validate --skip-sync || true

# Configure PHP for production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Copy and configure entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
