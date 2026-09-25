#!/bin/sh
# Warm the framework caches from the runtime environment, then run the container's command.
# Route caching is skipped on purpose: routes/web.php still has a closure route.
set -e
cd /app
php artisan config:cache
php artisan event:cache
php artisan view:cache
exec "$@"
