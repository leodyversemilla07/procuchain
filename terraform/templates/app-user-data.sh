#!/bin/bash
set -euxo pipefail

# ============================================
# ProcuChain — Laravel Application Server
# Amazon Linux 2023 + PHP 8.4 + Nginx + Node.js 22
# ============================================

# Install system dependencies (AL2023: curl conflicts with curl-minimal)
dnf update -y
dnf install -y --allowerasing git nginx curl

# Install AWS SSM Agent for remote management
dnf install -y amazon-ssm-agent
systemctl enable amazon-ssm-agent
systemctl start amazon-ssm-agent

# Install PHP 8.4 on Amazon Linux 2023
# AL2023 uses dnf (not amazon-linux-extras which was AL2 only)
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
  php-gd \
  php-intl \
  php-opcache

php -v

# Install Composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# ============================================
# CLONE AND SET UP LARAVEL APP
# ============================================
mkdir -p /var/www
cd /var/www
rm -rf procuchain
git clone -b "${github_branch}" "${github_repo_url}" procuchain
cd procuchain

# Create .env from template
cat > .env <<ENV
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

# MultiChain — admin node RPC endpoint
MULTICHAIN_CHAIN_NAME=${chain_name}
MULTICHAIN_RPC_USER=${rpc_user}
MULTICHAIN_RPC_PASSWORD=${rpc_password}
MULTICHAIN_RPC_PORT=${rpc_port}
MULTICHAIN_RPC_HOST=${admin_node_ip}

# All MultiChain node IPs (JSON) — app can query any node
MULTICHAIN_NODES='${node_ips}'

SESSION_DRIVER=database
SESSION_LIFETIME=120
CACHE_STORE=file
QUEUE_CONNECTION=database
ENV

# Install PHP dependencies
composer install --no-ansi --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-dev

# Install Node.js 22
curl -fsSL https://rpm.nodesource.com/setup_22.x | bash -
dnf install -y nodejs

# Build frontend assets
npm ci
npm run build

# ============================================
# CONFIGURE PHP-FPM
# ============================================
mkdir -p /var/run/php

cat > /etc/php-fpm.d/www.conf <<'PHPFPM'
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

# ============================================
# CONFIGURE NGINX
# ============================================
cat > /etc/nginx/conf.d/procuchain.conf <<'NGINX'
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
    location = /robots.txt { access_log off; log_not_found off; }

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

rm -f /etc/nginx/conf.d/default.conf 2>/dev/null || true

# ============================================
# PERMISSIONS & LOGS
# ============================================
chown -R nginx:nginx /var/www/procuchain
chmod -R 775 /var/www/procuchain/storage /var/www/procuchain/bootstrap/cache
mkdir -p /var/log/php-fpm
touch /var/log/php-fpm/www-error.log
chown nginx:nginx /var/log/php-fpm/www-error.log

# ============================================
# RUN MIGRATIONS (wait for RDS to be ready)
# ============================================
for i in $(seq 1 30); do
    if php artisan migrate --force --no-interaction 2>/dev/null; then
        echo "Migrations completed successfully."
        break
    fi
    echo "Waiting for database... ($$i/30)"
    sleep 10
done

# ============================================
# START SERVICES
# ============================================
systemctl enable php-fpm nginx
systemctl start php-fpm nginx

# Queue worker via supervisor
dnf install -y supervisor 2>/dev/null || true
if command -v supervisord &>/dev/null; then
    cat > /etc/supervisord.d/procuchain-worker.ini <<'SUPERVISOR'
[program:procuchain-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/procuchain/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=nginx
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/procuchain/storage/logs/worker.log
stopwaitsecs=3600
SUPERVISOR

    systemctl enable supervisord
    systemctl start supervisord
    supervisorctl reread
    supervisorctl update
fi

# Laravel scheduler cron
(crontab -l 2>/dev/null; echo "* * * * * cd /var/www/procuchain && php artisan schedule:run >> /dev/null 2>&1") | crontab -

echo "========================================"
echo "APP SERVER READY"
echo "URL: http://$(curl -s ifconfig.me 2>/dev/null || echo 'pending')"
echo "========================================"
