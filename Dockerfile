FROM node:22-alpine AS frontend

WORKDIR /build

COPY package.json package-lock.json* ./
RUN npm install --no-audit --no-fund --loglevel=error

COPY resources ./resources
COPY vite.config.js ./

RUN npm run build

# ---

FROM php:8.4-fpm-alpine

# System dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    zip \
    unzip \
    git \
    sqlite \
    sqlite-dev \
    python3 \
    py3-pip

# speedtest-cli (used by the in-app Tools → Speed Test)
RUN pip3 install --break-system-packages speedtest-cli

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_sqlite \
        gd \
        zip \
        bcmath \
        intl \
        opcache

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Nginx + PHP + Supervisor configs
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

WORKDIR /var/www/html

COPY . .

# Pull Vite-built Mini App assets from the frontend stage.
COPY --from=frontend /build/public/build ./public/build

RUN composer update --no-dev --prefer-dist --optimize-autoloader --no-interaction

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

RUN touch /var/www/html/database/database.sqlite \
    && chown www-data:www-data /var/www/html/database/database.sqlite

# Defer config:cache to startup (needs env vars that aren't baked at build time)
EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
