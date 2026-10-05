# Politica di sicurezza

[English version](SECURITY.en.md)

## Versioni supportate

Le correzioni di sicurezza vengono applicate al branch `main` corrente e all'ultima release con tag. Chi gestisce istanze self-hosted deve seguire le note di rilascio e distribuire tempestivamente le patch supportate.

## Segnalare una vulnerabilità

Non aprire una issue pubblica per una sospetta vulnerabilità e non inserire payload proof-of-concept sfruttabili nelle discussioni pubbliche.

Invia una segnalazione privata a [info@fatturino.com](mailto:info@fatturino.com) con oggetto `Security report`. Includi:

- versione, modalità di deployment e componente coinvolti;
- una riproduzione minima senza dati reali né credenziali;
- impatto, confine di autorizzazione interessato e possibili mitigazioni;
- un contatto sicuro e preferenze per la divulgazione coordinata.

L'obiettivo è confermare la ricezione entro cinque giorni lavorativi. I maintainer valuteranno la gravità, chiederanno chiarimenti quando necessario, svilupperanno e verificheranno la correzione e coordineranno la divulgazione. Non accedere a dati, account o infrastrutture che non possiedi o per cui non hai un'autorizzazione esplicita al test.

## Sicurezza operativa

- Conserva `APP_KEY`, password database, credenziali S3 e mail, token OpenAPI, segreti webhook, password backup e chiavi telemetry solo nel secret store di deployment o in file ambiente locali non tracciati.
- Imposta `APP_ENV=production`, `APP_DEBUG=false` e HTTPS sul reverse proxy. Imposta `SESSION_SECURE_COOKIE=true` per deployment HTTPS.
- Ruota subito una credenziale se compare in commit, layer immagine, log, ticket di supporto o output CI, anche se la storia del repository verrà poi sostituita.
- Limita l'accesso S3 ai prefissi oggetto dell'applicazione e l'accesso al database di produzione all'host applicativo.
- Conserva snapshot documenti, dati PostgreSQL e backup testati in storage durevole con controllo degli accessi.

## Controlli di sicurezza nel repository

La baseline CI esegue stile PHP, formattazione Livewire, test e build Bun riproducibile. Prima di una release esegui `composer audit --locked`, `bun audit` e la sequenza completa in [DEVELOPMENT.md](DEVELOPMENT.md). Gli advisory delle dipendenze richiedono una valutazione del rischio e un aggiornamento compatibile o una mitigazione documentata prima della release.
