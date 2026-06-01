#!/bin/sh

# Exit on error
set -e

echo "Running Entrypoint Tasks..."

# Clear and cache configurations
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# When RUN_MIGRATIONS=true (set on the ECS task definition):
#   1. Central migrate  — users, plans, ocr_settings, etc. (database/migrations/)
#   2. tenants:migrate  — per-tenant accounting schema (database/migrations/tenant/)
#
# --isolated: during rolling deploys only one container runs pending migrations;
# others skip quickly if another task holds the lock.
#
# db:seed is intentionally NOT run here — seeders include demo/test data and must
# not run on every deploy. See DEPLOYMENT.md for first-time seed and permission sync.
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running central migrations..."
    php artisan migrate --force --isolated

    echo "Running tenant migrations..."
    php artisan tenants:migrate --force --isolated
fi

# If a command is passed to the entrypoint, execute it instead of starting Supervisor
if [ $# -gt 0 ]; then
    echo "Executing Command: $@"
    exec "$@"
fi

# Start Supervisor
echo "Starting Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisor.conf
