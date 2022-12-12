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
    && docker-php-ext-configure gd --with-webp=/usr/include/webp --with-jpeg=/usr/include --with-freetype=/usr/include/freetype2/
RUN pecl install redis && docker-php-ext-enable redis
RUN docker-php-ext-install gd
RUN docker-php-ext-install zip
RUN docker-php-ext-install opcache
RUN docker-php-ext-install intl
RUN docker-php-ext-install pdo_mysql
RUN docker-php-ext-install mysqli
RUN docker-php-ext-install bcmath
RUN docker-php-ext-install gettext
RUN docker-php-ext-install iconv mbstring
RUN docker-php-ext-install curl
RUN pecl install imagick \
    && docker-php-ext-enable imagick \

RUN mkdir -p /var/www/inc/ && echo "" > /var/www/inc/secrets.php && chmod 766 /var/www/inc/secrets.php

WORKDIR "/var/www"
CMD ["php-fpm"]
EXPOSE 9000
