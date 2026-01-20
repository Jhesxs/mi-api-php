FROM php:8.2-apache

# Habilitar extensiones necesarias
RUN docker-php-ext-install openssl && \
    docker-php-ext-enable openssl

# Copiar tu archivo PHP
COPY api.php /var/www/html/

# Configurar Apache para redirigir todo a tu API
RUN echo '<?php require_once "api.php"; ?>' > /var/www/html/index.php

# Exponer puerto
EXPOSE 80
