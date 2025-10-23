# syntax=docker/dockerfile:1
FROM php:8.3-apache

# System deps and PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    git unzip \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" gd zip pdo_mysql \
 && a2enmod rewrite

# Set Apache DocumentRoot to /public and allow .htaccess
RUN sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
 && printf "\n<Directory /var/www/html/public>\n    AllowOverride All\n</Directory>\n" >> /etc/apache2/apache2.conf

# Suppress AH00558 by defining a global ServerName
RUN echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
 && a2enconf servername

# Copy project
COPY . /var/www/html
WORKDIR /var/www/html

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install PHP dependencies (tolerate if vendor is already present)
RUN composer install --no-interaction --prefer-dist --no-progress || true

# Expose port
EXPOSE 80