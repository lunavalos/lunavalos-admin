#!/bin/bash
set -e

echo "==> Running Laravel startup tasks..."

cd /var/www/html

# ── 1. Fix storage & cache permissions ───────────────────────────────────────
# Ensure www-data owns everything before AND after artisan runs.
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# ── 2. Clear ALL stale caches (run as www-data to avoid root-owned files) ────
# This is the key fix: artisan runs as www-data (same user as Apache),
# so all generated cache files are owned by www-data and Apache can write them.
su -s /bin/bash www-data -c "php artisan cache:clear"
su -s /bin/bash www-data -c "php artisan config:clear"
su -s /bin/bash www-data -c "php artisan route:clear"
su -s /bin/bash www-data -c "php artisan view:clear"

# ── 3. Run pending migrations (as www-data) ───────────────────────────────────
su -s /bin/bash www-data -c "php artisan migrate --force"

# ── 4. Seed permissions (idempotent) ──────────────────────────────────────────
su -s /bin/bash www-data -c "php artisan db:seed --class=ReportPermissionsSeeder --force"

# ── 5. Re-build optimized caches (as www-data) ───────────────────────────────
su -s /bin/bash www-data -c "php artisan config:cache"
su -s /bin/bash www-data -c "php artisan route:cache"
su -s /bin/bash www-data -c "php artisan view:cache"

# ── 6. Final permission fix (in case any step above ran as root accidentally) ─
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

echo "==> Startup tasks complete. Starting supervisor..."

exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
