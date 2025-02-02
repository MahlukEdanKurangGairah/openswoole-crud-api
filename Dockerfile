#Gunakan image base PHP dengan OpenSwoole
FROM openswoole/swoole:php8.3

# Install dependensi yang diperlukan
RUN docker-php-ext-install pdo_mysql

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
ENV MYSQL_HOST="localhost"
ENV MYSQL_PORT=3306
ENV MYSQL_USER="root"
ENV MYSQL_PASSWORD=""
ENV MYSQL_DATABASE="mysql"
ENV SERVER_ACCESS_IP="0.0.0.0"
ENV SERVER_ACCESS_PORT=9501
ENV NUM_PROSES=4
ENV GID=1000
ENV UID=1000
ENV COMPOSER_ALLOW_SUPERUSER=1
# Set working directory
WORKDIR /var/www/html
# Copy file aplikasi ke container
RUN mkdir /app
COPY ./run.sh /app/run.sh
COPY ./server.php /app/server.php
COPY ./composer.json /app/composer.json
RUN chmod +x /app/run.sh
# Expose port 9501
EXPOSE ${SERVER_PORT}
# Jalankan OpenSwoole HTTP server
ENTRYPOINT ["/app/run.sh"]
CMD ["bash","-c","/app/run.sh"]
