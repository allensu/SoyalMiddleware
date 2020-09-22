FROM php:7.3-fpm
WORKDIR /code
COPY . .
RUN apt update && apt install -y \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libpq-dev \
        git \
        wget \
        zip \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && docker-php-ext-configure gd \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-enable gd \
    && docker-php-ext-configure pgsql  \
    && docker-php-ext-install -j$(nproc) pgsql \
    && docker-php-ext-enable pgsql \
    && docker-php-ext-configure pdo_pgsql  \
    && docker-php-ext-install -j$(nproc) pdo_pgsql \
    && docker-php-ext-enable pdo_pgsql \
    && docker-php-ext-configure sockets  \
    && docker-php-ext-install -j$(nproc) sockets \
    && docker-php-ext-enable sockets \
    && docker-php-ext-configure pcntl  \
    && docker-php-ext-install -j$(nproc) pcntl \
    && docker-php-ext-enable pcntl \
    && wget https://raw.githubusercontent.com/composer/getcomposer.org/76a7060ccb93902cd7576b67264ad91c8a2700e2/web/installer -O - -q | php -- \
    && php composer.phar install \
    && mkdir -p storage/api-docs \
                storage/debugbar \
                storage/framework \
                storage/framework/cache \
                storage/framework/sessions \
                storage/framework/views \
                storage/logs
