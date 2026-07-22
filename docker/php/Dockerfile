FROM php:8.4-fpm AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        libicu-dev \
        libzip-dev \
        unzip \
        git \
    && docker-php-ext-install pdo_pgsql intl opcache zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

FROM base AS php_fpm

FROM base AS worker

# Deviendra "php bin/console messenger:consume async -vv" une fois
# symfony/messenger installé (Epic 05 — Moteur d'exécution des checks).
CMD ["php", "-v"]
