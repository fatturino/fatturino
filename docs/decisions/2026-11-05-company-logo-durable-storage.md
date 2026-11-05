# Company logo durable storage

Status: Accepted

## Context

The company logo was stored on Laravel's local `public` disk (`storage/app/public/logos`). Cloud deployments use ephemeral local storage, so the binary disappeared on redeploy while `CompanySettings.company_logo_path` remained set.

## Decision

Company logos use a dedicated Laravel `logos` disk driven by the same `DOCUMENTS_DISK` switch as fiscal snapshots. Local installs keep files under `storage/app/private/logos`; cloud sets `DOCUMENTS_DISK=s3` with root `{APP_INSTANCE_ID}/logos`. Uploads and deletes go through `CompanyLogoStorage`. Settings preview streams the file through an authenticated route. Mail embeds logo bytes via `embedData`; courtesy PDFs continue to inline a base64 data URI. Legacy reads may fall back to the old `public` disk for self-hosted installs; new writes never use `public`.

## Rationale

Reusing `DOCUMENTS_DISK` avoids a second cloud switch while keeping mutable branding out of immutable `DocumentStorageService` snapshot semantics.

## Consequences

- Cloud tenants must re-upload logos already lost from ephemeral storage; no automatic public-to-S3 migration.
- Local backups include `storage/app/private/logos` when `DOCUMENTS_DISK=local`; S3-backed logos stay on the object store.
- Tenancy remaps the `logos` disk root the same way as `documents`.
