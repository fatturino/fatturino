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
- The central tenant metadata may enable demo mode per tenant through `demo_enabled`. `app_key`, `app_url`, and `app_instance_id` are also tenant-specific and are applied after tenancy initialization; the runtime resets its encrypter whenever it enters or leaves a tenant context.
- The managed deployment now has a central administrator control plane. Its separate `admins` table and `admin` guard manage Ploi server registrations, cloud instance lifecycle, provisioning, and safe read-only synchronization of known Ploi sites.
- A central cloud instance may map to one optional record in the cloud `users` table as owner. This mapping is control-plane metadata only; deleting the user clears the mapping and does not delete or archive the instance.
- The central control plane stores only tenant registry, provisioning metadata, encrypted local database credentials, and non-sensitive allowlisted remote site metadata. It does not store tenant fiscal data.
- Tenant application settings are stored in the tenant metadata JSON so both the control plane and the runtime can read them. `app_key` is intentionally stored in clear text, matching the existing tenant database credentials in that JSON. A central-database disclosure therefore also exposes tenant application keys; access to the central database must remain tightly restricted.
- A Cloud instance is authoritative for its tenant registration and its public and technical domains. Every create, update, retry, and provisioning run synchronizes that registration exactly: stale domain ownership is reassigned, obsolete domains for that tenant are removed, and the correction is logged without credentials or application keys.
- When OpenAPI configuration is managed by environment, a tenant may override `OPENAPI_SDI_API_TOKEN` and `OPENAPI_SDI_SANDBOX`; absent tenant values inherit the deployment-wide environment configuration.
- Cloud scheduler events dispatch one isolated queue job per eligible tenant and task. The central database stores the shared queue; the queue payload restores the tenant context in the worker. Control-plane jobs use the `cloud` queue and tenant application jobs use the `tenant` queue, so workers never deserialize the other application’s jobs.
- Backup execution in a tenant context uses that tenant database and its `app_instance_id` backup namespace. The central configuration is restored when tenancy ends.
- The `documents` disk is configured separately from Stancl's local filesystem suffixing. In Cloud S3 deployments its root is `{app_instance_id}/documents`, never a server filesystem path.

## Consequences

- Cloud configuration and tenant lifecycle commands must fail clearly when required central settings are missing.
- Until the tenant schema migrations are physically split, Cloud tenant initialization runs the existing `database/migrations` schema migrations before the settings migrations. Self-hosted migration commands continue to apply the same complete application schema to the configured default database.
- Every Cloud runtime boundary must initialize tenancy before accessing fiscal data. Automatic tests must cover both modes and prove that an unreachable central database does not affect self-hosted operation.
- Cloud queue workers require `DB_QUEUE_CONNECTION=central`; self-hosted installations keep their configured queue connection.
- The central administrator control plane must not use destructive Ploi operations when an instance is archived, a synchronization fails, or Ploi reports a missing remote site.
