ARG PHP_IMAGE=php:8.5-cli
ARG COMPOSER_IMAGE=composer:2
ARG PG16_IMAGE=postgres:16
ARG PG18_IMAGE=postgres:18
FROM ${PG18_IMAGE} AS pg18
RUN mkdir /pg-client-libs && cp -L /usr/lib/*-linux-gnu/libpq.so.5 /pg-client-libs/libpq.so.5
RUN mkdir /pg-client-libs && cp -L /usr/lib/*-linux-gnu/libpq.so.5 /pg-client-libs/libpq.so.5
FROM ${PG16_IMAGE} AS pg16
FROM ${COMPOSER_IMAGE} AS composer
FROM ${PHP_IMAGE}

RUN apt-get update \
    && apt-get install -y --no-install-recommends libfreetype6-dev libjpeg62-turbo-dev libpng-dev libzip-dev libonig-dev libcurl4-openssl-dev libpq-dev postgresql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 pcntl pdo_mysql pdo_pgsql mbstring gd zip curl \
    && pecl install redis-6.3.0 \
    && docker-php-ext-enable redis

COPY --from=pg18 /usr/lib/postgresql/18/bin/ /opt/postgresql/18/bin/
COPY --from=pg18 /pg-client-libs/ /opt/postgresql/18/lib/
COPY --from=pg16 /usr/lib/postgresql/16/bin/ /opt/postgresql/16/bin/
COPY --from=composer /usr/bin/composer /usr/local/bin/composer

WORKDIR /app
CMD ["php", "start.php", "start"]
