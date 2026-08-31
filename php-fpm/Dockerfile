ARG PHP_VERSION=8.1

FROM composer:2 AS composer

FROM php:${PHP_VERSION}-fpm-bullseye AS runtime

COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN apt-get update && apt-get install -y \
        zlib1g-dev libicu-dev g++ \
        libjpeg62-turbo-dev \
        libzip-dev \
        libpng-dev \
        libwebp-dev \
        libfreetype6-dev \
        libxml2-dev \
        git \
        zip \
        ffmpeg \
        libonig-dev \
        unzip \
        libcurl4-openssl-dev \
        libmagickwand-dev \
        gifsicle \
        graphicsmagick \
        gettext \
        imagemagick \
        locales locales-all \
    && docker-php-ext-configure gd --with-webp=/usr/include/webp --with-jpeg=/usr/include --with-freetype=/usr/include/freetype2/ \
    && pecl install redis imagick igbinary \
    && docker-php-ext-install exif gd zip opcache intl pdo_mysql mysqli bcmath gettext iconv mbstring curl \
    && docker-php-ext-enable igbinary redis imagick \
    && rm -rf /var/lib/apt/lists/*

RUN mkdir -p /var/www/inc/ \
    && printf '' > /var/www/inc/secrets.php \
    && chmod 766 /var/www/inc/secrets.php

WORKDIR /var/www
CMD ["php-fpm"]
EXPOSE 9000

FROM runtime AS test

RUN apt-get update && apt-get install -y libssh2-1-dev \
    && docker-php-ext-install pcntl \
    && pecl install ssh2 xdebug \
    && docker-php-ext-enable ssh2 xdebug \
    && rm -rf /var/lib/apt/lists/*

COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache composer install \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --no-scripts \
        --no-autoloader

COPY . .
RUN composer dump-autoload --no-interaction --no-scripts

FROM runtime AS production
