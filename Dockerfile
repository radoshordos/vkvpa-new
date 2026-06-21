FROM php:8.5-apache

RUN apt-get update && apt-get install -y \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libzip-dev \
        libicu-dev \
        libsqlite3-dev \
        libmagickwand-dev \
        libheif1 \
        unzip \
        git \
        zip \
        apache2-utils \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo_mysql pdo_sqlite zip intl \
    # Imagick kvůli zpracování fotek v diskuzi vč. HEIC z mobilů (libheif).
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY docker/php/performance.ini /usr/local/etc/php/conf.d/performance.ini

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Vstupní skript generuje .htpasswd pro Adminer z env proměnných při startu.
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
