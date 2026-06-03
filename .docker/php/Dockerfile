FROM php:8.3-fpm-alpine

RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        postgresql-dev \
        linux-headers \
    && apk add --no-cache \
        git \
        icu \
        libpq \
        bash \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        intl \
        opcache \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY .docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /app

CMD ["php-fpm"]
