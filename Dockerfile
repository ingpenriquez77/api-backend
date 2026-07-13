# ==========================================
# ETAPA 1: Construcción y Dependencias (Composer)
# ==========================================
FROM php:8.2-alpine AS builder
RUN apk add --no-cache git unzip bash
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./

# Agregamos --no-scripts para evitar que busque a "artisan" prematuramente
RUN composer update mongodb/laravel-mongodb --ignore-platform-reqs --no-scripts \
    && composer install --no-ansi --no-dev --no-interaction --no-plugins --no-progress --no-scripts --optimize-autoloader --ignore-platform-reqs

# ==========================================
# ETAPA 2: Entorno Interactivo Final
# ==========================================
FROM php:8.2-alpine

# Instalar dependencias del sistema, bash, extensiones XML/DOM y linux-headers para compilar Mongo
RUN apk add --no-cache \
    openssl-dev \
    pcre-dev \
    bash \
    libxml2-dev \
    linux-headers \
    $PHPIZE_DEPS \
    && docker-php-ext-install dom xml

#  Forzamos la instalación de la versión 1.16.2 para solucionar el error de BSONArray
RUN pecl install mongodb-1.16.2 && docker-php-ext-enable mongodb
RUN apk del $PHPIZE_DEPS

WORKDIR /var/www/html

# Copiar proyecto y dependencias actualizadas
COPY . .
COPY --from=builder /app/vendor ./vendor

# Evitamos que falle si las carpetas de Laravel aún no existen en tu repo
RUN mkdir -p storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Cambiamos al puerto 80 que es el estándar que mapea Render
EXPOSE 80

# Ejecuta migraciones, llena la base de datos, y luego arranca el servidor
CMD sh -c "php artisan migrate --seed --force && php artisan serve --host=0.0.0.0 --port=80"

