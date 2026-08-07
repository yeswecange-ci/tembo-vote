# ============================================================
# Tembo Selfie & Vote — image de production (Coolify)
# 3 étapes : dépendances PHP → assets Vite → image finale
# ============================================================

# ---------- 1. Dépendances PHP ----------
FROM composer:2 AS deps
WORKDIR /app
COPY composer.json composer.lock ./
# --ignore-platform-reqs : les extensions (gd, zip…) vivent dans l'image finale
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs

# ---------- 2. Assets front (Vite + Tailwind 4) ----------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
# Tailwind scanne aussi les vues de pagination du framework (@source dans app.css)
COPY --from=deps /app/vendor ./vendor
RUN npm run build

# ---------- 3. Image finale : nginx + PHP-FPM prêts pour Laravel ----------
FROM serversideup/php:8.4-fpm-nginx

USER root
# gd/exif/zip : pipeline photo · pdo_mysql : MariaDB · mariadb-client : tembo:backup
RUN install-php-extensions gd exif zip pdo_mysql && \
    apt-get update && \
    apt-get install -y --no-install-recommends mariadb-client && \
    apt-get clean && rm -rf /var/lib/apt/lists/*
USER www-data

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --from=deps --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev

# 20 workers FPM : dimensionné par le test de charge (300 invités, polling 3 s).
# AUTORUN : migrations exécutées automatiquement à chaque démarrage.
# Pas de storage:link : le disque photos est volontairement privé.
ENV PHP_OPCACHE_ENABLE=1 \
    PHP_FPM_PM_MAX_CHILDREN=20 \
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_STORAGE_LINK=false
