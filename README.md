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
- **Bun** 1.3+ per compilare gli asset frontend. `bun.lock` è il lockfile autorevole.
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
vendor/bin/pint --test           # Verifica lo stile PHP senza modificare file
composer lint-livewire-format    # Verifica la formattazione dei componenti Livewire
composer format-livewire         # Formatta i componenti Livewire
composer audit --locked          # Cerca advisory PHP nel lockfile
bun audit                        # Cerca advisory frontend nel lockfile Bun
php artisan migrate:fresh --seed # Ricrea il database locale con i dati iniziali
```

Non usare `migrate:fresh` su dati da conservare. Non creare o aggiornare `package-lock.json`: Bun è il package manager frontend supportato.

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
- Decisioni architetturali: [docs/decisions](docs/decisions)
- Sicurezza: [SECURITY.md](SECURITY.md)
- Documentazione utente e operativa: [fatturino.it/docs](https://fatturino.it/docs)

### Architettura e limiti

L'applicazione usa Laravel 12 e Livewire 4. I controller validano l'input HTTP e delegano i flussi fiscali a servizi e action; XML e PDF finali sono snapshot immutabili sul disco `documents`. L'immagine AIO include NGINX, PHP-FPM, queue worker, scheduler e PostgreSQL: è adatta al self-hosting semplice, ma costituisce un singolo failure domain. Per il cloud imposta `DOCUMENTS_DISK=s3` e credenziali S3 a privilegio minimo.

Le integrazioni SDI richiedono un account OpenAPI e configurazione esplicita. Fatturino non sostituisce consulenza fiscale, conservazione digitale a norma, monitoraggio infrastrutturale, backup verificati o una valutazione legale della configurazione dell'istanza.

---

## Contribuire

1. Leggi [CONTRIBUTING.md](CONTRIBUTING.md) e [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md).
2. Fai fork su [Codeberg](https://codeberg.org/fatturino/fatturino) e crea un branch mirato.
3. Aggiungi o aggiorna i test necessari.
4. Esegui test, formattazione, build e audit indicati in [DEVELOPMENT.md](DEVELOPMENT.md).
5. Apri una Pull Request usando il template.

Per bug e richieste usa le [Issues](https://codeberg.org/fatturino/fatturino/issues).
Non pubblicare dati fiscali, documenti, backup, log di produzione, chiavi o token. Le vulnerabilità vanno segnalate privatamente secondo [SECURITY.md](SECURITY.md).

---

## Licenza

Fatturino è distribuito con licenza [GNU Affero General Public License v3.0 (AGPL-3.0)](LICENSE.md).

---

**Daniele Lenares** · [daniele.lenares.me](https://daniele.lenares.me)
