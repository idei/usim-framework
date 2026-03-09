#!/bin/bash

set -e

APP_DIR="/var/www/app"
DOMAIN="usim.idei.unsj.edu.ar"

echo "--------------------------------"
echo "Laravel production installer"
echo "Debian 13 (Trixie)"
echo "--------------------------------"

# ---- Fix DNS if missing ----

if ! ping -c1 deb.debian.org >/dev/null 2>&1; then
    echo "DNS not working. Fixing resolv.conf..."

    echo "nameserver 1.1.1.1" > /etc/resolv.conf
    echo "nameserver 8.8.8.8" >> /etc/resolv.conf
fi

# ---- Update system ----

echo "Updating system..."

apt update
apt upgrade -y

# ---- Base packages ----

echo "Installing base packages..."

apt install -y \
curl \
git \
unzip \
zip \
ca-certificates \
lsb-release \
apt-transport-https

# ---- Nginx ----

echo "Installing Nginx..."

apt install -y nginx

# ---- PHP ----

echo "Installing PHP..."

apt install -y \
php \
php-fpm \
php-cli \
php-mysql \
php-xml \
php-mbstring \
php-curl \
php-zip \
php-bcmath \
php-intl \
php-gd \
php-redis

# Detect PHP version
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")

# ---- Database ----

echo "Installing MariaDB..."

apt install -y mariadb-server

# ---- Redis ----

echo "Installing Redis..."

apt install -y redis-server

# ---- Supervisor ----

echo "Installing Supervisor..."

apt install -y supervisor

# ---- Node.js (LTS) ----

echo "Installing Node.js..."

curl -fsSL https://deb.nodesource.com/setup_lts.x | bash -
apt install -y nodejs

# ---- Composer ----

echo "Installing Composer..."

cd /tmp

curl -sS https://getcomposer.org/installer -o composer-setup.php

php composer-setup.php

mv composer.phar /usr/local/bin/composer

# ---- Certbot ----

echo "Installing Certbot..."

apt install -y certbot python3-certbot-nginx

# ---- Application directory ----

echo "Creating application directory..."

mkdir -p $APP_DIR

chown -R www-data:www-data $APP_DIR

# ---- Nginx config ----

echo "Configuring Nginx..."

cat > /etc/nginx/sites-available/laravel <<EOF
server {
    listen 80;
    server_name $DOMAIN;

    root $APP_DIR/public;
    index index.php;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
        include snippets/fastcgi-php.conf;
    }

    location ~ /\.ht {
        deny all;
    }
}
EOF

ln -sf /etc/nginx/sites-available/laravel /etc/nginx/sites-enabled/laravel
rm -f /etc/nginx/sites-enabled/default

# ---- Restart services ----

echo "Restarting services..."

systemctl restart nginx
systemctl restart php${PHP_VERSION}-fpm
systemctl enable nginx
systemctl enable php${PHP_VERSION}-fpm
systemctl enable mariadb
systemctl enable redis-server
systemctl enable supervisor

# ---- Laravel permissions ----

mkdir -p $APP_DIR/storage
mkdir -p $APP_DIR/bootstrap/cache

chown -R www-data:www-data $APP_DIR
chmod -R 775 $APP_DIR/storage
chmod -R 775 $APP_DIR/bootstrap/cache

echo "--------------------------------"
echo "Server ready for Laravel"
echo ""
echo "Next steps:"
echo "1) Deploy code into $APP_DIR"
echo "2) Create .env"
echo "3) Run:"
echo "   composer install --no-dev"
echo "   php artisan migrate --force"
echo "   php artisan optimize"
echo ""
echo "--------------------------------"
