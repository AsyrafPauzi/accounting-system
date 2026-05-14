#!/bin/sh

# Exit on error
set -e

echo "Running Entrypoint Tasks..."

# Clear and cache configurations
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations (only if not in local environment or if forced)
# In production, it's safer to run this manually or via a CI/CD pipeline, 
# but for simple deployments, we can do it here.
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running Migrations..."
    php artisan migrate --force
    php artisan tenants:migrate --force
    echo "Running Seeders..."
    php artisan db:seed --force
fi

# If a command is passed to the entrypoint, execute it instead of starting Supervisor
if [ $# -gt 0 ]; then
    echo "Executing Command: $@"
    exec "$@"
fi

# Start Supervisor
echo "Starting Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisor.conf
