FROM alpine:latest

# Install MariaDB, Apache, PHP83 and required extensions + ca-certificates
RUN apk update && apk add --no-cache \
    mariadb \
    mariadb-client \
    apache2 \
    php83 \
    php83-apache2 \
    php83-mysqli \
    php83-session \
    php83-json \
    php83-curl \
    php83-openssl \
    ca-certificates

# Ensure mysql user and group exist
RUN id mysql || (addgroup -S -g 101 mysql && adduser -S -D -H -u 100 -h /var/lib/mysql -s /sbin/nologin -G mysql -g mysql mysql)

# Configure Apache & PHP Logging
RUN sed -i 's#AllowOverride None#AllowOverride All#' /etc/apache2/httpd.conf && \
    sed -i 's#DirectoryIndex index.html#DirectoryIndex index.php index.html#' /etc/apache2/httpd.conf && \
    sed -i 's/#LoadModule rewrite_module/LoadModule rewrite_module/' /etc/apache2/httpd.conf && \
    sed -i 's#ErrorLog logs/error.log#ErrorLog /dev/stderr#' /etc/apache2/httpd.conf && \
    sed -i 's#CustomLog logs/access.log combined#CustomLog /dev/stdout combined#' /etc/apache2/httpd.conf

RUN sed -i 's/;error_log = php_errors.log/error_log = \/dev\/stderr/' /etc/php83/php.ini || true

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /var/www/localhost/htdocs

COPY . /var/www/localhost/htdocs

EXPOSE 80 10000 3306

ENTRYPOINT ["/entrypoint.sh"]
