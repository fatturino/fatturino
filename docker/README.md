# Docker

Fatturino viene eseguito in un singolo container Docker grazie a [serversideup/php](https://serversideup.net/open-source/docker-php/) con S6 Overlay per la gestione dei processi.

## Architettura

Un singolo container esegue 4 servizi supervisionati da S6:

```
┌──────────────────────────────────────────────┐
│  fatturino                                   │
│                                              │
│  PostgreSQL                   (database)     │
│  NGINX + PHP-FPM              (web server)   │
│  php artisan queue:work       (queue)        │
│  php artisan schedule:work    (scheduler)    │
│                                              │
│  All'avvio (entrypoint.d):                   │
│    10-setup-data.sh     (struttura + symlink)│
│    12-init-postgresql.sh (bootstrap database)│
│    15-migrate.sh        (migrazioni)         │
│    20-seed-database.sh  (seed primo avvio)   │
│    25-stop-bootstrap-postgresql.sh           │
│                                              │
│  Volume /data ────────────────────────────┐  │
│    database.sqlite                        │  │
│    storage/app/private/                   │  │
│    storage/app/public/                    │  │
│    storage/logs/                          │  │
└───────────────────────────────────────────┘  │
```

PostgreSQL, cache, queue e sessioni sono ospitati nello stesso container. Il database ascolta sulla rete privata del container per consentire l'accesso dall'host o da altri container direttamente collegati alla stessa rete. PostgreSQL non espone la porta sull'host: non aggiungere la porta `5432` a `ports` o a `x-ports`.

Le connessioni TCP alle interfacce private del container sono autorizzate in `pg_hba.conf` e richiedono autenticazione SCRAM, senza dipendere da un CIDR specifico del runtime. La porta PostgreSQL non è comunque pubblicata sull'host: mantieni `5432` fuori da `ports` e `x-ports`.

Durante il bootstrap PostgreSQL viene avviato una sola volta, resta disponibile per migrazioni e seed, poi viene arrestato prima che S6 avvii il servizio permanente. Il cluster in `/data/postgresql` resta sempre di proprietà dell'utente di sistema `postgres`.

## Quick Start

```bash
# 1. Genera la chiave applicazione
docker run --rm fatturino php artisan key:generate --show

# 2. Crea un file .env con chiave e password del database
echo "APP_KEY=base64:xxxxx" > .env
echo "APP_URL=http://localhost:8080" >> .env
echo "DB_PASSWORD=$(openssl rand -base64 32)" >> .env
echo "POSTGRES_SUPERUSER_PASSWORD=$(openssl rand -base64 32)" >> .env

# 3. Avvia
docker compose up -d

# 4. Apri il browser
open http://localhost:8080
```

All'avvio il container esegue automaticamente:

- Creazione della struttura dati persistente su `/data`
- Inizializzazione del cluster PostgreSQL e dell'utente applicativo
- Migrazioni database
- Seed delle aliquote IVA e dei sezionali (solo al primo avvio)
- Ottimizzazione cache Laravel (`AUTORUN_LARAVEL_OPTIMIZE`)

## Configurazione

### Variabili d'ambiente

| Variabile                     | Obbligatoria | Default                    | Descrizione                                                                                                                                                                                           |
| ----------------------------- | :----------: | -------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `APP_KEY`                     |      Si      | -                          | Chiave di crittografia (generata con `key:generate --show`)                                                                                                                                           |
| `APP_URL`                     |      Si      | `http://localhost:8080`    | URL pubblico dell'applicazione                                                                                                                                                                        |
| `APP_PORT`                    |      No      | `8080`                     | Porta esposta sull'host (variabile compose, non passata al container)                                                                                                                                 |
| `APP_NAME`                    |      No      | `Fatturino`                | Nome applicazione                                                                                                                                                                                     |
| `APP_ENV`                     |      No      | `production`               | Ambiente Laravel (`production`, `local`)                                                                                                                                                              |
| `DB_DATABASE`                 |      No      | `fatturino`                | Nome del database PostgreSQL interno                                                                                                                                                                  |
| `DB_USERNAME`                 |      No      | `fatturino`                | Utente applicativo PostgreSQL interno                                                                                                                                                                 |
| `DB_PASSWORD`                 |      Si      | -                          | Password dell'utente applicativo; non modificarla dopo il primo avvio                                                                                                                                 |
| `POSTGRES_SUPERUSER_PASSWORD` |      Si      | -                          | Password del superutente PostgreSQL; non modificarla dopo il primo avvio                                                                                                                              |
| `SSL_MODE`                    |      No      | `off`                      | Modalita SSL del container (`off`, `full`, `flexible`)                                                                                                                                                |
| `PHP_DATE_TIMEZONE`           |      No      | `Europe/Rome`              | Timezone PHP                                                                                                                                                                                          |
| `SMTP_MANAGED_BY_ENV`         |      No      | `false`                    | Se `true`, il provider email e le credenziali sono letti solo da env (UI provider nascosta)                                                                                                           |
| `MAIL_MAILER`                 |      No      | `log`                      | Driver email (`smtp`, `scaleway_tem`, `log`, `sendmail`)                                                                                                                                              |
| `MAIL_HOST`                   |      No      | `127.0.0.1`                | Host SMTP                                                                                                                                                                                             |
| `MAIL_PORT`                   |      No      | `2525`                     | Porta SMTP                                                                                                                                                                                            |
| `MAIL_USERNAME`               |      No      | -                          | Username SMTP                                                                                                                                                                                         |
| `MAIL_PASSWORD`               |      No      | -                          | Password SMTP                                                                                                                                                                                         |
| `MAIL_SCHEME`                 |      No      | -                          | Schema SMTP (es. `tls`)                                                                                                                                                                               |
| `MAIL_EHLO_DOMAIN`            |      No      | dominio da `APP_URL`       | Dominio EHLO per SMTP                                                                                                                                                                                 |
| `MAIL_FROM_ADDRESS`           |      No      | `hello@example.com`        | Indirizzo mittente di default                                                                                                                                                                         |
| `MAIL_FROM_NAME`              |      No      | `Fatturino`                | Nome mittente di default                                                                                                                                                                              |
| `SCALEWAY_TEM_REGION`         |      No      | `fr-par`                   | Regione Scaleway TEM                                                                                                                                                                                  |
| `SCALEWAY_TEM_PROJECT_ID`     |      No      | -                          | Project ID Scaleway TEM                                                                                                                                                                               |
| `SCALEWAY_TEM_SECRET_KEY`     |      No      | -                          | Secret key Scaleway con permessi TEM                                                                                                                                                                  |
| `APP_INSTANCE_ID`             |      No      | fallback a `APP_NAME`      | Identificativo stabile dell'istanza per namespace telemetry multi-tenant                                                                                                                              |
| `BACKUP_MANAGED_BY_ENV`       |      No      | `false`                    | Se `true`, UI e scheduler backup disabilitati. Le credenziali S3 vanno impostate via `AWS_*` env (modalita managed). Se `false` (default), la configurazione S3 si fa da UI in Impostazioni > Servizi |
| `POSTHOG_FRONTEND_KEY`        |      No      | -                          | API key PostHog browser letta a runtime dal layout Livewire autenticato                                                                                                                               |
| `POSTHOG_FRONTEND_HOST`       |      No      | fallback a `POSTHOG_HOST`  | `api_host` PostHog frontend                                                                                                                                                                           |
| `POSTHOG_UI_HOST`             |      No      | `https://eu.posthog.com`   | `ui_host` PostHog frontend per link corretti quando si usa un proxy                                                                                                                                   |
| `POSTHOG_API_KEY`             |      No      | -                          | API key PostHog backend. Se vuota, SDK PHP non inizializzato                                                                                                                                          |
| `POSTHOG_HOST`                |      No      | `https://eu.i.posthog.com` | Endpoint PostHog backend                                                                                                                                                                              |
| `AWS_ACCESS_KEY_ID`           |      No      | -                          | Access key S3 (solo se `BACKUP_MANAGED_BY_ENV=true`)                                                                                                                                                  |
| `AWS_SECRET_ACCESS_KEY`       |      No      | -                          | Secret key S3 (solo se `BACKUP_MANAGED_BY_ENV=true`)                                                                                                                                                  |
| `AWS_DEFAULT_REGION`          |      No      | `us-east-1`                | Regione S3 (solo se `BACKUP_MANAGED_BY_ENV=true`)                                                                                                                                                     |
| `AWS_BUCKET`                  |      No      | -                          | Bucket S3 (solo se `BACKUP_MANAGED_BY_ENV=true`)                                                                                                                                                      |
| `AWS_USE_PATH_STYLE_ENDPOINT` |      No      | `false`                    | Path-style endpoint per S3 compatibili (solo se `BACKUP_MANAGED_BY_ENV=true`)                                                                                                                         |

### Esempio docker-compose.yml completo

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

Per usare Scaleway TEM via API al posto di SMTP:

```yaml
environment:
    SMTP_MANAGED_BY_ENV: "true"
    MAIL_MAILER: "scaleway_tem"
    MAIL_FROM_ADDRESS: "fatture@example.com"
    MAIL_FROM_NAME: "Fatturino"
    SCALEWAY_TEM_REGION: "fr-par"
    SCALEWAY_TEM_PROJECT_ID: "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
    SCALEWAY_TEM_SECRET_KEY: "scw_secret_xxx"
```

> Usa `latest-stable` in produzione. Il tag `latest` e' rolling dall'ultimo push su `main` (development/staging). I tag `vX.Y.Z` sono rilasci immutabili.

## Volume /data

> **Migration notice:** document artifacts can now live on the dedicated
> `documents` filesystem disk. Cloud deployments must use
> `DOCUMENTS_DISK=s3` and `DOCUMENTS_NAME=<tenant-prefix>`. The legacy
> `/data/storage` layout below remains only for self-hosted local storage and
> for the non-destructive migration window; it is not removed automatically.
>
> When migrating existing local snapshots to S3, first run
> `php artisan documents:check-storage --write`, then inspect
> `php artisan documents:backfill-storage --dry-run`, and finally run
> `php artisan documents:backfill-storage`. With the S3 document disk enabled,
> the command copies local XML/PDF snapshots and regenerates missing outbound
> XML snapshots for sales invoices, credit notes, and self-invoices. It never
> regenerates received purchase XML because the received original is canonical.

Tutti i dati persistenti vivono in un unico volume Docker montato su `/data`:

```
/data/
├── postgresql/                  # Cluster PostgreSQL, proprietario postgres:postgres
├── database.sqlite              # Solo sorgente legacy per migrazione SQLite -> PostgreSQL
├── .seeded                      # Flag primo avvio completato
└── storage/
    ├── app/
    │   ├── private/
    │   │   ├── imports/         # File importati (XML, CSV)
    │   │   └── documents/
    │   │       ├── xml/
    │   │       │   ├── sales/          # XML fatture di vendita
    │   │       │   ├── purchase/       # XML fatture di acquisto
    │   │       │   ├── credit-notes/   # XML note di credito
    │   │       │   └── self-invoices/  # XML autofatture
    │   │       └── pdf/
    │   │           ├── sales/          # PDF fatture di vendita
    │   │           └── credit-notes/   # PDF note di credito
    │   └── public/              # Upload pubblici (logo, asset)
    └── logs/
        └── laravel.log          # Log applicazione
```

## Backup e Restore

### Backup

```bash
# Backup completo (database + file + log)
docker run --rm \
  -v fatturino-data:/data \
  -v $(pwd):/backup \
  alpine tar czf /backup/fatturino-$(date +%Y%m%d).tar.gz -C / data
```

### Restore

```bash
# Ferma il container
docker compose down

# Cancella il volume esistente
docker volume rm fatturino-data

# Ricrea il volume e ripristina il backup
docker volume create fatturino-data
docker run --rm \
  -v fatturino-data:/data \
  -v $(pwd):/backup \
  alpine tar xzf /backup/fatturino-20260323.tar.gz -C /

# Riavvia
docker compose up -d
```

### Backup solo database

```bash
docker exec fatturino pg_dump --username=fatturino --format=custom --file=/data/fatturino.dump fatturino
docker cp fatturino:/data/fatturino.dump ./fatturino-db-$(date +%Y%m%d).dump
docker exec fatturino rm /data/fatturino.dump
```

### Backup automatico su S3 (Spatie)

Fatturino integra `spatie/laravel-backup` per backup pianificati con destinazione **S3** (o compatibili: MinIO, Cloudflare R2, Wasabi, Backblaze B2). La destinazione e' fissa (disco `s3` in `config/backup.php`).

Due modalita di configurazione:

#### Self-hosted (`BACKUP_MANAGED_BY_ENV=false`, default)

La configurazione si fa da UI in **Impostazioni > Servizi**:

1. Abilita il backup, scegli frequenza (`daily`, `weekly`, `monthly`) e orario.
2. Inserisci le credenziali S3: Access Key, Secret, bucket, regione, endpoint (opzionale per S3 compatibili).
3. Salva. Le credenziali vengono salvate nel database e iniettate nella configurazione filesystem a runtime.

Lo scheduler interno esegue:

- `backup:run` con la frequenza scelta (all'orario configurato)
- `backup:clean` ogni notte alle 03:30

#### Managed (`BACKUP_MANAGED_BY_ENV=true`)

Per ambienti hosting dove i backup sono orchestrati esternamente:

- La UI di backup e' nascosta
- Lo scheduler di backup e' disabilitato
- Le credenziali S3 vanno impostate tramite le variabili d'ambiente `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`

#### Contenuto del backup

- `db-dumps/` (dump PostgreSQL)
- `storage/app/private/documents/` (XML e PDF fatture, organizzati per tipo)
- `storage/app/public/` (logo, asset utente)

When `DOCUMENTS_DISK=s3`, XML/PDF snapshots are authoritative under
`{DOCUMENTS_NAME}/...` in the configured bucket and are deliberately excluded
from the archive. The same bucket may store backups under `{BACKUP_NAME}/...`.

#### Pulizia automatica

`backup:clean` applica la strategia di retention predefinita:

- 7 giorni: tutti i backup
- 16 giorni: backup giornalieri
- 8 settimane: backup settimanali
- 4 mesi: backup mensili
- 2 anni: backup annuali
- Limite massimo: 5000 MB totali

#### Esecuzione manuale

```bash
docker exec fatturino php artisan backup:run --disable-notifications
```

#### Restore da archivio S3

```bash
# 1. Ferma l'istanza per bloccare web, queue worker e scheduler.
docker compose down

# 2. Avvia temporaneamente l'immagine sul volume esistente, senza i processi S6,
#    e valida l'archivio prima di applicare modifiche.
docker compose run --rm --no-deps --entrypoint php app \
  artisan app:restore-backup --s3-key=Fatturino/2026-04-29-03-00-00.zip --dry-run

# 3. Ripristina database e file. Il comando entra in maintenance mode e, con
#    --backup-current, conserva uno snapshot dello stato corrente prima del restore.
docker compose run --rm --no-deps --entrypoint php app \
  artisan app:restore-backup --s3-key=Fatturino/2026-04-29-03-00-00.zip --force --backup-current

# 4. Riavvia l'istanza normalmente.
docker compose up -d
```

> Il restore PostgreSQL e' distruttivo. Eseguilo solo con l'istanza fermata e dopo una prova su staging. Per un archivio locale, sostituisci `--s3-key=...` con `--file=/percorso/backup.zip` accessibile al container temporaneo.

## Build da sorgente

```bash
git clone https://codeberg.org/fatturino/fatturino.git
cd fatturino

docker compose build
APP_KEY=base64:$(openssl rand -base64 32) docker compose up -d
```

Il Dockerfile usa un build multi-stage:

1. **Stage composer**: `composer:2` installa una sola volta le dipendenze PHP di produzione, poi lo stage runtime riusa `vendor/`
2. **Stage frontend**: `oven/bun:1` compila gli asset CSS/JS con Vite
3. **Stage production**: `serversideup/php:8.4-fpm-nginx` con l'applicazione Laravel e le estensioni `bcmath`, `intl`, `gd`

BuildKit conserva cache separate per Composer, Bun e APT. In GitHub Actions la cache GHA `fatturino-production` viene importata sia dalla verifica amd64 delle pull request sia dalla pubblicazione multi-arch di `main` e dei tag; soltanto le build pubblicate la aggiornano. Lockfile e build riproducibili (`composer install` e `bun install --frozen-lockfile`) restano la fonte di verità, quindi una cache accelera i download ma non modifica le dipendenze risolte.

## Logging

Di default i log vengono inviati a `stderr` (accessibili con `docker logs`):

```bash
docker logs fatturino           # Tutti i log
docker logs fatturino -f        # Follow in tempo reale
docker logs fatturino --tail 50 # Ultime 50 righe
```

In sviluppo locale puoi aumentare il dettaglio:

```yaml
environment:
    APP_ENV: "local"
    APP_DEBUG: "true"
    LOG_LEVEL: "debug"
```

## Health Check

Il container include un health check automatico sull'endpoint `/up`:

```bash
docker inspect --format='{{.State.Health.Status}}' fatturino
# healthy
```

## Aggiornamento

```bash
# Pull nuova immagine
docker compose pull

# Riavvia (migrazioni automatiche all'avvio)
docker compose up -d
```

I dati nel volume `/data` sono preservati tra gli aggiornamenti.
