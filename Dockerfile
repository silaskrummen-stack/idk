FROM php:8.5-fpm

RUN apt-get update && apt-get install -y \
    git unzip libicu-dev libzip-dev \
    && docker-php-ext-install intl pdo pdo_mysql zip \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# PHP-Version nicht im X-Powered-By-Header verraten
RUN echo "expose_php = Off" > /usr/local/etc/php/conf.d/security.ini
