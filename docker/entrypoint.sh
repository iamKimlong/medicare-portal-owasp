#!/bin/bash
set -e

mysql_install_db --user=mysql --datadir=/var/lib/mysql > /dev/null 2>&1 || true

mariadbd-safe &

echo "Waiting for MariaDB..."
for i in $(seq 1 30); do
    if mysqladmin ping --silent 2>/dev/null; then
        break
    fi
    sleep 1
done

mysql -u root <<-EOSQL
    FLUSH PRIVILEGES;
    ALTER USER 'root'@'localhost' IDENTIFIED BY 'medicare_demo';
    CREATE DATABASE IF NOT EXISTS medicare;
    FLUSH PRIVILEGES;
EOSQL

mysql -u root -pmedicare_demo medicare < /var/www/html/db/schema.sql

echo "MariaDB ready. Starting Apache..."
ln -s /var/run/mysqld/mysqld.sock /tmp/mysql.sock
exec apache2-foreground
