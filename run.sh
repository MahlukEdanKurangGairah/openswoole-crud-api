#!/bin/bash
# export HTTP_PROXY="http://192.168.0.11:3128"
# export HTTPS_PROXY="$HTTP_PROXY"
# export http_proxy="$HTTP_PROXY"
# export http_proxy="$HTTP_PROXY"
cd /var/www/html
cp /app/composer.json /var/www/html/composer.json
cp /app/server.php /var/www/html/server.php
composer install --no-dev --optimize-autoloader
composer require openswoole/core mevdschee/php-crud-api
chown -R 1000:1000 /var/www/html
php -q server.php
