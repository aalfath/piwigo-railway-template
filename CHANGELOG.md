# Changelog

Breaking changes are listed first in each entry.

## 16.5.0 (2026-11-21)

First release of this template.

- Piwigo 16.5.0 from the official image `piwigo/piwigo:16.5.0@sha256:d5cc258a0d5f19f4b808d9797d6c851af13fe270570dd59afe9cd4e8088034b6`.
- Unattended first install (webmaster `admin`, password from `PIWIGO_ADMIN_PASSWORD`), database settings from `MYSQL_URL`.
- `/healthz` health check, nginx on Railway's `PORT`, setup failures stop the container with an error so Railway restarts it.
- Sized for 0.5 GB services: ImageMagick limited to one job and one thread at a time, PHP memory limit 256 MB.
