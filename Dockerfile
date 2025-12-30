FROM php:8.4-fpm-alpine

# Build argument for environment (dev or prod)
ARG APP_ENV=prod

# Install system dependencies
RUN apk add --no-cache \
    git \
    unzip \
    libsodium-dev \
    icu-dev \
    sqlite-dev \
    nginx \
    supervisor

# Install PHP extensions
RUN docker-php-ext-install sodium intl pdo_sqlite

# Configure PHP-FPM to listen on TCP instead of socket
RUN sed -i 's/listen = \/run\/php\/php8.4-fpm.sock/listen = 127.0.0.1:9000/' /usr/local/etc/php-fpm.d/www.conf || \
    echo "listen = 127.0.0.1:9000" >> /usr/local/etc/php-fpm.d/www.conf

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files first for better layer caching
COPY composer.json composer.lock symfony.lock ./

# Install dependencies (without scripts, they need app files)
# In dev mode: include dev dependencies, in prod: exclude them
RUN if [ "$APP_ENV" = "dev" ]; then \
        composer install --optimize-autoloader --no-scripts --no-interaction; \
    else \
        composer install --no-dev --optimize-autoloader --no-scripts --no-interaction; \
    fi

# Copy application files
COPY . .

# Run post-install scripts now that app files are present
RUN APP_ENV=$APP_ENV composer run-script post-install-cmd --no-interaction

# Copy nginx and supervisor configurations
COPY docker/nginx/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisor/supervisord.conf /etc/supervisord.conf

# Create required directories and set permissions
RUN mkdir -p var/cache var/log var /var/log/supervisor /run/nginx && \
    chown -R www-data:www-data /app /var/log/nginx /run/nginx && \
    chmod -R 775 var/

# Set permissions
RUN chown -R www-data:www-data var/

# Expose port
EXPOSE 8080

# Set APP_ENV as environment variable for runtime
ENV APP_ENV=${APP_ENV}

# Start: run migrations, clear cache, then start supervisor (which manages nginx + php-fpm)
CMD php bin/console doctrine:migrations:migrate --no-interaction && \
    php bin/console cache:clear && \
    chown -R www-data:www-data /app/var && \
    /usr/bin/supervisord -c /etc/supervisord.conf