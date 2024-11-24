FROM php:8.3-fpm

LABEL maintainer="Ikaro C. Laborda <iclaborda@msn.com>"

# Install system dependencies and PHP extensions
RUN apt update \
    && apt install -y zlib1g-dev g++ git libicu-dev zip libzip-dev zip libpng-dev libxml2-dev libcurl4-openssl-dev \
    && docker-php-ext-install intl opcache pdo pdo_mysql gd xml curl calendar exif \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && docker-php-ext-configure zip \
    && docker-php-ext-install zip

RUN pecl install redis && docker-php-ext-enable redis

# Set working directory
WORKDIR /var/www/megacases

# Ensure /tmp is writable
RUN mkdir -p /tmp && chmod 777 /tmp

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy and set permissions for the entrypoint script
COPY --chmod=755 entrypoint.sh /usr/local/bin/

ENTRYPOINT ["entrypoint.sh"]
