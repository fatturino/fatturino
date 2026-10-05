# Guida allo sviluppo e al rilascio

[English version](DEVELOPMENT.en.md)

## Toolchain

Usa PHP 8.2 o superiore, Composer 2, Bun 1.3 o superiore e SQLite, PostgreSQL o MySQL. Bun è il package manager frontend supportato; [`bun.lock`](bun.lock) è autorevole. Non creare un lockfile npm.

## Installazione ed esecuzione

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
bun install --frozen-lockfile
bun run build
```

Per lo sviluppo locale, esegui `composer dev`: avvia contemporaneamente Laravel, un queue listener, Pail e Vite. Non usarlo in produzione. Completa la procedura guidata all'URL locale dell'applicazione.

## Controlli obbligatori

```sh
vendor/bin/pint --test
composer lint-livewire-format
composer test
bun run build
composer audit --locked
bun audit
```

`composer format-livewire` e `vendor/bin/pint` applicano la formattazione. La CI esegue Pint, formattazione Livewire, test, installazione Bun congelata, build frontend e verifica dell'immagine Docker nelle pull request.

## Configurazione

[`.env.example`](.env.example) è il riferimento completo delle variabili. Non committare mai un file ambiente popolato. In produzione sono importanti `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, credenziali database, `SESSION_SECURE_COOKIE=true` dietro HTTPS, credenziali mail, credenziali OpenAPI SDI, credenziali S3 e password backup.

`*_MANAGED_BY_ENV=true` rende la configurazione corrispondente di mail, SDI o backup di proprietà dell'ambiente e ne impedisce la modifica da UI. I deployment cloud devono usare `DOCUMENTS_DISK=s3`; consulta gli [snapshot dei documenti](docs/decisions/2026-10-01-document-snapshot-storage.md).

## Architettura

Laravel 12 e Livewire 4 implementano l'applicazione. I controller validano l'input HTTP e delegano i flussi documentali a classi action e service. PostgreSQL, sessioni, cache e queue predefiniti supportano la topologia Docker AIO. XML fiscali e PDF di cortesia finali sono snapshot immutabili sul disco documenti configurato. I callback SDI sono autenticati bearer, rate limited, limitati nella dimensione del body, validati JSON e processati tramite record di inbound log.

Directory principali: `app/` codice applicativo, `resources/` asset Blade/Livewire, `routes/` entry point HTTP e console, `database/` schema/impostazioni/seeder, `tests/` test Pest, `docker/` bootstrap container e `docs/decisions/` ADR.

## Release

Usa Semantic Versioning. Aggiorna [`VERSION`](VERSION), [`CHANGELOG.md`](CHANGELOG.md) e documentazione della release nella stessa pull request; non aggiornare un file `.env` tracciato. Tagga la release integrata come `vX.Y.Z`. Il workflow GitHub release crea le note e la CI pubblica l'immagine container su GHCR.

Prima del tag, esegui i controlli obbligatori, verifica `composer audit --locked` e `bun audit`, prova il deployment dell'immagine con una configurazione non produttiva e conferma le procedure di backup/restore. Per la prima release pubblica che sostituisce la storia Git ereditata, segui il [runbook di sostituzione della history](docs/release-history-replacement.md): è manuale, distruttivo e non viene eseguito dalla CI.

## Risoluzione problemi

- Esegui `php artisan config:clear` dopo aver modificato `.env` in locale.
- Esegui `php artisan view:clear` se view Livewire compilate stale fanno riferimento a un precedente percorso locale.
- Usa `php artisan migrate:status` prima di diagnosticare errori dipendenti dallo schema.
- Usa `docker compose config` per validare l'interpolazione Compose senza avviare servizi.
- Non eseguire `migrate:fresh`, comandi di restore o di reset database in produzione senza backup testato e piano di manutenzione esplicito.
