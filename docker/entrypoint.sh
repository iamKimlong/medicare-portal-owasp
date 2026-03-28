#!/bin/bash
set -e

mysql_install_db --user=mysql --datadir=/var/lib/mysql > /dev/null 2>&1 || true

mysqld_safe --skip-grant-tables &

echo "Waiting for MariaDB..."
for i in $(seq 1 30); do
    if mysqladmin ping --silent 2>/dev/null; then
        break
    fi
    sleep 1
done

mysql -u root <<-EOSQL
    CREATE DATABASE IF NOT EXISTS medicare;
    CREATE USER IF NOT EXISTS 'root'@'localhost' IDENTIFIED BY 'awdonline';
    GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;
    FLUSH PRIVILEGES;
EOSQL

mysql -u root medicare < /var/www/html/db/schema.sql

echo "MariaDB ready. Starting Apache..."
exec apache2-foreground
