FROM php:8.5-apache

RUN apt-get update && apt-get install -y \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libzip-dev \
        libicu-dev \
        libsqlite3-dev \
        unzip \
        git \
        zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo_mysql pdo_sqlite zip intl \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

WORKDIR /var/www/html
