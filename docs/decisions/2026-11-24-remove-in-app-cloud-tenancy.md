# Remove in-app cloud tenancy

**Status:** Accepted  
**Supersedes:** [2026-10-22-cloud-tenancy-activation.md](2026-10-22-cloud-tenancy-activation.md)

## Context

Fatturino shipped an opt-in `FATTURINO_CLOUD` mode with `stancl/tenancy` so one
runtime could serve many customer databases. Managed hosting will instead be a
fleet of normal self-hosted instances (one site, one database, one `.env` each),
operated by Codebits. Keeping tenancy inside the product image adds complexity
without matching that operating model.

## Decision

- The Fatturino application image supports only self-hosted mode.
- Remove `FATTURINO_CLOUD`, `stancl/tenancy`, the central database connection,
  cloud tenancy providers/bootstrappers, tenant scheduled-task fan-out, and
  related configuration and tests from the `fatturino` app.
- MCP stays on the self-hosted path only (`{APP_URL}/mcp` with Passport); no
  tenancy middleware on MCP or OAuth routes.
- Keep `APP_INSTANCE_ID`, `DOCUMENTS_DISK` (including S3), and
  `*_MANAGED_BY_ENV` flags: they support shared object storage and managed
  operations without multi-tenant runtime routing.
- Provisioning automation for managed instances (Ploi, scripts, or a slim
  control plane) is out of scope for this decision and must not reintroduce
  in-app tenancy.

## Consequences

- Deploying an image from this decision to an instance that still sets
  `FATTURINO_CLOUD=true` will fail or misbehave; migrate those instances to
  plain self-hosted env first.
- Operators no longer configure `CLOUD_CENTRAL_*` or a central queue for the
  application.
- Historical ADR `2026-10-22` remains for context and is superseded.
