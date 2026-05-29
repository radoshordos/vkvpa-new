FROM php:8.5-apache

RUN apt-get update

RUN apt-get install -y --no-install-recommends \
    git \
    unzip \
    libzip-dev \
    libicu-dev \
    zlib1g-dev

RUN docker-php-ext-install pdo_mysql zip intl

RUN a2enmod rewrite

RUN rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
