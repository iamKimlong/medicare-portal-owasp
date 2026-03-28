# Undo Guide

Reverses everything from the setup in `README.md`. Run these steps in order.

---

## 1. Stop services

```bash
sudo systemctl stop httpd
sudo systemctl stop mariadb
```

## 2. Drop the database

```bash
sudo systemctl start mariadb
mariadb -u root -p -e "DROP DATABASE IF EXISTS medicare;"
sudo systemctl stop mariadb
```

## 3. Restore Apache config

Replace your modified `/etc/httpd/conf/httpd.conf` with the package default:
```bash
sudo cp /etc/httpd/conf/httpd.conf.bak /etc/httpd/conf/httpd.conf
```

If you didn't make a backup before editing, reinstall the config file:
```bash
sudo pacman -S --overwrite '/etc/httpd/conf/*' apache
```

## 4. Restore PHP config

Reset `/etc/php/php.ini` to package defaults:
```bash
sudo pacman -S --overwrite '/etc/php/*' php
```

Or manually re-comment the two lines you uncommented:

```ini
;extension=pdo_mysql
;extension=mysqli
```

## 5. Revert the systemd override

```bash
sudo vim /etc/systemd/system/httpd.service.d/hardening.conf
```

Comment out `ProtectHome=no` (or delete the line):
```ini
#ProtectHome=no
```

Then reload:
```bash
sudo systemctl daemon-reload
```

## 6. Remove packages

```bash
sudo pacman -Rns php apache mariadb php-apache
```

This removes the packages, their dependencies that aren't needed by anything else, and their config files.

## 7. Clean leftover data

```bash
sudo rm -rf /var/lib/mysql
sudo rm -rf /var/log/httpd
```

## 8. Delete the project

```bash
rm -rf /path/to/medicare-portal
```

---
