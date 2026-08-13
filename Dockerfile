FROM php:8.2-cli-bookworm

RUN docker-php-ext-install pdo pdo_mysql mysqli \
    && docker-php-ext-enable pdo_mysql mysqli

WORKDIR /app
COPY . /app

# Railway provides $PORT — listen on all interfaces
CMD ["sh", "-c", "echo Starting on port ${PORT:-8080} && exec php -S 0.0.0.0:${PORT:-8080} -t /app/public"]
