# syntax=docker/dockerfile:1

# Stages:
#   development → used by docker-compose locally (PHP-FPM only, code mounted as a volume)
#   production  → single image for ECS/Kubernetes: nginx + PHP-FPM, code baked in, port 8080
#
#   docker build --target production -t laravel-layer .

############################################################################
# base: PHP + extensions, shared by every stage
############################################################################
# 8.2.35+ fixes PHP2Shell (CVE-2026-109618 / CVE-2026-109619). Never go below it.
FROM php:8.2.35-fpm-bookworm AS base

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        librabbitmq-dev \
        unzip; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" gd pdo pdo_mysql mysqli sockets; \
    pecl install redis amqp; \
    docker-php-ext-enable redis amqp; \
    php -m | grep -qi opcache || docker-php-ext-enable opcache; \
    rm -rf /tmp/pear /var/lib/apt/lists/*

# Fails the build if the PHP version is vulnerable
RUN php -r 'exit(version_compare(PHP_VERSION, "8.2.35", ">=") ? 0 : 1);' \
    || (echo "PHP $(php -r 'echo PHP_VERSION;') is vulnerable to CVE-2026-109618/109619" && exit 1)

# PHP2Shell hardening (defense in depth on top of the upgrade)
COPY docker/php/security.ini /usr/local/etc/php/conf.d/zz-security.ini

WORKDIR /var/www

############################################################################
# development: what docker-compose runs (nginx is a separate container there)
############################################################################
FROM base AS development

RUN apt-get update \
    && apt-get install -y --no-install-recommends inotify-tools \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY nginx/www.conf /usr/local/etc/php-fpm.d/www.conf

EXPOSE 9000
CMD ["php-fpm"]

############################################################################
# vendor: production dependencies only, cached while composer.lock is unchanged
############################################################################
FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

############################################################################
# production: nginx + PHP-FPM in one image
############################################################################
FROM base AS production

# zz-docker.conf would force "listen = 9000" on every interface; FPM must stay on localhost.
RUN apt-get update \
    && apt-get install -y --no-install-recommends nginx \
    && rm -rf /var/lib/apt/lists/* /etc/nginx/sites-enabled /etc/nginx/conf.d \
    && rm -f /usr/local/etc/php-fpm.d/www.conf /usr/local/etc/php-fpm.d/zz-docker.conf \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/production/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/production/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/production/nginx.conf /etc/nginx/nginx.conf
COPY --chmod=755 docker/production/entrypoint.sh /usr/local/bin/entrypoint

# Defaults sized for a 1 GB task (~60 MB per worker). Override in the task definition.
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    PHP_FPM_PM_MAX_CHILDREN=12 \
    PHP_FPM_PM_START_SERVERS=4 \
    PHP_FPM_PM_MIN_SPARE_SERVERS=2 \
    PHP_FPM_PM_MAX_SPARE_SERVERS=6 \
    PHP_FPM_PM_MAX_REQUESTS=500 \
    PHP_FPM_REQUEST_TERMINATE_TIMEOUT=60s

COPY --from=vendor /var/www/vendor ./vendor
COPY . .

# Composer is mounted only for this step, so it never ships in the final image.
# The post-autoload-dump scripts also write the package manifest to bootstrap/cache.
RUN --mount=type=bind,from=composer:2,source=/usr/bin/composer,target=/usr/local/bin/composer \
    composer dump-autoload --no-dev --optimize --no-interaction \
    && mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8080

# ECS ignores this; configure the same command in the task definition's healthCheck.
HEALTHCHECK --interval=15s --timeout=3s --start-period=20s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8080/health || exit 1

STOPSIGNAL SIGTERM
ENTRYPOINT ["entrypoint"]
CMD ["web"]
