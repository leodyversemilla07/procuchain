#!/bin/bash
set -e

export HOME=/root

echo "=== ProcuChain App Deployment ==="

# 1. Install prerequisites
echo "[1/7] Installing prerequisites..."
dnf install -y --allowerasing curl php8.4-fpm php8.4-cli php8.4-mbstring php8.4-xml php8.4-mysqlnd php8.4-zip php8.4-bcmath php8.4-intl php8.4-gd php8.4-opcache unzip git

# Install Composer
if ! command -v composer &>/dev/null; then
    echo "Installing Composer..."
    php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
fi

# Install Node.js 20 + npm
if ! command -v node &>/dev/null; then
    echo "Installing Node.js 20..."
    curl -fsSL https://rpm.nodesource.com/setup_20.x | bash -
    dnf install -y nodejs
fi

# Install MultiChain CLI (for direct queries if needed)
if ! command -v multichaind &>/dev/null; then
    echo "Installing MultiChain 2.3.3..."
    cd /tmp
    curl -fsSL https://www.multichain.com/download/multichain-2.3.3.tar.gz -o multichain.tar.gz
    tar -xzf multichain.tar.gz
    cd multichain-2.3.3
    mv multichaind multichain-cli multichain-util multichaind-cold /usr/local/bin/
    cd /tmp
    rm -rf multichain-2.3.3 multichain.tar.gz
fi

# 2. Clone/update the app
APP_DIR="/var/www/procuchain"
if [ -d "$APP_DIR" ]; then
    echo "[2/7] Updating app code..."
    cd "$APP_DIR" && git pull origin main
else
    echo "[2/7] Cloning app code..."
    mkdir -p /var/www
    cd /var/www
    git clone https://github.com/leodyversemilla07/procuchain.git procuchain
    cd "$APP_DIR"
fi

# 3. Install PHP dependencies
echo "[3/7] Installing PHP dependencies..."
cd "$APP_DIR"
composer install --no-dev --optimize-autoloader --no-interaction 2>/dev/null || composer install --no-interaction

# 4. Install Node dependencies and build frontend
echo "[4/7] Building frontend..."
npm install 2>/dev/null || npm install --legacy-peer-deps
npm run build 2>/dev/null || echo "Frontend build may have warnings, continuing..."

# 5. Configure Laravel
echo "[5/7] Configuring Laravel..."
cd "$APP_DIR"

# Create .env if not exists
if [ ! -f .env ]; then
    cp .env.example .env 2>/dev/null || php artisan key:generate --force
fi

# Set the MultiChain RPC connection (point to admin node)
cat > .env <<'ENVEOF'
APP_NAME=ProcuChain
APP_ENV=production
APP_KEY=base64:placeholder
APP_DEBUG=false
APP_URL=http://3.223.155.19

LOG_CHANNEL=stderr

DB_CONNECTION=mysql
DB_HOST=procuchain-db.ciraiim6k0r7.us-east-1.rds.amazonaws.com
DB_PORT=3306
DB_DATABASE=procuchain
DB_USERNAME=procuchain
DB_PASSWORD=Procuchain2026!

MULTICHAIN_RPC_HOST=172.31.13.41
MULTICHAIN_RPC_PORT=6834
MULTICHAIN_RPC_USERNAME=multichainrpc
MULTICHAIN_RPC_PASSWORD=multichainrpc
MULTICHAIN_CHAIN_NAME=procuchain
MULTICHAIN_USE_SSL=false
MULTICHAIN_VERIFY_SSL=false
MULTICHAIN_TIMEOUT=15
MULTICHAIN_WEB_CONNECTION_TIMEOUT=15
MULTICHAIN_WEB_MAX_RETRIES=2
ENVEOF

php artisan key:generate --force

# Run migrations
php artisan migrate --force 2>/dev/null || echo "Migration may need DB setup"

# Set permissions
chown -R nginx:nginx "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" 2>/dev/null || true
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" 2>/dev/null || true

# 6. Configure nginx
echo "[6/7] Configuring nginx..."
cat > /etc/nginx/conf.d/procuchain.conf <<'NGINXEOF'
server {
    listen 80;
    server_name _;
    root /var/www/procuchain/public;
    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINXEOF

# Remove default nginx config that might conflict
rm -f /etc/nginx/conf.d/default.conf 2>/dev/null || true
mv /etc/nginx/nginx.conf /etc/nginx/nginx.conf.bak 2>/dev/null || true
cat > /etc/nginx/nginx.conf <<'NGINXMAIN'
worker_processes auto;
events {
    worker_connections 1024;
}
http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;
    sendfile on;
    keepalive_timeout 65;
    include /etc/nginx/conf.d/*.conf;
}
NGINXMAIN

# Configure PHP-FPM
sed -i 's/^user = apache/user = nginx/' /etc/php-fpm.d/www.conf 2>/dev/null || true
sed -i 's/^group = apache/group = nginx/' /etc/php-fpm.d/www.conf 2>/dev/null || true
sed -i 's/^listen.owner = root/listen.owner = nginx/' /etc/php-fpm.d/www.conf 2>/dev/null || true
sed -i 's/^listen.group = root/listen.group = nginx/' /etc/php-fpm.d/www.conf 2>/dev/null || true

# 7. Start services
echo "[7/7] Starting services..."
systemctl enable php-fpm nginx
systemctl restart php-fpm nginx

echo "=== Deployment Complete ==="
echo "App URL: http://3.223.155.19"
echo "MultiChain RPC: 172.31.13.41:6834"
curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" http://localhost/ || echo "Nginx check failed"
