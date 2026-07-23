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

# "async" exécute les sondes (ExecuteProbeMessage), "scheduler_default"
# fait tourner le Scheduler (déclenche le tick CheckDueProbesMessage
# toutes les 10s, voir ProbeSchedule). --time-limit redémarre le worker
# périodiquement (évite les fuites mémoire d'un process PHP long-lived) ;
# Docker le relance aussitôt via "restart: unless-stopped" (compose.yaml).
CMD ["php", "bin/console", "messenger:consume", "async", "scheduler_default", "--time-limit=3600", "-vv"]
