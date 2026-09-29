<p align="center">
  <img src="public/brand/logo-dark.svg" alt="Fatturino" width="280">
</p>

<p align="center">
  <strong>Fatturazione elettronica open source per professionisti e piccole imprese italiane</strong>
</p>

<p align="center">
  <a href="https://www.gnu.org/licenses/agpl-3.0"><img src="https://img.shields.io/badge/License-AGPL%20v3-blue.svg" alt="Licenza: AGPL v3"></a>
  <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel" alt="Laravel"></a>
  <a href="https://livewire.laravel.com"><img src="https://img.shields.io/badge/Livewire-4-FB70A9?logo=livewire" alt="Livewire"></a>
  <a href="https://php.net"><img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php" alt="PHP"></a>
</p>

Fatturino è un'applicazione web open source per gestire la fatturazione elettronica italiana: documenti fiscali, XML Fattura Elettronica, invii e ricezione tramite Sistema di Interscambio (SDI), pagamenti e anagrafiche. Può essere eseguita in self-hosting oppure usata nella versione Cloud gestita.

> **English?** Read the [English README](README.en.md).

---

## Demo

Prova Fatturino senza installare nulla:

**[demo.fatturino.it](https://demo.fatturino.it)**

La demo contiene dati fittizi, non richiede registrazione e viene ripristinata periodicamente. Non inserire dati reali o sensibili.

---

## Cosa puoi fare

- **Emettere documenti**: fatture di vendita, autofatture, note di credito e proforma, con sezionali e numerazione configurabili.
- **Gestire le fatture ricevute**: consultazione e modifica delle fatture di acquisto sincronizzate dal canale SDI.
- **Generare e controllare XML**: produzione, validazione locale e download degli XML Fattura Elettronica; PDF di cortesia per i documenti supportati.
- **Operare sullo SDI**: invio dei documenti, ricezione degli eventi e gestione della configurazione del provider OpenAPI.
- **Tenere traccia degli incassi**: registrazione, aggiornamento e rimozione dei pagamenti per documenti attivi e passivi.
- **Gestire anagrafiche e dati fiscali**: clienti, fornitori, dati aziendali, aliquote, impostazioni di fatturazione e template email.
- **Importare dati**: import di contatti Fattura24 da CSV e di fatture passive da XML.
- **Monitorare l'operatività**: dashboard con andamento dell'anno fiscale, documenti recenti e indicatori operativi.
- **Configurare backup**: backup pianificati su S3 e storage compatibili, oppure gestione esterna tramite variabili d'ambiente.
- **Completare l'avvio guidato**: procedura iniziale per configurare l'istanza.

---

## Stack tecnologico

| Layer           | Tecnologia                                  |
| --------------- | ------------------------------------------- |
| **Backend**     | Laravel 12, PHP 8.2+                        |
| **Interfaccia** | Laravel Livewire 4 + componenti Blade       |
| **Frontend**    | Vite 7, Tailwind CSS 4, Bun                 |
| **Test**        | Pest                                        |
| **XML**         | `fatturaelettronicaphp/fattura-elettronica` |
| **Backup**      | `spatie/laravel-backup`                     |

---

## Requisiti

- **PHP** 8.2+
- **Composer** 2.x
- **Bun** per compilare gli asset frontend
- **Database**: SQLite, MySQL o PostgreSQL
- **Account OpenAPI** (opzionale): necessario per l'invio e la ricezione SDI tramite il provider integrato

---

## Avvio rapido per lo sviluppo

```bash
git clone https://codeberg.org/fatturino/fatturino.git
cd fatturino

# Installa le dipendenze, crea .env se necessario, genera la chiave,
# esegue le migrazioni e compila gli asset.
composer setup

# Avvia Laravel, queue worker, log e Vite in watch mode.
composer dev
```

Apri [http://localhost:8000](http://localhost:8000) e completa il setup guidato.

### Installazione manuale

```bash
composer install
bun install
cp .env.example .env
php artisan key:generate
php artisan migrate
bun run build
php artisan serve
```

---

## Comandi utili

```bash
composer dev                     # Laravel, queue, log e Vite
composer test                    # Svuota la cache di configurazione ed esegue i test
vendor/bin/pint                  # Formatta il codice PHP
composer lint-livewire-format    # Verifica la formattazione dei componenti Livewire
composer format-livewire         # Formatta i componenti Livewire
php artisan migrate:fresh --seed # Ricrea il database locale con i dati iniziali
```

---

## Docker e self-hosting

L'immagine Docker esegue NGINX, PHP-FPM, worker della queue e scheduler in un unico container. La guida descrive le variabili d'ambiente, i volumi persistenti, PostgreSQL, email e backup/restore.

```bash
# Genera una chiave applicativa.
docker run --rm fatturino php artisan key:generate --show

# Inserisci la chiave in .env e avvia l'istanza.
APP_KEY=base64:xxxxx docker compose up -d
```

Consulta la [guida Docker completa](docker/README.md) prima di esporre l'istanza in produzione.

---

## Versioning e documentazione

- Versione corrente: [`0.0.1`](VERSION)
- Strategia release: Semantic Versioning
- Workflow di sviluppo e release: [DEVELOPMENT.md](DEVELOPMENT.md)
- Changelog: [CHANGELOG.md](CHANGELOG.md)
- Migrazione da SQLite a PostgreSQL: [runbook](docs/postgresql-migration-runbook.md)
- Documentazione utente e operativa: [fatturino.it/docs](https://fatturino.it/docs)

---

## Contribuire

1. Fai fork su [Codeberg](https://codeberg.org/fatturino/fatturino).
2. Crea un branch per la modifica.
3. Aggiungi o aggiorna i test necessari.
4. Esegui `composer test` e `vendor/bin/pint`.
5. Apri una Pull Request.

Per bug e richieste usa le [Issues](https://codeberg.org/fatturino/fatturino/issues).

---

## Licenza

Fatturino è distribuito con licenza [GNU Affero General Public License v3.0 (AGPL-3.0)](LICENSE.md).

---

**Daniele Lenares** · [daniele.lenares.me](https://daniele.lenares.me)
