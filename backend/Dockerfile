FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y libonig-dev \
    && docker-php-ext-install mbstring \
    && rm -rf /var/lib/apt/lists/*