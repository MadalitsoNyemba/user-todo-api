# syntax=docker/dockerfile:1

# =============================================================================
# base: shared runtime. Everything both targets need, nothing either doesn't.
# =============================================================================
ARG PHP_VERSION=8.3

FROM php:${PHP_VERSION}-fpm-bookworm AS base

ARG UID=1000
ARG GID=1000

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1 \
    COMPOSER_HOME=/tmp/composer

WORKDIR /var/www/html

# System packages: build headers for the PHP extensions, plus a mysql client
# for debugging from inside the container.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libicu-dev \
        default-mysql-client \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        bcmath \
        intl \
        zip \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/*

# Composer, pinned by image tag rather than piped from an installer script.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Align www-data with the host user so bind-mounted files stay writable from
# both sides. Without this, storage/ and bootstrap/cache/ throw permission
# errors that look like application bugs.
RUN groupmod --non-unique --gid "${GID}" www-data \
    && usermod --non-unique --uid "${UID}" --gid "${GID}" www-data \
    && mkdir -p /tmp/composer \
    && chown -R www-data:www-data /var/www/html /tmp/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

# =============================================================================
# dev: no application code baked in. The source is bind-mounted by Compose and
# dependencies are installed into the mount, so edits are live.
# =============================================================================
FROM base AS dev

COPY docker/php/opcache-dev.ini /usr/local/etc/php/conf.d/zz-opcache.ini

USER www-data

EXPOSE 9000
CMD ["php-fpm"]

# =============================================================================
# production: code and dependencies baked into the image, dev dependencies
# stripped, autoloader and opcache tuned. Built by CI on every PR so this stage
# is never untested.
# =============================================================================
FROM base AS production

COPY docker/php/opcache-prod.ini /usr/local/etc/php/conf.d/zz-opcache.ini

# Dependencies first, so the vendor layer is cached unless composer files change.
COPY --chown=www-data:www-data composer.json composer.lock ./

RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction \
        --no-progress

COPY --chown=www-data:www-data . .

RUN composer dump-autoload \
        --no-dev \
        --optimize \
        --classmap-authoritative \
        --no-scripts \
    && chmod -R ug+rw storage bootstrap/cache

USER www-data

EXPOSE 9000
CMD ["php-fpm"]
