# SQLite to PostgreSQL AIO migration runbook

## Preconditions

1. Use a staging copy of each production volume first.
2. Record image digest, volume name, database SHA-256, free space and planned maintenance window.
3. Verify a complete backup containing `/data/database.sqlite`, `/data/storage` and deployment environment variables. Restore that backup into an isolated volume and run `sqlite3 database.sqlite 'PRAGMA integrity_check'`.
4. Set unique `DB_PASSWORD` and `POSTGRES_SUPERUSER_PASSWORD` through the deployment secret mechanism.

## Rehearsal

1. Start the target image against a copy of the volume.
2. Inspect generated reports in `storage/app/migration/`: preflight, import and verification must succeed.
3. Check web health, login, invoice search, create/update transaction, payment transaction, SDI queue processing and scheduled work.
4. Compare response times for document list, contact search and dashboard aggregates against the baseline.

### Local evidence and remaining gate

The repository SQLite fixture was rehearsed successfully against an isolated PostgreSQL database: migrations, preflight, import, canonical hashes, foreign-key checks and sequence checks passed. This is not evidence for either production volume.

The PostgreSQL direct-restore path remains a release gate for the actual AIO image. The local Lerd client wrappers cannot be executed by Symfony Process, so the restore command must be rehearsed after building the AIO image, using a disposable volume and a real `backup:run` archive. Confirm that `pg_dump` snapshots the current state, `psql` restores successfully, storage files are restored, and the documented recovery checks pass.

## Production cutover, one instance at a time

1. Put the instance in maintenance and stop its container. Confirm no web, worker or scheduler process can write.
2. Create and verify the final immutable SQLite-plus-files backup.
3. Deploy the new image with PostgreSQL secrets.
4. Startup runs Laravel migrations, `database:migration-preflight`, `database:import-sqlite-to-postgres --force` and `database:verify-sqlite-postgres` before application services start. The import uses primary-key upserts, so a restart resumes a partial initial import without duplicating source rows; it writes `/data/.sqlite-migration-complete` only after verification succeeds.
5. A failed check is a hard stop. Keep maintenance enabled, collect logs and restore the prior image/SQLite volume.
6. On success, inspect the reports, validate `/up`, log in, exercise critical invoice/payment/SDI flows and observe logs before ending maintenance.
7. Retain the SQLite source and final backup according to retention policy after the completion marker exists.

## Cold S3 restore

Use this flow to restore a new AIO volume without web, workers or the scheduler running.

1. Confirm the S3 backup key and its integrity. The configured AWS credentials must have read access only to that object and its bucket.
2. Deploy with `RESTORE_BACKUP_S3_KEY` set to the full, exact S3 object key. The entrypoint does not prepend `BACKUP_NAME` and restores without invoking cache-dependent Artisan maintenance or optimization commands before the PostgreSQL schema exists.
3. The archive must contain exactly one database representation: a `database.sqlite` file or `db-dumps/*.sql` / `*.sql.gz`. SQLite files and SQLite SQL dumps are validated and restored to `/data/database.sqlite`; PostgreSQL SQL dumps are restored to internal PostgreSQL.
4. The entrypoint restores storage and writes `/data/.cold-restore-complete` with the backup-key fingerprint and database type only after the complete restore succeeds. Reusing the same key is idempotent; a different key requires an explicit recovery decision and marker removal.
5. A SQLite backup restored while `DB_CONNECTION=pgsql` automatically runs the normal SQLite-to-PostgreSQL cutover. A SQLite backup restored while `DB_CONNECTION=sqlite` remains a SQLite-only restore. There is no PostgreSQL-to-SQLite migration.
6. An existing legacy SQLite source is migrated automatically when PostgreSQL is the configured runtime database. Any failed restore or migration exits the container before application services start. Remove `RESTORE_BACKUP_S3_KEY` after success.

## Success criteria

- SQLite integrity, foreign keys, UTF-8 encoding and expected tables pass before import.
- All table counts and canonical SHA-256 hashes match.
- No orphan fiscal document lines, malformed JSON or sequence values behind their table maximum exist.
- Application health and critical functional smoke tests pass.

## Optional document dates and empty form values

PostgreSQL rejects `''` for a `date` column because an empty string is not `NULL` and cannot be parsed as a date. SQLite's permissive type affinity can preserve that invalid application value, which may hide the defect before migration.

Before accepting production writes, verify the effective schema:

```sql
SELECT is_nullable, data_type
FROM information_schema.columns
WHERE table_schema = 'public'
  AND table_name = 'fiscal_documents'
  AND column_name = 'due_date';
```

The result must be `YES` and `date`. The application converts empty optional document dates to `NULL` before validation and again in the shared document mutation service. Keep input rules as `nullable|date`; an empty date must persist as `NULL`, a valid ISO date must be preserved, and an invalid non-empty value must fail validation. Eloquent's `date:Y-m-d` casts format valid values but do not sanitize invalid input.

Optional text fields such as `bank_name` and `bank_iban` are distinct from typed date fields. PostgreSQL accepts empty text, but the application stores blank optional values as `NULL` for consistent semantics. Never work around this issue by editing generated Eloquent SQL.

## Rollback

Before PostgreSQL receives business writes, stop the new container and restart the prior SQLite image with the preserved volume. After writes are accepted on PostgreSQL, do not restart SQLite: preserve evidence and use a separately approved recovery plan.

## PostgreSQL backup restore

`app:restore-backup --file=/path/to/backup.zip --force --backup-current` supports PostgreSQL dumps produced by the configured backup job. It first snapshots the current database and persisted files, then replaces the `public` schema and imports the dump with `psql` configured to stop on the first error.

This is a direct, destructive restore: PostgreSQL does not use a staging database. Run it only while the application is in maintenance mode, with workers and scheduler stopped, and only after validating the archive with `--dry-run`. Retain the snapshot created by `--backup-current` until the functional recovery checks are complete.

The AIO image must provide matching `psql` and `pg_dump` clients on `PATH`. `POSTGRES_BIN_PATH` may override that directory only when the deployment uses a non-standard client location.
