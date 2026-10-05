# Docker

[Versione italiana](README.md)

Fatturino runs in one Docker container based on [serversideup/php](https://serversideup.net/open-source/docker-php/), using S6 Overlay to supervise its processes.

## Architecture

The container runs PostgreSQL, NGINX with PHP-FPM, the Laravel queue worker, and the scheduler. During bootstrap it creates the persistent data structure, initializes PostgreSQL, runs migrations, seeds first-run data, and optimizes Laravel. Persistent data is stored in the `/data` volume:

```text
/data/
├── postgresql/                  # PostgreSQL cluster
├── .seeded                      # first-run marker
└── storage/
    ├── app/private/             # imports and document snapshots
    ├── app/public/              # public uploads, including logos
    └── logs/                    # application logs
```

PostgreSQL, cache, queue, and sessions share the container. PostgreSQL is reachable only on the private container network and is not published to the host. Do not add port `5432` to `ports` or `x-ports`. Bootstrap PostgreSQL runs only for setup, then S6 starts the permanent database service. The cluster at `/data/postgresql` remains owned by the `postgres` system user.

## Quick start

```bash
# 1. Generate an application key.
docker run --rm fatturino php artisan key:generate --show

# 2. Create .env with the generated key and random database passwords.
echo "APP_KEY=base64:xxxxx" > .env
echo "APP_URL=http://localhost:8080" >> .env
echo "DB_PASSWORD=$(openssl rand -base64 32)" >> .env
echo "POSTGRES_SUPERUSER_PASSWORD=$(openssl rand -base64 32)" >> .env

# 3. Start the instance.
docker compose up -d

# 4. Open it in a browser.
open http://localhost:8080
```

## Configuration

| Variable                      | Required | Default                 | Purpose                                                                     |
| ----------------------------- | :------: | ----------------------- | --------------------------------------------------------------------------- |
| `APP_KEY`                     |   Yes    | —                       | Laravel encryption key, generated with `key:generate --show`.               |
| `APP_URL`                     |   Yes    | `http://localhost:8080` | Public application URL.                                                     |
| `APP_PORT`                    |    No    | `8080`                  | Host port used by Compose, not passed to the container.                     |
| `APP_ENV`                     |    No    | `production`            | Laravel environment.                                                        |
| `DB_DATABASE` / `DB_USERNAME` |    No    | `fatturino`             | Internal PostgreSQL database and application user.                          |
| `DB_PASSWORD`                 |   Yes    | —                       | Application database password. Do not change it after first start.          |
| `POSTGRES_SUPERUSER_PASSWORD` |   Yes    | —                       | PostgreSQL superuser password. Do not change it after first start.          |
| `SMTP_MANAGED_BY_ENV`         |    No    | `false`                 | Makes email provider configuration environment-owned and hides its UI.      |
| `BACKUP_MANAGED_BY_ENV`       |    No    | `false`                 | Makes backup configuration environment-owned and disables its UI/scheduler. |
| `DOCUMENTS_DISK`              |    No    | `local`                 | Use `s3` for cloud document snapshots.                                      |
| `APP_INSTANCE_ID`             |    No    | `APP_NAME` fallback     | Stable namespace for documents, backups, and telemetry.                     |

When mail or backups are managed by environment variables, keep credentials outside the repository. For SMTP use `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, and `MAIL_FROM_NAME`. For Scaleway TEM use `MAIL_MAILER=scaleway_tem`, `SCALEWAY_TEM_REGION`, `SCALEWAY_TEM_PROJECT_ID`, and `SCALEWAY_TEM_SECRET_KEY`. For managed S3 backups, set `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, and `AWS_BUCKET`.

```yaml
services:
    fatturino:
        image: codeberg.org/fatturino/fatturino:latest-stable
        ports:
            - "8080:8080"
        volumes:
            - fatturino-data:/data
        environment:
            APP_KEY: "base64:your-generated-key-here"
            APP_URL: "https://fatturino.example.com"
            DB_PASSWORD: "application-password"
            POSTGRES_SUPERUSER_PASSWORD: "superuser-password"
            SMTP_MANAGED_BY_ENV: "true"
            MAIL_MAILER: "smtp"
            MAIL_HOST: "smtp.example.com"
            MAIL_PORT: "587"
            MAIL_USERNAME: "user@example.com"
            MAIL_PASSWORD: "password"
            MAIL_FROM_ADDRESS: "fatture@example.com"
            MAIL_FROM_NAME: "Fatturino"
        restart: unless-stopped

volumes:
    fatturino-data:
```

Use `latest-stable` in production. `latest` tracks the newest push to `main` for development/staging. `vX.Y.Z` tags are immutable releases.

## Documents, backups, and restore

Local document snapshots and application data remain in `/data`. With `DOCUMENTS_DISK=s3`, XML/PDF snapshots are authoritative at `{APP_INSTANCE_ID}/documents/...` in the configured bucket and are excluded from backup archives. Backups use `{APP_INSTANCE_ID}/backups/...`; when `APP_INSTANCE_ID` is empty, these prefixes are simply `documents/...` and `backups/...`.

Create a complete volume archive:

```bash
docker run --rm \
  -v fatturino-data:/data \
  -v $(pwd):/backup \
  alpine tar czf /backup/fatturino-$(date +%Y%m%d).tar.gz -C / data
```

Restore is destructive. Stop the application and test the archive in staging first:

```bash
docker compose down
docker volume rm fatturino-data
docker volume create fatturino-data
docker run --rm \
  -v fatturino-data:/data \
  -v $(pwd):/backup \
  alpine tar xzf /backup/fatturino-YYYYMMDD.tar.gz -C /
docker compose up -d
```

For a database-only backup:

```bash
docker exec fatturino pg_dump --username=fatturino --format=custom --file=/data/fatturino.dump fatturino
docker cp fatturino:/data/fatturino.dump ./fatturino-db-$(date +%Y%m%d).dump
docker exec fatturino rm /data/fatturino.dump
```

Fatturino also schedules S3 backups through `spatie/laravel-backup`. In self-hosted mode, configure the S3 destination in **Settings > Services** and choose `daily`, `weekly`, or `monthly` frequency. The scheduler runs `backup:run` at the configured time and `backup:clean` nightly at 03:30. In managed mode, configuration and scheduling are external.

Run a manual backup with:

```bash
docker exec fatturino php artisan backup:run --disable-notifications
```

To restore a Spatie archive from S3, first stop the instance, validate with `app:restore-backup --dry-run`, then run `app:restore-backup --force --backup-current` from a temporary container. See the Italian guide for the exact commands and archive path variants.

## Build from source

```bash
git clone https://codeberg.org/fatturino/fatturino.git
cd fatturino

docker compose build
APP_KEY=base64:$(openssl rand -base64 32) docker compose up -d
```

The Dockerfile has Composer, Bun, and production stages. Composer installs production PHP dependencies, Bun builds Vite assets, and the production image uses `serversideup/php:8.4-fpm-nginx` with `bcmath`, `intl`, and `gd`. BuildKit caches Composer, Bun, and APT downloads but committed lockfiles remain authoritative.

## Logging, health, and updates

Logs go to `stderr` and can be inspected with Docker:

```bash
docker logs fatturino
docker logs -f fatturino
docker logs --tail 50 fatturino
```

The container health check calls `/up`:

```bash
docker inspect --format='{{.State.Health.Status}}' fatturino
# healthy
```

Update a deployed image with:

```bash
docker compose pull
docker compose up -d
```

Data in `/data` is preserved across updates. Run `docker compose config` before deployment to validate Compose interpolation without starting services.
