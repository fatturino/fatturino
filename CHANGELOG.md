# Registro delle modifiche

[English version](CHANGELOG.en.md)

Tutte le modifiche rilevanti al progetto Fatturino sono documentate in questo file.

Il formato si ispira a [Keep a Changelog](https://keepachangelog.com/it-IT/1.1.0/) e il progetto aderisce al [Versionamento Semantico](https://semver.org/lang/it/).

---

## [Unreleased]

### Aggiunto

- Le autofatture TD17, TD18 e TD19 già inviate possono essere stornate creando una bozza collegata con importi negativi, senza invio automatico allo SDI.

### Sicurezza

- Sono stati limitati i test di connessione SMTP e S3 mediante le capability di ambiente esistenti.
- È stata evitata la restituzione al browser di dettagli delle eccezioni dei provider durante errori email, solleciti, SMTP e S3.
- Sono stati aggiunti audit delle dipendenze lockate all'integrazione continua.

### Modificato

- Gli storni di autofatture TD17, TD18 e TD19 ora collegano separatamente numero/data e IdSdI della comunicazione errata; l'IdSdI ufficiale viene salvato dalle notifiche SDI e recuperato dai log storici quando disponibile.
- L'installazione frontend è stata standardizzata su Bun e il lockfile npm è stato rimosso.
- Sono state aggiunte linee guida open source per contribuzioni, condotta, sicurezza, issue e pull request.

## [0.0.0]

### Aggiunto

- Setup tags
