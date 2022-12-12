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
    	unzip \
    && docker-php-ext-configure gd --with-webp=/usr/include/webp --with-jpeg=/usr/include --with-freetype=/usr/include/freetype2/
RUN pecl install redis && docker-php-ext-enable redis
RUN docker-php-ext-install gd
RUN docker-php-ext-install zip
RUN docker-php-ext-install opcache
RUN docker-php-ext-install opcache
RUN docker-php-ext-install intl
RUN docker-php-ext-install pdo_mysql
RUN docker-php-ext-install mysqli
RUN docker-php-ext-install bcmath
RUN apt-get install -y \
        libonig-dev \
    && docker-php-ext-install iconv mbstring

RUN apt-get install -y \
        libcurl4-openssl-dev \
    && docker-php-ext-install curl

RUN apt-get install -y libmagickwand-dev gifsicle graphicsmagick
RUN apt-get update && apt-get install -y \
    imagemagick libmagickwand-dev --no-install-recommends \
    && pecl install imagick \
    && docker-php-ext-enable imagick
RUN touch /var/www/inc/secrets.php
WORKDIR "/var/www"
CMD ["php-fpm"]
EXPOSE 9000
