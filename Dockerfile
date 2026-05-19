FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    unzip zip curl libpng-dev libonig-dev libxml2-dev \
    libpq-dev \
    && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    mbstring

RUN a2enmod rewrite

WORKDIR /var/www/html

COPY . .

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN composer install

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80