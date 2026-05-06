#!/bin/bash
set -e

echo "==> Running Laravel startup tasks..."

cd /var/www/html

# ── 1. Initial permission fix ─────────────────────────────────────────────────
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# ── 2. Clear ALL stale caches ────────────────────────────────────────────────
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# ── 3. Run pending migrations ─────────────────────────────────────────────────
php artisan migrate --force

# ── 4. Seed permissions (idempotent) ──────────────────────────────────────────
php artisan db:seed --class=ReportPermissionsSeeder --force

# ── 5. Re-build optimized caches ─────────────────────────────────────────────
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ── 6. CRITICAL: re-fix permissions after artisan (artisan runs as root here) ─
# This ensures Apache (www-data) can write Spatie's permission cache at runtime.
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

echo "==> Startup tasks complete. Starting supervisor..."

exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
