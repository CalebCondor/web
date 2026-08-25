FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" curl opcache \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers

ENV APP_BASE= \
    API_BASE=https://islandmedpr.com/notarize/api/v1

RUN cat > /usr/local/etc/php/conf.d/app.ini <<'EOF'
memory_limit = 128M
upload_max_filesize = 16M
post_max_size = 20M
max_execution_time = 30
date.timezone = UTC
session.cookie_httponly = 1
session.use_strict_mode = 1
expose_php = Off
EOF

RUN mkdir -p /var/lib/php/sessions \
    && chown -R www-data:www-data /var/lib/php/sessions

WORKDIR /var/www/html

COPY index.php ./index.php
COPY auth.php ./auth.php
COPY logout.php ./logout.php
COPY profile.php ./profile.php
COPY requests.php ./requests.php
COPY assets/ ./assets/
COPY booking/step1.php booking/step2.php booking/step3.php booking/step4.php booking/step5.php booking/step6.php ./booking/
COPY booking/_shared/config.php ./config.php
COPY booking/_shared/_head.php ./_head.php
COPY booking/_shared/_nav.php ./_nav.php

RUN cat > /etc/apache2/sites-available/000-default.conf <<'EOF'
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html

    RewriteEngine On
    RewriteRule ^/step([0-9]+)\.php$ /booking/step$1.php [L]

    <Directory /var/www/html>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
