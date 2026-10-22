# Cloud tenancy activation and self-hosted isolation

**Status:** Accepted

## Context

Fatturino is self-hosted by default, but the managed Cloud deployment needs a central tenant registry and a separate database for each tenant. Installing a tenancy package must not make the central database a requirement for existing installations.

## Decision

- `FATTURINO_CLOUD` is opt-in. When absent or false, Fatturino runs in its existing self-hosted mode.
- Cloud uses `stancl/tenancy` with database-per-tenant isolation and domain-based tenant identification.
- Composer auto-discovery for `stancl/tenancy` is disabled. `CloudTenancyServiceProvider` registers the package only when Cloud mode is explicitly enabled.
- Self-hosted HTTP requests, Artisan commands, migrations, queue workers, and scheduler processes must not resolve or connect to a central database.
- The central database is Cloud-only. It stores tenant metadata, domains, and Cloud queue infrastructure. Fiscal application data remains exclusively in the tenant database.
- Tenant provisioning is performed through Artisan in this phase. A central UI, customer onboarding, and billing are out of scope.

## Consequences

- Cloud configuration and tenant lifecycle commands must fail clearly when required central settings are missing.
- Existing migrations will be split into tenant schema migrations, while self-hosted migration commands continue to apply the same complete application schema to the configured default database.
- Every Cloud runtime boundary must initialize tenancy before accessing fiscal data. Automatic tests must cover both modes and prove that an unreachable central database does not affect self-hosted operation.
