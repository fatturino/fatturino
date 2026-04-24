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

> [!WARNING]
> **Active development, not production-ready.** Fatturino is still under active development. APIs, database schema, and features may change without notice. Use for testing and development purposes only.

> [!NOTE]
> **Primary Repository**: The official Fatturino repository is hosted on [Codeberg](https://codeberg.org/fatturino/fatturino). This GitHub repository is an automatically synchronized mirror. For contributing and reporting issues, please use the Codeberg repository.

Fatturino is an open source web application for managing Italian electronic invoicing, fully compliant with the XML standard of the Sistema di Interscambio (SDI). Available as self-hosted or managed Cloud.

> **Italiano?** Leggi il [README in italiano](README.md).

---

## Demo

Try Fatturino without installing anything:

**[demo.fatturino.it](https://demo.fatturino.it)**

Pre-populated data, no registration required, automatic periodic reset.

> **Note**: the demo uses fictitious data. Do not enter real or sensitive data.

---

## Key Features

- **100% SDI Compliance**: generates XML files compliant with the Fattura Elettronica standard
- **Privacy and Control**: your data stays under your control
- **Modern Interface**: Livewire 4, Mary UI, DaisyUI v5 (light/dark mode)
- **Complete Management**: sales and purchase invoices, self-invoices, contacts, VAT rates, sequences
- **Plugin System**: extensible architecture with UI-based activation/deactivation
- **Dashboard and Reports**: revenue overview with fiscal year selector
- **Import/Export**: contact import from CSV, purchase invoice sync from SDI
- **Automatic Backups**: schedule periodic backups to S3 (daily/weekly/monthly) with configurable management
- **Services Page**: centralized management of optional services (extensible for future services)
- **Transparent Pricing**: self-hosted is free (you only pay for hosting), Cloud with a lightweight subscription
- **Guided Setup**: initial configuration wizard

---

## Requirements

- **PHP** 8.2+
- **Composer** 2.x
- **Bun** (or Node.js 18+)
- **Database**: SQLite (dev) / MySQL / PostgreSQL (prod)
- **OpenAPI account**: for invoice submission to SDI ([openapi.it](https://openapi.it))

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
php artisan migrate:fresh --seed     # Reset database
```

---

## Docker

```bash
docker run --rm fatturino php artisan key:generate --show
APP_KEY=base64:xxxxx docker compose up -d
```

Single container with NGINX, PHP-FPM, queue worker and scheduler. SQLite, zero external dependencies.

Full guide: **[docker/README.md](docker/README.md)**

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| **Backend** | Laravel 12 (PHP 8.2+) |
| **UI** | Livewire 4 + Mary UI |
| **Styling** | DaisyUI v5 + Tailwind CSS 4 |
| **Testing** | Pest PHP |
| **XML** | fatturaelettronicaphp/fattura-elettronica |

---

## Documentation

For configuration, architecture, plugin system, SDI integration and advanced guides see the [full documentation](https://fatturino.it/docs).

---

## Contributing

Contributions are welcome!

1. Fork the repository on [Codeberg](https://codeberg.org/fatturino/fatturino)
2. Create a branch for your feature
3. Write tests for new functionality
4. Format code with `vendor/bin/pint`
5. Open a Pull Request

**Bugs and Feature Requests**: [Issues on Codeberg](https://codeberg.org/fatturino/fatturino/issues)

---

## License

[GNU Affero General Public License v3.0 (AGPL-3.0)](LICENSE)

---

**Daniele Lenares** · [daniele.lenares.me](https://daniele.lenares.me)

**Made with ❤️ in Italy** 🇮🇹
