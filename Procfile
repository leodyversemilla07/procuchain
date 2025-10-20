release: php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache
web: (php artisan inertia:start-ssr > /dev/null 2>&1) & heroku-php-apache2 public/
worker: php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --timeout=60 --verbose --stop-when-empty
