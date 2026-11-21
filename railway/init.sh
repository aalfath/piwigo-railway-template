#!/command/with-contenv ash
# One-shot setup, run by s6 before nginx and php-fpm start.
# Any failure stops the container with a non-zero code (S6_BEHAVIOUR_IF_STAGE2_FAILS=2),
# so Railway's restart policy retries it.
set -eu

PORT="${PORT:-8080}"
case "$PORT" in ''|*[!0-9]*) echo "[railway] PORT must be a number, got '$PORT'" >&2; exit 1;; esac
sed -i -E "s/listen( +)[0-9]+;/listen\1${PORT};/" /etc/nginx/nginx.conf
echo "[railway] nginx will listen on port ${PORT}" >&2

# Upstream step: copy or upgrade the Piwigo files on the volume, fix ownership.
/init-script.sh

# Our step: wait for MySQL, write the database settings, install on first boot.
php84 /railway/setup.php

# Files created by setup belong to the web server user.
chown -R "${PIWIGO_UID}:${PIWIGO_GID}" /var/www/html/piwigo/local /var/www/html/piwigo/_data
