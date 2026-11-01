ARG PHP_IMAGE=php:8.5-cli
FROM ${PHP_IMAGE}

RUN docker-php-ext-install pcntl pdo_mysql

WORKDIR /app
CMD ["php", "start.php", "start"]
