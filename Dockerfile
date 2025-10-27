FROM php:8.2-apache

# Paquetes y extensiones necesarias para la app
# - pdo_mysql: conexión MySQL
# - gd + freetype/jpeg: generación de imágenes (certificados)
# - mbstring: manejo de strings multibyte
# - zip: soporte para ZIP si lo requieren librerías
# - fonts-dejavu-core: fuentes TTF presentes en Linux
RUN apt-get update && apt-get install -y \
    libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libonig-dev pkg-config \
    fonts-dejavu-core \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install pdo_mysql gd mbstring zip \
 && a2enmod rewrite \
 && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Apuntar el DocumentRoot a /public para respetar tu estructura
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!DocumentRoot /var/www/html!DocumentRoot ${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf \
 && sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

# Evitar el warning de ServerName en Apache
ENV SERVER_NAME=localhost
RUN echo "ServerName ${SERVER_NAME}" > /etc/apache2/conf-available/servername.conf \
 && a2enconf servername

WORKDIR /var/www/html
COPY . .

# Exponer el puerto estándar de Apache
EXPOSE 80

# Comando de arranque
CMD ["apache2-foreground"]