# syntax=docker/dockerfile:1.7
# Cloudron package for the APES-owned EASM instance. Shelters on ordinary
# hosting do not need this file (see README "Install").
#
# Two stages, so dependency layers stay cached: the vendor stage installs
# Composer packages from the lock file before any app source is copied, and the
# runtime stage gets only the result. EASM's front end is static files in
# public/, so there is no Node build. Base images are pinned by digest;
# Dependabot (docker ecosystem) proposes updates.

FROM composer:2.11.1@sha256:8de2184c6de224f4689e8f80ed5f5edaf0030b6b8b7839a22a2bcb6722ac87d0 AS vendor

WORKDIR /app/code

COPY composer.json composer.lock ./
# The extensions live in the runtime image, so platform checks are skipped here.
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --no-progress \
        --prefer-dist --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --optimize --no-dev --no-interaction --no-scripts --ignore-platform-reqs

FROM php:8.3.36-apache@sha256:a800bbd2c0aa322702259ed475556b2cbc311076a8d3c4bba9bfe96838745472

COPY --from=mlocati/php-extension-installer:2.13.2@sha256:4fd45396c16685266d32bf147a24ecd5e81029d3820295895bf4a90131190ac9 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions bcmath gd intl opcache pdo_mysql zip \
    && rm /usr/local/bin/install-php-extensions \
    && a2enmod rewrite remoteip headers \
    && a2dissite 000-default \
    && rm -f /etc/apache2/ports.conf && touch /etc/apache2/ports.conf

COPY cloudron/apache.conf /etc/apache2/sites-enabled/easm.conf
COPY cloudron/php.ini /usr/local/etc/php/conf.d/zz-easm.ini

WORKDIR /app/code

COPY --from=vendor /app/code /app/code

# /app/code is read-only on Cloudron: persistent state lives in /app/data. The
# package manifest is built here; the route and event caches are written to
# /app/data/cache at start. Config is not cached, because the install wizard
# and settings write .env while the app runs.
ENV APP_ROUTES_CACHE=/app/data/cache/routes-v7.php \
    APP_EVENTS_CACHE=/app/data/cache/events.php
RUN php artisan package:discover --no-interaction \
    && rm -rf storage .env public/storage \
    && ln -s /app/data/storage storage \
    && ln -s /app/data/env .env \
    && ln -s ../storage/app/public public/storage \
    && sed -i 's/\r$//' cloudron/start.sh \
    && chmod +x cloudron/start.sh

ARG EASM_VERSION=dev
ENV EASM_VERSION=${EASM_VERSION}

EXPOSE 8000

CMD ["/app/code/cloudron/start.sh"]
