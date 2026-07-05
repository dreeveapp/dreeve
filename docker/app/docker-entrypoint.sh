#!/bin/sh
set -e

echo "date.timezone=\"${TZ:-UTC}\"" > "${PHP_INI_DIR}/conf.d/timezone.ini"

# Run database migrations once at startup. The app and daemon containers share
# the same image and the same SQLite database volume, so flock serializes the
# two startups: the winner migrates.
flock /var/www/storage/database/migrate.lock \
    php /var/www/bin/console app:db:migrate --no-interaction

if [ -n "$PUID" ] && [ -n "$PGID" ] && [ "$(id -u)" = "0" ]; then
    echo "Setting permissions for PUID=$PUID PGID=$PGID..."

    chown -R "$PUID:$PGID" \
        /var/www \
        /config/caddy \
        /data/caddy || true

    echo "Permissions have been set"
fi

exec "$@"