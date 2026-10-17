# Imagen de desarrollo para osTicket 1.18 (PHP 8.4 + Apache)
FROM php:8.4-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg-dev libfreetype6-dev libicu-dev libzip-dev \
        libxml2-dev msmtp unzip \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" mysqli gd intl zip gettext opcache \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

# mod_rewrite y .htaccess (usados por api/ y pages/)
RUN a2enmod rewrite \
    && sed -ri 's#AllowOverride None#AllowOverride All#g' /etc/apache2/apache2.conf

# Sin APCu en desarrollo: osTicket guarda en APCu la definición de los modelos
# y solo la renueva al desplegar, así que ignoraría los cambios de código
# durante 30 minutos. En producción sí se recomienda APCu.
COPY php.ini /usr/local/etc/php/conf.d/zz-osticket-dev.ini

WORKDIR /var/www/html
