# Piwigo on Railway: a thin wrapper around the official image (Piwigo/piwigo-docker).
# Bump the version and digest together; every merge to main notifies deployers.
FROM piwigo/piwigo:16.5.0@sha256:d5cc258a0d5f19f4b808d9797d6c851af13fe270570dd59afe9cd4e8088034b6

# TZ: the upstream init script runs with `set -u` and dies without it.
# PIWIGO_UID/GID: own the volume as nginx (php-fpm's user), so uploads work even
# where the volume has no ACL support.
# S6_BEHAVIOUR_IF_STAGE2_FAILS=2: if setup fails (database unreachable, install
# error) the container stops with a non-zero code, so Railway restarts it.
ENV TZ=UTC \
    PIWIGO_UID=100 \
    PIWIGO_GID=101 \
    S6_BEHAVIOUR_IF_STAGE2_FAILS=2 \
    S6_CMD_WAIT_FOR_SERVICES_MAXTIME=0

COPY railway/ /railway/
COPY nginx.conf /etc/nginx/nginx.conf

RUN set -eux; \
    chmod 0755 /railway/init.sh; \
    for f in /railway/*.php; do php84 -l "$f"; done; \
    # Our setup step wraps the upstream one (copy/upgrade files, permissions).
    printf '/bin/ash -c "/railway/init.sh"\n' > /etc/s6-overlay/s6-rc.d/init-script/up; \
    sed -i '1i #!/bin/execlineb -P' /etc/s6-overlay/s6-rc.d/init-script/up; \
    # Fit Railway's smallest plans (0.5 GB per service): fewer PHP workers, and
    # ImageMagick spills big photos to disk instead of RAM.
    sed -i 's/^memory_limit=.*/memory_limit=256M/' /etc/php84/conf.d/piwigo.ini; \
    sed -i 's/^pm.max_children = .*/pm.max_children = 4/' /etc/php84/php-fpm.d/www.conf; \
    sed -i 's|^</policymap>|  <policy domain="resource" name="memory" value="128MiB"/>\n  <policy domain="resource" name="map" value="256MiB"/>\n</policymap>|' /etc/ImageMagick-7/policy.xml; \
    grep -q 'name="memory" value="128MiB"' /etc/ImageMagick-7/policy.xml; \
    grep -q '^memory_limit=256M' /etc/php84/conf.d/piwigo.ini; \
    grep -q '^pm.max_children = 4' /etc/php84/php-fpm.d/www.conf

EXPOSE 8080
ENTRYPOINT ["/init"]
