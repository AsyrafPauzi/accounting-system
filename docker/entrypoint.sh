#!/bin/sh

# Exit on error
set -e

echo "Running Entrypoint Tasks..."

# The storage volume is mounted at runtime, potentially over the image's
# storage directory. Ensure www-data can write to it and that the
# public/storage symlink exists (it lives in public/, outside the volume).
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
php artisan storage:link --force

# Clear and cache configurations
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# When RUN_MIGRATIONS=true (set on the ECS task definition):
#   1. Central migrate  — users, plans, ocr_settings, etc. (database/migrations/)
#   2. tenants:migrate  — per-tenant accounting schema (database/migrations/tenant/)
#   3. PlanSeeder                  — which features each subscription tier allows
#   4. app:sync-roles-permissions  — which actions each user role can perform
#      (both central-only, idempotent, no demo accounts)
#
# --isolated: during rolling deploys only one container runs pending migrations;
# others skip quickly if another task holds the lock.
#
# Full db:seed is intentionally NOT run here — most seeders include demo/test data.
# PlanSeeder and sync-roles-permissions are exceptions: they only sync permission
# definitions; the sidebar needs both plan AND role permissions to show a link.
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running central migrations..."
    php artisan migrate --force --isolated

    echo "Running tenant migrations..."
    php artisan tenants:migrate --force --isolated

    echo "Syncing subscription plan permissions..."
    php artisan db:seed --class=PlanSeeder --force

    echo "Syncing role permissions..."
    php artisan app:sync-roles-permissions
fi

# If a command is passed to the entrypoint, execute it instead of starting Supervisor
if [ $# -gt 0 ]; then
    echo "Executing Command: $@"
    exec "$@"
fi

# Start Supervisor
echo "Starting Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisor.conf
