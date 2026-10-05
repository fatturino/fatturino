# Contribuire a Fatturino

[English version](CONTRIBUTING.en.md)

Grazie per voler migliorare Fatturino. Il progetto gestisce dati di fatturazione elettronica italiana: correttezza, sicurezza, privacy e retrocompatibilità vengono prima della velocità.

## Prima di aprire una segnalazione

- Cerca nelle issue e nelle pull request esistenti.
- Non inserire fatture, codici fiscali, partite IVA, dati dei clienti, token API, credenziali, dump del database o log contenenti dati di produzione.
- Segnala le vulnerabilità privatamente secondo [SECURITY.md](SECURITY.md), non in una issue pubblica.

## Configurazione locale

Usa PHP 8.2 o superiore, Composer 2, Bun 1.3 o superiore e SQLite, PostgreSQL o MySQL. Bun è l'unico package manager frontend supportato.

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
bun install --frozen-lockfile
bun run build
```

Consulta [README.md](README.md) e [DEVELOPMENT.md](DEVELOPMENT.md) per configurazione e dettagli di rilascio.

## Flusso di contribuzione

1. Crea un branch da `main` con un nome breve e descrittivo.
2. Mantieni ogni pull request focalizzata su un solo comportamento o intervento di manutenzione.
3. Aggiungi o aggiorna test sul comportamento osservabile, includendo i casi di errore per i percorsi sensibili alla sicurezza.
4. Mantieni il PHP formattato con Pint e conserva la formattazione dei componenti Livewire single-file.
5. Aggiorna la documentazione utente, operativa o architetturale quando cambiano un comportamento o una decisione durevole.
6. Compila accuratamente il template della pull request.

Prima di richiedere una revisione, esegui i controlli locali richiesti:

```sh
vendor/bin/pint --test
composer lint-livewire-format
composer test
bun run build
composer audit --locked
bun audit
```

## Codice e revisione

- Conserva rotte pubbliche, integrazioni, formati dei documenti e dati persistiti, a meno che la pull request non documenti esplicitamente una migrazione compatibile.
- Valida l'input esterno, autorizza le operazioni che modificano lo stato e non restituire mai al browser messaggi di eccezione dei provider, segreti o dati personali.
- Usa API Eloquent/query builder parametrizzate. Non costruire SQL dall'input della richiesta.
- Non aggiungere dipendenze, modifiche allo schema o livelli architetturali senza spiegare nella pull request necessità e alternative.
- Non committare asset generati, `vendor/`, `node_modules/`, configurazioni locali, file database, backup o risultati di test.
- Mantieni i commenti nel codice in inglese: devono spiegare vincoli o intenzioni, non ripetere il codice.

## Documentazione e decision record

Gli Architecture Decision Record (ADR) sono in [docs/decisions](docs/decisions). Crea un ADR quando una modifica stabilisce o ribalta una decisione durevole su architettura, infrastruttura, storage, confini di sicurezza o deployment. Usa la struttura esistente `Context`, `Decision`, `Rationale` e `Consequences`, e indica chiaramente i record sostituiti.

## Licenza

Contribuendo accetti che il tuo contributo sia distribuito con la [licenza AGPL-3.0-or-later](LICENSE.md) del repository.
