#!/bin/bash
# Clear dashboard caches after deployment to ensure fresh data
# This prevents stale Carbon objects from causing "Invalid Date" on frontend

cd /var/app/current
php artisan cache:clear 2>/dev/null || true
