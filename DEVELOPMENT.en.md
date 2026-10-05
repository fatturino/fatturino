# Development and release guide

[Versione italiana](DEVELOPMENT.md)

## Toolchain

Use PHP 8.2 or newer, Composer 2, Bun 1.3 or newer, and SQLite, PostgreSQL, or MySQL. Bun is the supported frontend package manager; [`bun.lock`](bun.lock) is authoritative. Do not create an npm lockfile.

## Install and run

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
bun install --frozen-lockfile
bun run build
```

For local development, run `composer dev`; it starts Laravel, a queue listener, Pail, and Vite concurrently. Do not use it for production. Complete the setup wizard at the local application URL.

## Required checks

```sh
vendor/bin/pint --test
composer lint-livewire-format
composer test
bun run build
composer audit --locked
bun audit
```

`composer format-livewire` and `vendor/bin/pint` apply formatting. CI runs Pint, Livewire formatting, tests, a frozen Bun install, the frontend build, and Docker image verification on pull requests.

## Configuration

[`.env.example`](.env.example) is the complete variable reference. Never commit a populated environment file. Important production values are `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, database credentials, `SESSION_SECURE_COOKIE=true` behind HTTPS, mail credentials, OpenAPI SDI credentials, S3 credentials, and backup password.

`*_MANAGED_BY_ENV=true` makes the related mail, SDI, or backup configuration environment-owned and prevents UI editing. Cloud deployments must use `DOCUMENTS_DISK=s3`; see [document snapshots](docs/decisions/2026-10-01-document-snapshot-storage.md).

## Architecture

Laravel 12 and Livewire 4 implement the application. Controllers validate HTTP input and delegate document workflows to action and service classes. PostgreSQL, sessions, cache, and queue defaults support the Docker AIO topology. Fiscal document XML and final courtesy PDFs are immutable snapshots on the configured documents disk. SDI callbacks are bearer-authenticated, rate limited, body-limited, JSON-validated, and processed through inbound-log records.

Primary directories: `app/` application code, `resources/` Blade/Livewire assets, `routes/` HTTP and console entry points, `database/` schema/settings/seeders, `tests/` Pest tests, `docker/` container bootstrap, and `docs/decisions/` ADRs.

## Releases

Use Semantic Versioning. Update [`VERSION`](VERSION), [`CHANGELOG.en.md`](CHANGELOG.en.md), and release documentation in one pull request; do not update a tracked `.env` file. Tag the merged release as `vX.Y.Z`. The GitHub release workflow creates release notes and CI publishes the container image to GHCR.

Before tagging, run the required checks, review `composer audit --locked` and `bun audit`, verify image deployment with a non-production configuration, and confirm backup/restore procedures. For the one-time public release that replaces inherited Git history, follow [the history replacement runbook](docs/release-history-replacement.md). It is a manual, destructive operation and is not executed by CI.

## Troubleshooting

- Run `php artisan config:clear` after changing `.env` locally.
- Run `php artisan view:clear` if stale compiled Livewire views refer to a moved local path.
- Use `php artisan migrate:status` before diagnosing schema-dependent failures.
- Run `docker compose config` to validate Compose interpolation without starting services.
- Do not run `migrate:fresh`, restore commands, or database-reset commands against production without a tested backup and explicit maintenance plan.
