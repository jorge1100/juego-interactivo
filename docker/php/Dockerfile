FROM php:8.5-fpm

RUN apt-get update && apt-get install -y \
git \
curl \
nano \
zip \
unzip \
mariadb-client \
libzip-dev \
libicu-dev \
libpng-dev \
libxml2-dev \
libonig-dev \
libssl-dev \
libsasl2-dev

RUN docker-php-ext-install \
pdo \
pdo_mysql \
mysqli \
intl \
zip \
bcmath \
exif \
pcntl

RUN pecl install mongodb \
 && docker-php-ext-enable mongodb

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

RUN echo "expose_php=Off" > /usr/local/etc/php/conf.d/security.ini \
 && echo "display_errors=Off" >> /usr/local/etc/php/conf.d/security.ini \
 && echo "session.cookie_httponly=1" >> /usr/local/etc/php/conf.d/security.ini \
 && echo "memory_limit=512M" >> /usr/local/etc/php/conf.d/security.ini

WORKDIR /var/www/html

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
