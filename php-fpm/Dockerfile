FROM composer AS composer
FROM php:8.1-fpm-bullseye
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
        imagemagick \
        libmagickwand-dev \
    && docker-php-ext-configure gd --with-webp=/usr/include/webp --with-jpeg=/usr/include --with-freetype=/usr/include/freetype2/ \
    && pecl install redis && pecl install imagick && pecl install -o -f igbinary \
    && docker-php-ext-install gd zip opcache intl pdo_mysql mysqli bcmath gettext iconv mbstring curl \
    && docker-php-ext-enable igbinary redis imagick

RUN mkdir -p /var/www/inc/ && echo "" > /var/www/inc/secrets.php && chmod 766 /var/www/inc/secrets.php

WORKDIR "/var/www"
CMD ["php-fpm"]
EXPOSE 9000
