FROM php:8.2-apache

# OpenSSL YA VIENE INSTALADO en php:8.2-apache
# Solo necesitamos copiar tu archivo

COPY api.php /var/www/html/

# Configurar Apache para usar tu script como índice
RUN echo '<?php include "api.php"; ?>' > /var/www/html/index.php

# O puedes redirigir todas las solicitudes a api.php
RUN a2enmod rewrite
RUN echo '<Directory /var/www/html>' > /etc/apache2/conf-available/api.conf && \
    echo '  RewriteEngine On' >> /etc/apache2/conf-available/api.conf && \
    echo '  RewriteCond %{REQUEST_FILENAME} !-f' >> /etc/apache2/conf-available/api.conf && \
    echo '  RewriteCond %{REQUEST_FILENAME} !-d' >> /etc/apache2/conf-available/api.conf && \
    echo '  RewriteRule ^(.*)$ api.php [QSA,L]' >> /etc/apache2/conf-available/api.conf && \
    echo '</Directory>' >> /etc/apache2/conf-available/api.conf

RUN a2enconf api

EXPOSE 80
