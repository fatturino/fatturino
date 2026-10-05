# Contributing to Fatturino

[Versione italiana](CONTRIBUTING.md)

Thank you for improving Fatturino. This project handles Italian electronic-invoicing data; correctness, security, privacy and backward compatibility take priority over speed.

## Before opening an issue

- Search existing issues and pull requests.
- Do not include invoices, tax codes, VAT numbers, customer data, API tokens, credentials, database dumps or logs containing production data.
- Report vulnerabilities privately under [SECURITY.en.md](SECURITY.en.md), not in a public issue.

## Local setup

Use PHP 8.2 or newer, Composer 2, Bun 1.3 or newer, and SQLite, PostgreSQL, or MySQL. Bun is the only supported frontend package manager.

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
bun install --frozen-lockfile
bun run build
```

See [README.en.md](README.en.md) and [DEVELOPMENT.en.md](DEVELOPMENT.en.md) for configuration and release details.

## Contribution workflow

1. Create a branch from `main` using a concise, descriptive name.
2. Keep a pull request focused on one behavior or maintenance concern.
3. Add or adjust tests for observable behavior, including failure cases for security-sensitive paths.
4. Keep PHP formatted with Pint and preserve the Livewire single-file-component formatting.
5. Update user, operator, or architecture documentation when the behavior or a durable decision changes.
6. Complete the pull-request template accurately.

Run the required local checks before requesting review:

```sh
vendor/bin/pint --test
composer lint-livewire-format
composer test
bun run build
composer audit --locked
bun audit
```

## Code and review expectations

- Preserve existing public routes, integrations, document formats, and persisted data unless the pull request explicitly documents a compatible migration path.
- Validate external input, authorize state-changing operations, and never return provider exception messages, secrets, or personal data to browsers.
- Use parameterized Eloquent/query-builder APIs. Do not build SQL from request input.
- Do not add dependencies, schema changes, or architectural layers without explaining the need and alternatives in the pull request.
- Do not commit generated assets, `vendor/`, `node_modules/`, local configuration, database files, backups, or test results.
- Keep comments in English and explain constraints or intent rather than repeating the code.

## Documentation and decision records

Architecture Decision Records (ADRs) live in [docs/decisions](docs/decisions). Create an ADR when a change establishes or reverses a durable architectural, infrastructure, storage, security-boundary, or deployment decision. Use the existing `Context`, `Decision`, `Rationale`, and `Consequences` structure, and mark superseded records clearly.

## License

By contributing, you agree that your contribution is licensed under the repository's [AGPL-3.0-or-later license](LICENSE.md).
