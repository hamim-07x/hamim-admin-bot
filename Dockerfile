FROM php:8.2-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl libgmp-dev \
    && docker-php-ext-install pdo pdo_mysql mysqli gmp bcmath \
    && docker-php-ext-enable pdo_mysql mysqli gmp bcmath \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY composer.json /app/
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
COPY . /app
RUN composer dump-autoload -o

CMD ["sh", "-c", "echo Starting on port ${PORT:-8080} && exec php -S 0.0.0.0:${PORT:-8080} -t /app/public"]
