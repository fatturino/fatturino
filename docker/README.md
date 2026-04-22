# Docker

Fatturino viene eseguito in un singolo container Docker grazie a [serversideup/php](https://serversideup.net/open-source/docker-php/) con S6 Overlay per la gestione dei processi.

## Architettura

Un singolo container esegue 4 processi:

```
┌────────────────────────────────────────┐
│  fatturino                             │
│                                        │
│  NGINX + PHP-FPM         (web server)  │
│  php artisan queue:work   (queue)      │
│  php artisan schedule:work (scheduler) │
│                                        │
│  Volume /data ──────────────────────┐  │
│    database.sqlite                  │  │
│    storage/app/private/             │  │
│    storage/app/public/              │  │
│    storage/logs/                    │  │
└─────────────────────────────────────┘  │
```

Tutto gira su SQLite: database, cache, queue, sessioni. Zero dipendenze esterne.

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

Al primo avvio il container esegue automaticamente:
- Creazione del database SQLite
- Migrazioni
- Seed delle aliquote IVA e dei sezionali
- Ottimizzazione cache Laravel

## Configurazione

### Variabili d'ambiente

| Variabile | Obbligatoria | Default | Descrizione |
|-----------|:---:|---------|-------------|
| `APP_KEY` | Si | - | Chiave di crittografia (generata con `key:generate --show`) |
| `APP_URL` | Si | `http://localhost:8080` | URL pubblico dell'applicazione |
| `APP_PORT` | No | `8080` | Porta esposta sull'host |
| `APP_NAME` | No | `Fatturino` | Nome applicazione |
| `OPENAPI_SDI_API_TOKEN` | No | - | Token API per invio fatture al SDI |
| `OPENAPI_SDI_SANDBOX` | No | `false` | Modalita sandbox OpenAPI |
| `MAIL_MAILER` | No | `log` | Driver email (`smtp`, `log`, ecc.) |
| `MAIL_HOST` | No | - | Host SMTP |
| `MAIL_PORT` | No | - | Porta SMTP |
| `MAIL_USERNAME` | No | - | Username SMTP |
| `MAIL_PASSWORD` | No | - | Password SMTP |

### Esempio docker-compose.yml completo

```yaml
services:
  fatturino:
    image: fatturino/fatturino:latest
    ports:
      - "8080:8080"
    volumes:
      - fatturino-data:/data
    environment:
      APP_KEY: "base64:your-generated-key-here"
      APP_URL: "https://fatturino.example.com"
      OPENAPI_SDI_API_TOKEN: "your-api-token"
      OPENAPI_SDI_SANDBOX: "false"
      MAIL_MAILER: "smtp"
      MAIL_HOST: "smtp.example.com"
      MAIL_PORT: "587"
      MAIL_USERNAME: "user@example.com"
      MAIL_PASSWORD: "password"
    restart: unless-stopped

volumes:
  fatturino-data:
```

## Volume /data

Tutti i dati persistenti vivono in un unico volume Docker montato su `/data`:

```
/data/
├── database.sqlite              # Database completo
├── .seeded                      # Flag primo avvio completato
└── storage/
    ├── app/
    │   ├── private/             # File importati (XML, CSV)
    │   │   └── imports/
    │   └── public/              # Upload pubblici
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

## Build da sorgente

```bash
git clone https://codeberg.org/fatturino/fatturino.git
cd fatturino

docker compose build
APP_KEY=base64:$(openssl rand -base64 32) docker compose up -d
```

Il Dockerfile usa un build multi-stage:
1. **Stage frontend**: `oven/bun:1` compila gli asset CSS/JS con Vite
2. **Stage production**: `serversideup/php:8.4-fpm-nginx` con l'applicazione Laravel

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

# Riavvia (le migrazioni girano automaticamente)
docker compose up -d
```

I dati nel volume `/data` sono preservati tra gli aggiornamenti.
