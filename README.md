[![Deploy on Railway](https://railway.com/button.svg)](https://railway.com/new/template/piwigo-1?utm_medium=integration&utm_source=button&utm_campaign=piwigo)

# Piwigo on Railway

One-click [Piwigo](https://piwigo.org) photo gallery for [Railway](https://railway.com), built from the official [`piwigo/piwigo`](https://hub.docker.com/r/piwigo/piwigo) image ([Piwigo/piwigo-docker](https://github.com/Piwigo/piwigo-docker)).

- **Piwigo 16.5.0**, pinned as `piwigo/piwigo:16.5.0@sha256:d5cc258a0d5f19f4b808d9797d6c851af13fe270570dd59afe9cd4e8088034b6` (Alpine, nginx, PHP 8.4, ImageMagick, ffmpeg).
- **MySQL**: Railway's own MySQL service, reached over the private network.

## What the template deploys

| Service | What it does | Volume |
|---|---|---|
| **Piwigo** (this repo) | Web app on Railway's `PORT` (8080), public domain, health check `/healthz` | `/var/www/html/piwigo`: app files, uploaded photos (`upload/`), generated sizes (`_data/`), plugins, themes, `local/` settings |
| **MySQL** | Gallery database (albums, users, tags, comments, settings) | `/var/lib/mysql` |

On the first boot the wrapper waits for MySQL and runs Piwigo's own installer with no browser, so the public URL never shows the setup wizard to strangers. The webmaster account is `admin`, with the password Railway generated in `PIWIGO_ADMIN_PASSWORD`. Later boots only refresh `local/config/database.inc.php` from `MYSQL_URL`.

## After deploying

1. Open the Piwigo service's public URL.
2. Log in as **admin**. The password is the `PIWIGO_ADMIN_PASSWORD` value on the Piwigo service's **Variables** tab.
3. Go to **Admin → Users**, set your own email address on the admin account (it starts as `admin@example.com`) and change the password if you like. Changing the variable afterwards does not change the password: it is only used for the first install.
4. Create an album and upload photos (**Admin → Photos → Add**).

Optional: set up outgoing mail under **Admin → Configuration → Options → Email** (SMTP host, user, password) if you want password-reset and notification emails. Out of the box the container's Postfix only delivers locally.

## Variables

| Service | Variable | Default | Notes |
|---|---|---|---|
| Piwigo | `PORT` | `8080` | Port nginx listens on |
| Piwigo | `MYSQL_URL` | `${{MySQL.MYSQL_URL}}` | Database connection, read on every boot |
| Piwigo | `PIWIGO_ADMIN_USER` | `admin` | Webmaster login created on the first boot |
| Piwigo | `PIWIGO_ADMIN_PASSWORD` | `${{secret(24, …)}}` | Webmaster password for the first boot only |

Advanced, not in the template: `PIWIGO_ADMIN_EMAIL` (first-boot admin email, default `admin@example.com`), `PIWIGO_DB_PREFIX` (table prefix, default `piwigo_`), `PIWIGO_DB_WAIT_SECONDS` (how long to wait for MySQL, default 180), `TZ` (default `UTC`).

## Resources

Runs on Railway's smallest plans (0.5 GB RAM, 1 vCPU per service). Piwigo idles at about 40 MB. ImageMagick is limited to one job and one thread at a time, so generating many thumbnails at once stays inside the limit, and photos up to 100 MB can be uploaded. MySQL starts with a 128 MB InnoDB buffer pool. Larger galleries are faster with more RAM on MySQL.

If setup fails (for example MySQL never answers), the container exits with an error, so Railway's restart policy tries again.

## Updates

Each new Piwigo release is a commit to `main` here: a new pinned version and digest, plus an entry in [CHANGELOG.md](CHANGELOG.md), with breaking changes first. Railway shows deployers an "update available" notice; when you apply it, the new image copies the new Piwigo files over the old ones on the volume (your photos, `_data` and `local/` stay), and Piwigo runs any database upgrade when the webmaster next opens the gallery. Back up the volume and database before major upgrades.

## Files

- `Dockerfile`: the pinned official image plus the wrapper below.
- `railway/init.sh`: sets the nginx port from `PORT`, runs the upstream setup step, then `setup.php`.
- `railway/setup.php`, `railway/common.php`: wait for MySQL, unattended first install, database settings from `MYSQL_URL`.
- `railway/health.php`: `/healthz`, 200 only when PHP and the Piwigo database answer.
- `railway/magick`: runs ImageMagick jobs one at a time.
- `nginx.conf`: upstream's config plus `/healthz`, port 8080 and no access to `local/config` or logs.

Licence: Piwigo is GPL-2.0. This wrapper is provided under the same licence.
