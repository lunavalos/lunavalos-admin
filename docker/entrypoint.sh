#!/bin/bash
set -e

echo "==> Running Laravel startup tasks..."

cd /var/www/html

# Run pending migrations (safe: --force skips the confirmation prompt)
php artisan migrate --force

# Seed permissions (idempotent: uses firstOrCreate so safe to run every deploy)
php artisan db:seed --class=ReportPermissionsSeeder --force

# Clear & cache config/routes for performance
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Startup tasks complete. Starting supervisor..."

exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
