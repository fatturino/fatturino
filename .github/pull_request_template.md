## Riepilogo / Summary

Descrivi il comportamento osservabile modificato e il motivo. / Describe the observable behavior changed and why.

## Verifica / Verification

- [ ] Ho aggiunto o aggiornato i test per il comportamento modificato. / I added or updated tests for the changed behavior.
- [ ] Ho eseguito `vendor/bin/pint --test`. / I ran `vendor/bin/pint --test`.
- [ ] Ho eseguito `composer lint-livewire-format` quando sono cambiati file Livewire. / I ran `composer lint-livewire-format` when Livewire files changed.
- [ ] Ho eseguito `composer test`. / I ran `composer test`.
- [ ] Ho eseguito `bun run build` quando sono cambiati file o dipendenze frontend. / I ran `bun run build` when frontend files or dependencies changed.
- [ ] Ho eseguito l'audit delle dipendenze pertinente quando sono cambiate dipendenze. / I ran the relevant dependency audit when dependencies changed.

## Sicurezza e compatibilità / Security and compatibility

- [ ] Questa modifica non espone segreti, dati personali o finanziari, né dettagli di errore dei provider. / This change does not expose secrets, personal data, financial data, or provider error details.
- [ ] Le operazioni che modificano lo stato restano autorizzate e validate. / State-changing operations remain authorized and validated.
- [ ] Rotte pubbliche, documenti archiviati e integrazioni restano compatibili, oppure il piano di migrazione e rollback è documentato sotto. / Existing public routes, stored documents, and integrations remain compatible, or the migration and rollback plan is documented below.

## Documentazione e decisioni / Documentation and decisions

- [ ] Ho aggiornato README, documentazione operativa o per contributori dove necessario. / I updated README, operational, or contributor documentation where needed.
- [ ] Ho aggiunto o aggiornato un ADR in `docs/decisions` se questa modifica stabilisce o cambia una decisione architetturale durevole. / I added or updated an ADR in `docs/decisions` if this establishes or changes a durable architectural decision.

## Note su rischio, migrazione o rollback / Risk, migration, or rollback notes

Indica `Nessuno / None` solo quando non ci sono modifiche a comportamento, configurazione, dati o deployment con impatto in produzione. / State `None` only when there is no production-impacting behavior, configuration, data, or deployment change.
