#!/bin/sh
set -e

# Setup Render Dynamic Port
RENDER_PORT="${PORT:-80}"
echo "==> Configuring Apache to listen on port ${RENDER_PORT}..."
sed -i "s/^Listen [0-9]*/Listen ${RENDER_PORT}/g" /etc/apache2/httpd.conf || true

# Setup error/access logs to stdout/stderr
mkdir -p /var/log/apache2 /run/apache2
ln -sf /dev/stdout /var/log/apache2/access.log 2>/dev/null || true
ln -sf /dev/stderr /var/log/apache2/error.log 2>/dev/null || true

# Check if external DB is configured
if [ -n "$DB_URI" ] || [ -n "$DATABASE_URL" ] || ([ -n "$DB_HOST" ] && [ "$DB_HOST" != "localhost" ] && [ "$DB_HOST" != "127.0.0.1" ]); then
    echo "==> External database configuration detected. Connecting to remote MySQL service."
else
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

    # Wait for MariaDB to be healthy
    for i in $(seq 1 20); do
        if mariadb-admin ping --silent 2>/dev/null; then
            echo "==> MariaDB is ready for connections."
            break
        fi
        sleep 1
    done
fi

# Start Apache in foreground
echo "==> Starting Apache Web Server on port ${RENDER_PORT}..."
exec httpd -D FOREGROUND
