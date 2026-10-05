# PostgreSQL single-container AIO

Status: Accepted

## Context

Fatturino is deployed as a single container containing web, queue worker, scheduler, and PostgreSQL.

## Decision

The AIO image runs PostgreSQL under S6. PostgreSQL stores its cluster at `/data/postgresql`; application storage is at `/data/storage`; the application defaults to PostgreSQL. Entrypoint migrations run before web, workers, or scheduler start. `RESTORE_BACKUP_S3_KEY` performs an explicit cold S3 restore before migrations on a fresh volume.

## Rationale

The topology offers a simple self-hosted deployment while retaining a persistent PostgreSQL database and controlled recovery path.

## Consequences

- The container is a single failure domain and PostgreSQL is not independently scalable or upgradeable.
- Backup and restore verification are mandatory before production recovery.
- `DB_PASSWORD` and `POSTGRES_SUPERUSER_PASSWORD` are deployment secrets and must not enter images, Git, logs, or reports.
- A failed restore blocks application startup; its completion marker is written only after success.
