FROM php:7.4-fpm
WORKDIR /code
COPY . .
RUN apt update && apt install -y \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        git \
        wget \
        zip \
    && wget https://raw.githubusercontent.com/composer/getcomposer.org/76a7060ccb93902cd7576b67264ad91c8a2700e2/web/installer -O - -q | php -- \
    && docker-php-ext-configure gd --with-freetype --with-jpeg  \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-enable gd \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && php composer.phar install \
    && mkdir -p storage/api-docs \
                storage/debugbar \
                storage/framework \
                storage/framework/cache \
                storage/framework/sessions \
                storage/framework/views
