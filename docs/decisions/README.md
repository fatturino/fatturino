# Architecture Decision Records

This directory is the canonical, versioned location for Fatturino Architecture Decision Records (ADRs). Each ADR documents a durable decision already implemented or explicitly approved.

| ADR                                                         | Status   | Decision                                                         |
| ----------------------------------------------------------- | -------- | ---------------------------------------------------------------- |
| [2026-09-04](2026-09-04-daisyui-shell-redesign.md)          | Accepted | daisyUI application shell                                        |
| [2026-09-29](2026-09-29-postgresql-single-container-aio.md) | Accepted | PostgreSQL in the single-container AIO deployment                |
| [2026-10-01](2026-10-01-document-snapshot-storage.md)       | Accepted | Immutable XML and PDF snapshots on the documents disk            |
| [2026-10-02](2026-10-02-manual-payment-reminders.md)        | Accepted | Manual, scenario-bound payment reminders                         |
| [2026-10-04](2026-10-04-open-source-release-baseline.md)    | Accepted | Bun lockfile, CI release baseline, and public-repository hygiene |

Do not edit or add decision records outside this directory. When a decision changes, create a new ADR that references the prior record and marks it superseded; do not rewrite historical rationale.
