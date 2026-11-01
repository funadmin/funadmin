ARG PHP_IMAGE=php:8.5-cli
ARG COMPOSER_IMAGE=composer:2
FROM ${COMPOSER_IMAGE} AS composer
FROM ${PHP_IMAGE}

RUN apt-get update \
    && apt-get install -y --no-install-recommends libfreetype6-dev libjpeg62-turbo-dev libpng-dev libzip-dev libonig-dev libcurl4-openssl-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 pcntl pdo_mysql mbstring gd zip curl \
    && pecl install redis-6.3.0 \
    && docker-php-ext-enable redis

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

WORKDIR /app
CMD ["php", "start.php", "start"]
