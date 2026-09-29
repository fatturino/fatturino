# PostgreSQL single-container AIO

## Context

Fatturino is deployed as a single container containing web, queue worker and scheduler. Production instances currently use small SQLite databases and accept a controlled outage for migration.

## Decision

The AIO image also runs PostgreSQL under S6. PostgreSQL stores its cluster in `/data/postgresql`, while application files remain under `/data/storage`. The application uses PostgreSQL as its default connection. Legacy SQLite remains read-only at `/data/database.sqlite` until the migration is verified.

Migration is offline per instance: entrypoint migrations run first, then a preflight, idempotent primary-key import, verification and a completion marker execute before S6 starts web, workers or scheduler.

## Consequences

- The container is a single failure domain. PostgreSQL is not independently scalable or upgradeable.
- PostgreSQL backup and restore verification are mandatory before production cutover.
- `DB_PASSWORD` and `POSTGRES_SUPERUSER_PASSWORD` are required deployment secrets and must not enter images, Git, logs or reports.
- Before PostgreSQL accepts its first write, rollback restores the prior image and untouched SQLite volume. Afterwards a rollback requires a new controlled reverse migration or recovery from verified backup.
