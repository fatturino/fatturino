<p align="center">
  <img src="public/brand/logo-dark.svg" alt="Fatturino" width="280">
</p>

<p align="center">
  <strong>Open Source Electronic Invoicing</strong>
</p>

<p align="center">
  <a href="https://www.gnu.org/licenses/agpl-3.0"><img src="https://img.shields.io/badge/License-AGPL%20v3-blue.svg" alt="License: AGPL v3"></a>
  <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel" alt="Laravel"></a>
  <a href="https://livewire.laravel.com"><img src="https://img.shields.io/badge/Livewire-4-FB70A9?logo=livewire" alt="Livewire"></a>
  <a href="https://php.net"><img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php" alt="PHP"></a>
</p>

Fatturino is an open source web application for managing Italian electronic invoicing: fiscal documents, Fattura Elettronica XML, SDI submission and reception through the integrated OpenAPI provider, payments, contacts, backups, and document snapshots. It is available as self-hosted or managed Cloud.

> **Italiano?** Leggi il [README in italiano](README.md).

---

## Demo

Try Fatturino without installing anything:

**[demo.fatturino.it](https://demo.fatturino.it)**

Pre-populated data, no registration required, automatic periodic reset.

> **Note**: the demo uses fictitious data. Do not enter real or sensitive data.

---

## Key Features

- **Fiscal documents**: sales invoices, self-invoices, credit notes, pro formas, and configurable sequences.
- **Purchase invoices**: SDI-synchronized purchase-invoice viewing and editing.
- **XML and PDF**: XML generation, local validation, download, and courtesy PDFs for supported documents.
- **SDI workflows**: document submission, callback reception, and integrated OpenAPI provider settings.
- **Payments and reminders**: payment tracking and manually initiated, server-validated payment reminders.
- **Contacts and company data**: customer/supplier records, company fiscal data, VAT settings, and email templates.
- **Imports**: Fattura24 contact CSV import and purchase-invoice XML import.
- **Snapshots and backups**: immutable XML/PDF snapshots plus scheduled S3-compatible backups.
- **Guided setup**: initial instance configuration wizard.

---

## Requirements

- **PHP** 8.2+
- **Composer** 2.x
- **Bun** 1.3+ for frontend assets; `bun.lock` is authoritative
- **Database**: SQLite, MySQL, or PostgreSQL
- **OpenAPI account** (optional): required for SDI submission and reception through the integrated provider

---

## Quick Start

```bash
git clone https://codeberg.org/fatturino/fatturino.git
cd fatturino

# Full setup (dependencies, .env, key, migrations, build)
composer setup

# Start the development server
composer dev
```

Open [localhost:8000](http://localhost:8000) and follow the setup wizard.

### Manual Installation

```bash
composer install
bun install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
bun run build
php artisan serve
```

---

## Development Commands

```bash
composer dev                         # Server + queue + logs + Vite (HMR)
composer test                        # All tests (Pest)
vendor/bin/pint                      # Code formatting (Laravel Pint)
vendor/bin/pint --test               # PHP style check without changes
composer lint-livewire-format        # Livewire component formatting check
composer audit --locked              # PHP dependency advisory check
bun audit                            # Bun dependency advisory check
php artisan migrate:fresh --seed     # Reset database
```

Do not run `migrate:fresh` against data that must be retained. Do not create or update `package-lock.json`; Bun is the supported frontend package manager.

---

## Docker

```bash
docker run --rm fatturino php artisan key:generate --show
APP_KEY=base64:xxxxx docker compose up -d
```

The AIO image contains NGINX, PHP-FPM, queue worker, scheduler, and PostgreSQL. It is a single failure domain; test backups and restores before production use.

Full guide: **[docker/README.en.md](docker/README.en.md)**

---

## Tech Stack

| Layer       | Technology                                |
| ----------- | ----------------------------------------- |
| **Backend** | Laravel 12 (PHP 8.2+)                     |
| **UI**      | Livewire 4 + Blade components             |
| **Styling** | Tailwind CSS 4 + Tailkit                  |
| **Testing** | Pest PHP                                  |
| **XML**     | fatturaelettronicaphp/fattura-elettronica |

---

## Documentation

- Development, release, configuration, architecture, and troubleshooting: [DEVELOPMENT.en.md](DEVELOPMENT.en.md)
- Container deployment: [docker/README.en.md](docker/README.en.md)
- Architecture Decision Records: [docs/decisions](docs/decisions)
- Security reporting and operator guidance: [SECURITY.en.md](SECURITY.en.md)
- User and operational documentation: [fatturino.it/docs](https://fatturino.it/docs)

### Architecture and limitations

Laravel 12 and Livewire 4 power the application. HTTP controllers validate input and delegate fiscal workflows to actions and services. Final XML and PDFs are immutable snapshots on the configured `documents` disk. Cloud deployments must use `DOCUMENTS_DISK=s3` with least-privilege credentials.

Fatturino does not replace tax advice, legally compliant digital preservation, infrastructure monitoring, tested backups, or legal review of an instance configuration.

---

## Contributing

Read [CONTRIBUTING.en.md](CONTRIBUTING.en.md) and [CODE_OF_CONDUCT.en.md](CODE_OF_CONDUCT.en.md), then:

1. Fork the repository on [Codeberg](https://codeberg.org/fatturino/fatturino).
2. Create a focused branch.
3. Add or update tests.
4. Run the checks in [DEVELOPMENT.en.md](DEVELOPMENT.en.md).
5. Open a pull request using the template.

**Bugs and Feature Requests**: [Issues on Codeberg](https://codeberg.org/fatturino/fatturino/issues)

Do not publish fiscal documents, customer data, backups, production logs, keys, or tokens. Report vulnerabilities privately according to [SECURITY.en.md](SECURITY.en.md).

---

## License

[GNU Affero General Public License v3.0 (AGPL-3.0)](LICENSE.md)

---

**Daniele Lenares** · [daniele.lenares.me](https://daniele.lenares.me)

**Made in Italy** 🇮🇹
