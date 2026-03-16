FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libcurl4-openssl-dev \
    && docker-php-ext-install curl

# НЕ устанавливаем protobuf - используем чистый PHP

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .

# Настройка переменных окружения для OpenTelemetry
ENV OTEL_EXPORTER_OTLP_ENDPOINT=http://simplest-collector:4318
ENV OTEL_EXPORTER_OTLP_PROTOCOL=http/json
ENV OTEL_SERVICE_NAME=service-a
ENV OTEL_TRACES_EXPORTER=otlp
ENV OTEL_PHP_AUTOLOAD_ENABLED=true

RUN composer install --no-interaction

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "public/index.php"]