# Docker

Fatturino viene eseguito in un singolo container Docker grazie a [serversideup/php](https://serversideup.net/open-source/docker-php/) con S6 Overlay per la gestione dei processi.

## Architettura

Un singolo container esegue 3 servizi supervisionati da S6:

```
┌──────────────────────────────────────────────┐
│  fatturino                                   │
│                                              │
│  NGINX + PHP-FPM              (web server)   │
│  php artisan queue:work       (queue)        │
│  php artisan schedule:work    (scheduler)    │
│                                              │
│  All'avvio (entrypoint.d):                   │
│    10-setup-data.sh     (struttura + symlink)│
│    15-migrate.sh        (migrazioni)         │
│    17-install-plugins.sh(plugin da env)      │
│    20-seed-database.sh  (seed primo avvio)   │
│                                              │
│  Volume /data ────────────────────────────┐  │
│    database.sqlite                        │  │
│    storage/app/private/                   │  │
│    storage/app/public/                    │  │
│    storage/logs/                          │  │
└───────────────────────────────────────────┘  │
```

Tutto gira su SQLite: database, cache, queue, sessioni. Zero dipendenze esterne.

I plugin SDI (es. OpenAPI, Aruba) e altri plugin si installano tramite la variabile `FATTURINO_PLUGINS` e vengono clonati da Codeberg all'avvio del container.

## Quick Start

```bash
# 1. Genera la chiave applicazione
docker run --rm fatturino php artisan key:generate --show

# 2. Crea un file .env con la chiave generata
echo "APP_KEY=base64:xxxxx" > .env
echo "APP_URL=http://localhost:8080" >> .env

# 3. Avvia
docker compose up -d

# 4. Apri il browser
open http://localhost:8080
```

All'avvio il container esegue automaticamente:
- Creazione della struttura dati su `/data` (cartelle, symlink, WAL mode su SQLite)
- Migrazioni database (prima dei plugin)
- Installazione plugin definiti in `FATTURINO_PLUGINS` (clone da Codeberg, rebuild asset)
- Seconda migrazione per includere le tabelle dei plugin
- Seed delle aliquote IVA e dei sezionali (solo al primo avvio)
- Ottimizzazione cache Laravel (`AUTORUN_LARAVEL_OPTIMIZE`)

## Configurazione

### Variabili d'ambiente

| Variabile | Obbligatoria | Default | Descrizione |
|-----------|:---:|---------|-------------|
| `APP_KEY` | Si | - | Chiave di crittografia (generata con `key:generate --show`) |
| `APP_URL` | Si | `http://localhost:8080` | URL pubblico dell'applicazione |
| `APP_PORT` | No | `8080` | Porta esposta sull'host (variabile compose, non passata al container) |
| `APP_NAME` | No | `Fatturino` | Nome applicazione |
| `APP_ENV` | No | `production` | Ambiente Laravel (`production`, `local`) |
| `SSL_MODE` | No | `off` | Modalita SSL del container (`off`, `full`, `flexible`) |
| `PHP_DATE_TIMEZONE` | No | `Europe/Rome` | Timezone PHP |
| `FATTURINO_PLUGINS` | No | - | Plugin da installare all'avvio (nomi separati da spazio, es. `plugin-cloud`) |
| `CODEBERG_TOKEN` | No | - | Token Codeberg per clonare repository privati di plugin |
| `SMTP_MANAGED_BY_ENV` | No | `false` | Se `true`, le credenziali SMTP sono lette solo da env (UI SMTP nascosta) |
| `MAIL_MAILER` | No | `log` | Driver email (`smtp`, `log`, `sendmail`) |
| `MAIL_HOST` | No | `127.0.0.1` | Host SMTP |
| `MAIL_PORT` | No | `2525` | Porta SMTP |
| `MAIL_USERNAME` | No | - | Username SMTP |
| `MAIL_PASSWORD` | No | - | Password SMTP |
| `MAIL_SCHEME` | No | - | Schema SMTP (es. `tls`) |
| `MAIL_EHLO_DOMAIN` | No | dominio da `APP_URL` | Dominio EHLO per SMTP |
| `MAIL_FROM_ADDRESS` | No | `hello@example.com` | Indirizzo mittente di default |
| `MAIL_FROM_NAME` | No | `Fatturino` | Nome mittente di default |
| `BACKUP_MANAGED_BY_ENV` | No | `false` | Se `true`, UI e scheduler backup disabilitati. Le credenziali S3 vanno impostate via `AWS_*` env (modalita managed). Se `false` (default), la configurazione S3 si fa da UI in Impostazioni > Servizi |
| `MONITORING_MANAGED_BY_ENV` | No | `false` | Se `true`, la UI monitoring e' nascosta e si usa `SENTRY_LARAVEL_DSN` da env |
| `SENTRY_LARAVEL_DSN` | No | - | DSN Sentry per error tracking |
| `AWS_ACCESS_KEY_ID` | No | - | Access key S3 (solo se `BACKUP_MANAGED_BY_ENV=true`) |
| `AWS_SECRET_ACCESS_KEY` | No | - | Secret key S3 (solo se `BACKUP_MANAGED_BY_ENV=true`) |
| `AWS_DEFAULT_REGION` | No | `us-east-1` | Regione S3 (solo se `BACKUP_MANAGED_BY_ENV=true`) |
| `AWS_BUCKET` | No | - | Bucket S3 (solo se `BACKUP_MANAGED_BY_ENV=true`) |
| `AWS_USE_PATH_STYLE_ENDPOINT` | No | `false` | Path-style endpoint per S3 compatibili (solo se `BACKUP_MANAGED_BY_ENV=true`) |

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
      FATTURINO_PLUGINS: "plugin-fe-openapi"
      CODEBERG_TOKEN: "your-codeberg-token"
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

> Usa `latest-stable` in produzione. Il tag `latest` e' rolling dall'ultimo push su `main` (development/staging). I tag `vX.Y.Z` sono rilasci immutabili.

## Volume /data

Tutti i dati persistenti vivono in un unico volume Docker montato su `/data`:

```
/data/
├── database.sqlite              # Database completo (WAL mode)
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
docker exec fatturino sqlite3 /data/database.sqlite ".backup /data/backup.sqlite"
docker cp fatturino:/data/backup.sqlite ./fatturino-db-$(date +%Y%m%d).sqlite
docker exec fatturino rm /data/backup.sqlite
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

- `db-dumps/database.sql.gz` (dump SQLite compresso con Gzip)
- `storage/app/private/documents/` (XML e PDF fatture, organizzati per tipo)
- `storage/app/public/` (logo, asset utente)

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
# 1. Scarica l'archivio dal bucket S3
aws s3 cp s3://il-tuo-bucket/Fatturino/2026-04-29-03-00-00.zip ./backup.zip

# 2. Estrai in una cartella temporanea
unzip backup.zip -d ./restore

# 3. Ferma il container per evitare scritture concorrenti
docker compose down

# 4. Ripristina il database
docker run --rm -v fatturino-data:/data -v $(pwd)/restore:/restore alpine \
  sh -c "gunzip -c /restore/db-dumps/database.sql.gz | sqlite3 /data/database.sqlite"

# 5. Ripristina i file (documenti e public)
docker run --rm -v fatturino-data:/data -v $(pwd)/restore:/restore alpine \
  sh -c "cp -a /restore/storage/. /data/storage/"

# 6. Riavvia
docker compose up -d
```

> Verifica sempre l'integrita' del backup ripristinandolo periodicamente su un'istanza di staging prima di affidarti al recupero in emergenza.

## Build da sorgente

```bash
git clone https://codeberg.org/fatturino/fatturino.git
cd fatturino

docker compose build
APP_KEY=base64:$(openssl rand -base64 32) docker compose up -d
```

Il Dockerfile usa un build multi-stage:
1. **Stage composer**: `composer:2` installa le dipendenze PHP (necessarie per la scansione delle classi Tailwind)
2. **Stage frontend**: `oven/bun:1` compila gli asset CSS/JS con Vite
3. **Stage production**: `serversideup/php:8.4-fpm-nginx` con l'applicazione Laravel e le estensioni `bcmath`, `intl`, `gd`

## Logging

Di default i log vengono inviati a `stderr` (accessibili con `docker logs`):

```bash
docker logs fatturino           # Tutti i log
docker logs fatturino -f        # Follow in tempo reale
docker logs fatturino --tail 50 # Ultime 50 righe
```

Per avere anche i log su file (nel volume `/data`):

```yaml
environment:
  LOG_CHANNEL: "stack"
  LOG_STACK: "single,stderr"
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

## Plugin

I plugin estendono Fatturino con funzionalita' aggiuntive (provider SDI, cloud sync, ecc.). Si installano elencandoli in `FATTURINO_PLUGINS` (nomi spazio-separati, senza prefisso `fatturino/`):

```yaml
environment:
  FATTURINO_PLUGINS: "plugin-fe-openapi plugin-cloud"
  CODEBERG_TOKEN: "fe_xxxx"  # necessario per repository privati
```

All'avvio il container:
1. Clona ogni plugin da `https://codeberg.org/fatturino/<nome>.git` in `plugins/<nome>/`
2. Registra il plugin nel database
3. Ricompila gli asset frontend con Bun (per includere i template Blade del plugin)
4. Esegue una seconda migrazione per le tabelle dei plugin

Per generare un token Codeberg: **Settings > Applications > Generate new token** (scope `read:repository`).

## Aggiornamento

```bash
# Pull nuova immagine
docker compose pull

# Riavvia (migrazioni e installazione plugin girano automaticamente)
docker compose up -d
```

I dati nel volume `/data` sono preservati tra gli aggiornamenti.
