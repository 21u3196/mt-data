FROM alpine:latest

# Install MariaDB, Apache, PHP83 and required extensions
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
    php83-openssl

# Ensure mysql user and group exist
RUN id mysql || (addgroup -S -g 101 mysql && adduser -S -D -H -u 100 -h /var/lib/mysql -s /sbin/nologin -G mysql -g mysql mysql)

# Configure Apache
RUN sed -i 's#AllowOverride None#AllowOverride All#' /etc/apache2/httpd.conf && \
    sed -i 's#DirectoryIndex index.html#DirectoryIndex index.php index.html#' /etc/apache2/httpd.conf && \
    sed -i 's/#LoadModule rewrite_module/LoadModule rewrite_module/' /etc/apache2/httpd.conf

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /var/www/localhost/htdocs

COPY . /var/www/localhost/htdocs

EXPOSE 80 3306

ENTRYPOINT ["/entrypoint.sh"]
