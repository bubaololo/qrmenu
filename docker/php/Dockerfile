FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache \
    libpq-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    linux-headers \
    imagemagick \
    imagemagick-dev \
    libwebp-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        intl \
        zip \
        pcntl \
        bcmath \
        opcache \
        gd \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

FROM base AS dev

RUN apk add --no-cache \
    $PHPIZE_DEPS \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del $PHPIZE_DEPS

RUN printf '%s\n' \
    'xdebug.mode=develop,debug' \
    'xdebug.start_with_request=yes' \
    > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

FROM base AS prod

COPY --chown=www-data:www-data . /var/www/html

USER www-data

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

USER root

CMD ["php-fpm", "-F"]
