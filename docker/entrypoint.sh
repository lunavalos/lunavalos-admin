#!/bin/bash
set -e

echo "==> Running Laravel startup tasks..."

cd /var/www/html

# Ensure any file created from this shell is group-writable so Apache (www-data)
# can append to logs even when artisan runs as root.
umask 002

# ── 1. Initial permission fix ─────────────────────────────────────────────────
# Force-create the log file so it exists with the correct owner BEFORE any
# artisan command (which runs as root here) writes to it.
mkdir -p /var/www/html/storage/logs /var/www/html/storage/framework/{cache,sessions,views,testing} /var/www/html/bootstrap/cache
touch /var/www/html/storage/logs/laravel.log

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
# Separate dir/file perms: dirs need execute bit, regular files don't.
find /var/www/html/storage /var/www/html/bootstrap/cache -type d -exec chmod 2775 {} +
find /var/www/html/storage /var/www/html/bootstrap/cache -type f -exec chmod 0664 {} +

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

# ── 6. CRITICAL: re-fix permissions after artisan (artisan ran as root) ──────
# Ensures Apache (www-data) can write Spatie's permission cache and laravel.log
# at runtime, including any new file artisan just created.
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
find /var/www/html/storage /var/www/html/bootstrap/cache -type d -exec chmod 2775 {} +
find /var/www/html/storage /var/www/html/bootstrap/cache -type f -exec chmod 0664 {} +

echo "==> Startup tasks complete. Starting supervisor..."

exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
