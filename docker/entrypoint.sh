#!/bin/bash
set -e

echo "==> Running Laravel startup tasks..."

cd /var/www/html

# ── 1. Fix storage & cache permissions ───────────────────────────────────────
# Each Docker deployment may create files owned by different users.
# This ensures www-data always owns the directories before anything else runs.
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# ── 2. Clear ALL stale caches ─────────────────────────────────────────────────
# Prevents Permission denied errors and stale Spatie permission cache
# that can cause 500 errors if the previous container wrote cache as a different user.
php artisan cache:clear          # Application cache (includes Spatie permission cache)
php artisan config:clear         # Config cache
php artisan route:clear          # Route cache
php artisan view:clear           # Compiled Blade views
php artisan event:clear          # Event/listener cache (if used)

# ── 3. Run pending migrations ─────────────────────────────────────────────────
php artisan migrate --force

# ── 4. Seed permissions (idempotent: uses firstOrCreate) ──────────────────────
php artisan db:seed --class=ReportPermissionsSeeder --force

# ── 5. Re-build optimized caches for production performance ──────────────────
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Startup tasks complete. Starting supervisor..."

exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
