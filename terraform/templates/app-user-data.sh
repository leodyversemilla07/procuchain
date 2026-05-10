#!/bin/bash
set -e

# Install system dependencies
dnf update -y
dnf install -y docker git nginx

# Enable PHP 8.4 via Amazon Linux Extras
amazon-linux-extras enable php8.4
dnf clean metadata

# Install PHP 8.4 extensions (using correct names)
dnf install -y \
  php \
  php-fpm \
  php-mysqlnd \
  php-mbstring \
  php-xml \
  php-json \
  php-bcmath \
  php-curl \
  php-zip \
  php-dom \
  php-simplexml \
  php-tokenizer \
  php-fileinfo

# Install Composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Clone the application
mkdir -p /var/www
cd /var/www
rm -rf procuchain
git clone ${github_repo_url} procuchain
cd procuchain

# Create .env
cat > .env << ENV
APP_NAME=ProcuChain
APP_ENV=production
APP_KEY=${app_key}
APP_DEBUG=false
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=${rds_endpoint}
DB_PORT=3306
DB_DATABASE=${rds_database}
DB_USERNAME=${rds_username}
DB_PASSWORD=${rds_password}

MULTICHAIN_CHAIN_NAME=${chain_name}
MULTICHAIN_RPC_USER=${rpc_user}
MULTICHAIN_RPC_PASSWORD=${rpc_password}
MULTICHAIN_RPC_PORT=${rpc_port}

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

CACHE_STORE=file
CACHE_DRIVER=file
ENV

# Install dependencies
composer install --no-ansi --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-dev

# Install Node.js 22
curl -fsSL https://rpm.nodesource.com/setup_22.x | bash -
dnf install -y nodejs

# Build assets
npm ci
npm run build

# Create PHP-FPM socket directory
mkdir -p /var/run/php

# Configure PHP-FPM
cat > /etc/php-fpm.d/www.conf << 'PHPFPM'
[www]
user = nginx
group = nginx
listen = /var/run/php/php-fpm.sock
listen.owner = nginx
listen.group = nginx
listen.mode = 0660
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500
php_admin_value[error_log] = /var/log/php-fpm/www-error.log
php_admin_flag[log_errors] = on
PHPFPM

# Configure Nginx
cat > /etc/nginx/conf.d/procuchain.conf << 'NGINX'
server {
    listen 80;
    server_name _;
    root /var/www/procuchain/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX

# Permissions
chown -R nginx:nginx /var/www/procuchain
chmod -R 775 /var/www/procuchain/storage /var/www/procuchain/bootstrap/cache

# Create log directories
mkdir -p /var/log/php-fpm
touch /var/log/php-fpm/www-error.log
chown nginx:nginx /var/log/php-fpm/www-error.log

# Run migrations (wait for DB to be ready)
for i in {1..30}; do
  if php artisan migrate --force --no-interaction 2>/dev/null; then
    echo "Migrations completed successfully"
    break
  fi
  echo "Waiting for database... ($$i/30)"
  sleep 10
done

# Start services
systemctl enable php-fpm nginx
systemctl start php-fpm nginx
systemctl enable docker
systemctl start docker

echo "App server setup complete!"