#!/bin/sh
# BukuCloud self-hosted runtime entrypoint.
#
# Runs the same idempotent setup steps on every boot — migrations,
# storage link, route cache. Tens of milliseconds when there's
# nothing to do; safe to re-run after a customer pulls a new image.

set -e

cd /var/www/html

# Wait for the database to accept connections — give up after 60s so
# a misconfigured DB host doesn't keep the container in a restart loop
# forever. The customer's logs will show the timeout and they can fix.
echo "[entrypoint] waiting for database…"
i=0
until php -r 'try { new PDO(getenv("DB_DSN") ?: "mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT") ?: 3306), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); echo "ok\n"; } catch (Throwable $e) { fwrite(STDERR, $e->getMessage()."\n"); exit(1); }' >/dev/null 2>&1; do
    i=$((i + 1))
    if [ "$i" -ge 60 ]; then
        echo "[entrypoint] database not reachable after 60 attempts; giving up." >&2
        exit 1
    fi
    sleep 1
done
echo "[entrypoint] database reachable."

# Run migrations + link storage + warm caches. These are all
# idempotent so re-running on every boot is a feature, not a bug.
php artisan migrate --force --no-interaction --isolated
if [ "${RUN_TENANT_MIGRATE:-0}" = "1" ]; then
    echo "[entrypoint] RUN_TENANT_MIGRATE=1 — running tenants:migrate…"
    php artisan tenants:migrate --force --isolated
fi
php artisan app:sync-roles-permissions
php artisan app:sync-plans
php artisan storage:link || true

# Cache config / routes / views. We deliberately don't `optimize`
# because it sometimes inlines env values that should remain dynamic
# across container restarts.
php artisan config:cache
php artisan route:cache || true
php artisan view:cache  || true

echo "[entrypoint] boot complete."

# docker-compose passes an explicit command (php-fpm, queue:work, …).
# The ECS image has no CMD — start supervisor here when nothing was passed.
if [ $# -gt 0 ]; then
    exec "$@"
fi

echo "[entrypoint] starting supervisor…"
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisor.conf
