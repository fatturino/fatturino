<p align="center">
  <img src="public/brand/logo-dark.svg" alt="Fatturino" width="280">
</p>

<p align="center">
  <strong>Fatturazione Elettronica Open Source</strong>
</p>

<p align="center">
  <a href="https://www.gnu.org/licenses/agpl-3.0"><img src="https://img.shields.io/badge/License-AGPL%20v3-blue.svg" alt="License: AGPL v3"></a>
  <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel" alt="Laravel"></a>
  <a href="https://livewire.laravel.com"><img src="https://img.shields.io/badge/Livewire-4-FB70A9?logo=livewire" alt="Livewire"></a>
  <a href="https://php.net"><img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php" alt="PHP"></a>
</p>

> [!WARNING]
> **Progetto in fase di sviluppo attivo.** Fatturino non è ancora pronto per l'uso in produzione. API, struttura del database e funzionalità possono cambiare senza preavviso. Usalo solo a scopo di test e sviluppo.

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

- **Conformità SDI al 100%**: genera XML conformi allo standard Fattura Elettronica
- **Privacy e Controllo**: i tuoi dati restano sotto il tuo controllo
- **Interfaccia Moderna**: Livewire 4, Mary UI, DaisyUI v5 (light/dark mode)
- **Gestione Completa**: fatture attive e passive, autofatture, contatti, aliquote IVA, sezionali
- **Sistema Plugin**: architettura estensibile con attivazione/disattivazione da UI
- **Dashboard e Report**: panoramica fatturato con selettore anno fiscale
- **Import/Export**: importazione contatti da CSV, sincronizzazione fatture passive da SDI
- **Backup Automatico**: pianificazione backup periodici su S3 (daily/weekly/monthly) con gestione configurabile
- **Pagina Servizi**: gestione centralizzata dei servizi opzionali (estensibile per futuri servizi)
- **Costi Trasparenti**: self-hosted gratuito (paghi solo l'hosting), Cloud con canone leggero
- **Setup Guidato**: wizard di configurazione iniziale

---

## Requisiti

- **PHP** 8.2+
- **Composer** 2.x
- **Bun** (o Node.js 18+)
- **Database**: SQLite (dev) / MySQL / PostgreSQL (prod)
- **Account OpenAPI**: per l'invio al SDI ([openapi.it](https://openapi.it))

---

## Quick Start

```bash
git clone https://codeberg.org/fatturino/fatturino.git
cd fatturino

# Setup completo (dipendenze, .env, chiave, migrazioni, build)
composer setup

# Avvia il server di sviluppo
composer dev
```

Apri [localhost:8000](http://localhost:8000) e segui il wizard di configurazione.

### Installazione manuale

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

## Comandi di sviluppo

```bash
composer dev                         # Server + queue + logs + Vite (HMR)
composer test                        # Tutti i test (Pest)
vendor/bin/pint                      # Format codice (Laravel Pint)
php artisan migrate:fresh --seed     # Reset database
```

---

## Versioning & Release

Fatturino segue [Semantic Versioning](https://semver.org/lang/it/). 

- **Versione corrente**: consultare il file `VERSION`
- **Git tags**: `v0.0.1`, `v0.0.2`, etc.
- **Docker images**: 
  - `codeberg.org/fatturino/fatturino:0.0.1` (release immutabile)
  - `codeberg.org/fatturino/fatturino:latest-stable` (ultimo release)
  - `codeberg.org/fatturino/fatturino:latest` (sviluppo, aggiornato ad ogni push)

**Flusso di release**: Feature PR → merge → Release PR (aggiorna VERSION, CHANGELOG, composer.json) → git tag → Woodpecker builda immagine taggata.

Dettagli completi: **[DEVELOPMENT.md](DEVELOPMENT.md)**

---

## Docker

```bash
docker run --rm fatturino php artisan key:generate --show
APP_KEY=base64:xxxxx docker compose up -d
```

Container unico con NGINX, PHP-FPM, queue worker e scheduler. SQLite, zero dipendenze esterne.

Guida completa: **[docker/README.md](docker/README.md)**

---

## Stack tecnologico

| Layer | Tecnologia |
|-------|-----------|
| **Backend** | Laravel 12 (PHP 8.2+) |
| **UI** | Livewire 4 + Mary UI |
| **Styling** | DaisyUI v5 + Tailwind CSS 4 |
| **Testing** | Pest PHP |
| **XML** | fatturaelettronicaphp/fattura-elettronica |

---

## Documentazione

Per configurazione, architettura, sistema plugin, integrazione SDI e guide avanzate consulta la [documentazione completa](https://fatturino.it/docs).

---

## Contribuire

I contributi sono benvenuti!

1. Fork il repository su [Codeberg](https://codeberg.org/fatturino/fatturino)
2. Crea un branch per la tua feature
3. Scrivi test per le nuove funzionalità
4. Formatta il codice con `vendor/bin/pint`
5. Apri una Pull Request

**Bug e Feature Request**: [Issues su Codeberg](https://codeberg.org/fatturino/fatturino/issues)

---

## Licenza

[GNU Affero General Public License v3.0 (AGPL-3.0)](LICENSE)

---

**Daniele Lenares** · [daniele.lenares.me](https://daniele.lenares.me)

**Made with ❤️ in Italy** 🇮🇹
