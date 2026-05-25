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
#   1. Central migrate  — creates/updates central tables (users, roles, plans, …)
#   2. db:seed          — roles, plans, etc. must exist before the app serves traffic
#   3. tenants:migrate  — applies tenant DB migrations (requires tenant DBs to exist)
# Seed cannot run before migrate: tables do not exist yet.
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running central migrations..."
    php artisan migrate --force

    echo "Running seeders..."
    php artisan db:seed --force

    echo "Running tenant migrations..."
    php artisan tenants:migrate --force
fi

# If a command is passed to the entrypoint, execute it instead of starting Supervisor
if [ $# -gt 0 ]; then
    echo "Executing Command: $@"
    exec "$@"
fi

# Start Supervisor
echo "Starting Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisor.conf
