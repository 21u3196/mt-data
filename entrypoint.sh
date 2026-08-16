#!/bin/sh
set -e

# Ensure mysql user and group exist
id mysql >/dev/null 2>&1 || (addgroup -S -g 101 mysql 2>/dev/null || true && adduser -S -D -H -u 100 -h /var/lib/mysql -s /sbin/nologin -G mysql -g mysql mysql 2>/dev/null || true)

# Setup directories
mkdir -p /run/mysqld /var/lib/mysql /var/www/localhost/htdocs
chown -R mysql:mysql /run/mysqld /var/lib/mysql 2>/dev/null || true

# Initialize database directory if first run
if [ ! -d "/var/lib/mysql/mysql" ]; then
    echo "==> Initializing MariaDB data directory..."
    mariadb-install-db --user=mysql --datadir=/var/lib/mysql --skip-test-db > /dev/null

    echo "==> Starting temporary MariaDB to configure database..."
    mariadbd --user=mysql --datadir=/var/lib/mysql --skip-networking --socket=/run/mysqld/mysqld.sock &
    MARIADB_PID=$!

    # Wait for MariaDB socket to be ready
    for i in $(seq 1 30); do
        if mariadb-admin ping --socket=/run/mysqld/mysqld.sock --silent 2>/dev/null; then
            break
        fi
        sleep 1
    done

    echo "==> Creating datavending database and setting permissions..."
    mariadb --socket=/run/mysqld/mysqld.sock -u root <<-EOSQL
        CREATE DATABASE IF NOT EXISTS \`datavending\`;
        SET PASSWORD FOR 'root'@'localhost' = PASSWORD('rootpassword');
        CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY 'rootpassword';
        SET PASSWORD FOR 'root'@'%' = PASSWORD('rootpassword');
        GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;
        GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;
        FLUSH PRIVILEGES;
EOSQL

    if [ -f "/docker-entrypoint-initdb.d/init.sql" ]; then
        echo "==> Importing /docker-entrypoint-initdb.d/init.sql..."
        mariadb --socket=/run/mysqld/mysqld.sock -u root -prootpassword datavending < /docker-entrypoint-initdb.d/init.sql || true
    elif [ -f "/var/www/localhost/htdocs/database/datavending.sql" ]; then
        echo "==> Importing database/datavending.sql..."
        mariadb --socket=/run/mysqld/mysqld.sock -u root -prootpassword datavending < /var/www/localhost/htdocs/database/datavending.sql || true
    fi

    echo "==> Shutting down temporary MariaDB..."
    mariadb-admin --socket=/run/mysqld/mysqld.sock -u root -prootpassword shutdown
    wait "$MARIADB_PID" 2>/dev/null || true
    echo "==> Database initialization complete."
fi

# Ensure permissions
chown -R mysql:mysql /run/mysqld /var/lib/mysql 2>/dev/null || true

# Start MariaDB in background
echo "==> Starting MariaDB daemon on port 3306..."
mariadbd --user=mysql --datadir=/var/lib/mysql --bind-address=0.0.0.0 &

# Start Apache in foreground
echo "==> Starting Apache Web Server on port 80..."
exec httpd -D FOREGROUND
