# Cloudron package for the APES-owned EASM instance. Shelters on ordinary
# hosting do not need this file (see README "Install").
FROM php:8.3-apache

ARG EASM_VERSION=dev
ENV EASM_VERSION=${EASM_VERSION}

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN install-php-extensions bcmath gd intl opcache pdo_mysql zip \
    && a2enmod rewrite remoteip headers \
    && a2dissite 000-default \
    && rm -f /etc/apache2/ports.conf && touch /etc/apache2/ports.conf

WORKDIR /app/code

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --no-progress --prefer-dist

COPY . .

# /app/code is read-only on Cloudron: persistent state lives in /app/data.
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && rm -rf storage .env public/storage \
    && ln -s /app/data/storage storage \
    && ln -s /app/data/env .env \
    && ln -s ../storage/app/public public/storage \
    && sed -i 's/\r$//' cloudron/start.sh \
    && chmod +x cloudron/start.sh \
    && cp cloudron/apache.conf /etc/apache2/sites-enabled/easm.conf \
    && cp cloudron/php.ini /usr/local/etc/php/conf.d/zz-easm.ini \
    && rm -f /usr/local/bin/composer

EXPOSE 8000

CMD ["/app/code/cloudron/start.sh"]
