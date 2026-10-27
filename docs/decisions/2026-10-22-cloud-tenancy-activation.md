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
- The central tenant metadata may enable demo mode per tenant through `demo_enabled`; the application key remains deployment-wide and is never tenant-specific.
- The managed deployment now has a central administrator control plane. Its separate `admins` table and `admin` guard manage Ploi server registrations, cloud instance lifecycle, provisioning, and safe read-only synchronization of known Ploi sites.
- A central cloud instance may map to one optional record in the cloud `users` table as owner. This mapping is control-plane metadata only; deleting the user clears the mapping and does not delete or archive the instance.
- The central control plane stores only tenant registry, provisioning metadata, encrypted local database credentials, and non-sensitive allowlisted remote site metadata. It does not store tenant fiscal data.

## Consequences

- Cloud configuration and tenant lifecycle commands must fail clearly when required central settings are missing.
- Until the tenant schema migrations are physically split, Cloud tenant initialization runs the existing `database/migrations` schema migrations before the settings migrations. Self-hosted migration commands continue to apply the same complete application schema to the configured default database.
- Every Cloud runtime boundary must initialize tenancy before accessing fiscal data. Automatic tests must cover both modes and prove that an unreachable central database does not affect self-hosted operation.
- The central administrator control plane must not use destructive Ploi operations when an instance is archived, a synchronization fails, or Ploi reports a missing remote site.
