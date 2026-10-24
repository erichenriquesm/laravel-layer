#!/bin/bash
# Usage:
#   entrypoint web                                → nginx + PHP-FPM (default)
#   entrypoint php artisan queue:work rabbitmq    → any other command (worker, migrations…)
set -euo pipefail

cd /var/www

# Config, routes and events are cached at start, not at build: the environment
# (secrets, DB host, Passport keys) only exists once the orchestrator injects it.
# Not "artisan optimize": it also runs view:cache, which fails in an API without resources/views.
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan event:cache --no-interaction

if [ "${1:-web}" != "web" ]; then
    exec "$@"
fi

php-fpm -F &
fpm_pid=$!

nginx -g 'daemon off;' &
nginx_pid=$!

# Graceful stop: nginx first (stops accepting, drains in-flight requests), then FPM.
shutdown() {
    kill -QUIT "$nginx_pid" 2>/dev/null || true
    wait "$nginx_pid" 2>/dev/null || true
    kill -QUIT "$fpm_pid" 2>/dev/null || true
    wait "$fpm_pid" 2>/dev/null || true
}

trap 'shutdown; exit 0' TERM INT QUIT

# If either process dies, take the other down too so the orchestrator replaces the task.
set +e
wait -n "$fpm_pid" "$nginx_pid"
status=$?
set -e

echo "entrypoint: a process exited with status $status, stopping the container" >&2
shutdown

# A web process ending, even cleanly, is a failure for the orchestrator.
if [ "$status" -eq 0 ]; then
    status=1
fi
exit "$status"
