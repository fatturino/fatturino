<p align="center">
  <img src="public/brand/logo-dark.svg" alt="Fatturino" width="280">
</p>

<p align="center">
  <strong>Fatturazione Elettronica Open Source</strong>
</p>

<p align="center">
  <a href="https://www.gnu.org/licenses/agpl-3.0"><img src="https://img.shields.io/badge/License-AGPL%20v3-blue.svg" alt="License: AGPL v3"></a>
  <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel" alt="Laravel"></a>
  <a href="https://inertiajs.com"><img src="https://img.shields.io/badge/Inertia.js-v2-9553E9" alt="Inertia.js"></a>
  <a href="https://svelte.dev"><img src="https://img.shields.io/badge/Svelte-5-FF3E00?logo=svelte" alt="Svelte"></a>
  <a href="https://php.net"><img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php" alt="PHP"></a>
</p>

Fatturino è un'applicazione web open source per la gestione della fatturazione elettronica italiana, conforme allo standard XML del Sistema di Interscambio (SDI). Disponibile in versione self-hosted o Cloud gestito.

> **English?** Read the [English README](README.en.md).

---

## Demo

Prova Fatturino senza installare nulla:

**[demo.fatturino.it](https://demo.fatturino.it)**

Dati precompilati, nessuna registrazione richiesta, reset automatico periodico.

> **Nota**: la demo utilizza dati fittizi. Non inserire dati reali o sensibili.

---

## Caratteristiche principali

- **Conformità SDI**: generazione XML Fattura Elettronica e integrazione invio/ricezione
- **Nuova UI Svelte**: frontend Inertia.js + Svelte 5 con componenti UI condivisi
- **Gestione completa**: fatture di vendita, acquisto, autofatture, note di credito, proforma
- **Dashboard operativa**: KPI, panoramica fatturato e focus su attività recenti
- **Contatti e sezionali**: anagrafica clienti/fornitori, sequenze documento e impostazioni fiscali
- **Import e sincronizzazione**: import contatti CSV e sincronizzazione fatture passive
- **Servizi opzionali**: backup e monitoring configurabili da impostazioni
- **Plugin architecture**: estensioni installabili e attivabili via configurazione
- **Setup guidato**: wizard iniziale per portare l'istanza online rapidamente

---

## Stack tecnologico

| Layer | Tecnologia |
|-------|-----------|
| **Backend** | Laravel 12 (PHP 8.2+) |
| **Frontend** | Inertia.js v2 + Svelte 5 |
| **Build tool** | Vite 7 |
| **Styling** | Tailwind CSS 4 |
| **Testing** | Pest |
| **XML** | fatturaelettronicaphp/fattura-elettronica |

---

## Requisiti

- **PHP** 8.2+
- **Composer** 2.x
- **Node.js** 20+ (raccomandato) o **Bun**
- **Database**: SQLite (default), MySQL o PostgreSQL
- **Account OpenAPI** (opzionale): per invio/ricezione SDI via provider

---

## Quick Start

```bash
git clone https://codeberg.org/fatturino/fatturino.git
cd fatturino

# Setup completo (dipendenze, .env, chiave, migrazioni, build frontend)
composer setup

# Avvia ambiente sviluppo (server, queue, log, vite)
composer dev
```

Apri [http://localhost:8000](http://localhost:8000) e completa il setup guidato.

### Installazione manuale

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

---

## Comandi utili

```bash
composer dev                     # Laravel + queue + pail + Vite HMR
composer test                    # Test suite
vendor/bin/pint                 # Code style
php artisan migrate:fresh --seed # Reset database locale
```

---

## Docker

```bash
docker run --rm fatturino php artisan key:generate --show
APP_KEY=base64:xxxxx docker compose up -d
```

Container unico con NGINX, PHP-FPM, queue worker e scheduler.

Guida completa: **[docker/README.md](docker/README.md)**

---

## Versioning e release

- Versione corrente: `0.0.1` (vedi file `VERSION`)
- Strategia release: Semantic Versioning
- Dettagli workflow CI/CD: [DEVELOPMENT.md](DEVELOPMENT.md)

---

## Documentazione

Per architettura, SDI, deploy e guide operative consulta [fatturino.it/docs](https://fatturino.it/docs).

---

## Contribuire

1. Fai fork su [Codeberg](https://codeberg.org/fatturino/fatturino)
2. Crea un branch feature/fix
3. Aggiungi o aggiorna i test
4. Verifica code style con `vendor/bin/pint`
5. Apri una Pull Request

Bug e richieste: [Issues](https://codeberg.org/fatturino/fatturino/issues)

---

## Licenza

[GNU Affero General Public License v3.0 (AGPL-3.0)](LICENSE.md)

---

**Daniele Lenares** · [daniele.lenares.me](https://daniele.lenares.me)
