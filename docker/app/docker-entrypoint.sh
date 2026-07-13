#!/bin/sh
set -e

echo "date.timezone=\"${TZ:-UTC}\"" > "${PHP_INI_DIR}/conf.d/timezone.ini"

# The app and daemon containers share the same image and the same SQLite database
# volume, so only one of them may migrate. The web container is the migrator, every other
# container waits for it to finish before touching the database.
if [ "$1" = "frankenphp" ]; then
    php /var/www/bin/console app:db:migrate --no-interaction
else
    echo 'Waiting for the database schema to be up to date...'
    php /var/www/bin/console app:db:wait-for-schema
    echo 'Database schema updated, starting daemon'
fi

if [ -n "$PUID" ] && [ -n "$PGID" ] && [ "$(id -u)" = "0" ]; then
    echo "Setting permissions for PUID=$PUID PGID=$PGID..."

    chown -R "$PUID:$PGID" \
        /var/www \
        /config/caddy \
        /data/caddy || true

    echo "Permissions have been set"
fi

exec "$@"