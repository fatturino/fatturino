# Document snapshot storage

Status: Accepted

## Context

Fatturino must retain the exact XML submitted to or received from SDI and the final courtesy PDF delivered to a customer. Regeneration from mutable database records is not a reliable reconstruction.

## Decision

Document artifacts use Laravel's `documents` disk. `DOCUMENTS_DISK=local` remains the compatible self-hosted default; cloud deployments set `DOCUMENTS_DISK=s3`. `APP_INSTANCE_ID` optionally namespaces documents and backups. XML and PDF snapshots use `{format}/{category}/{year}/{public_ulid}/{filename}` under the disk root. Existing keys are immutable: identical writes are no-ops and different content fails.

Validated XML is persisted before `xml_validated`; submission reuses that artifact. Final PDFs are persisted before definitive download or mail attachment. Inbound XML is copied to the same disk.

## Rationale

Immutable artifacts preserve the document actually exchanged or delivered while supporting local volumes and S3-compatible cloud storage.

## Consequences

- Cloud storage requires least-privilege `GetObject`, `PutObject`, `HeadObject`, and health-check `DeleteObject` permissions under the instance prefix.
- S3-backed documents are authoritative and excluded from backup archives; local documents remain included.
- Existing path columns remain relative keys. Legacy local-to-S3 migration is not supported by this decision.
