# SDI outbound driver neutrality

## Context

Outbound SDI submission was coupled to the runtime `SdiProvider` binding, which is
currently OpenAPI. A submission could be created with a placeholder provider before
the network call, making a future provider switch unsafe for existing submissions.

## Decision

The outbound boundary uses a small `SdiSubmissionDriver` contract. New submissions
select a server-configured driver once and persist its immutable key before the HTTP
call. The initial supported keys are `openapi_legacy` and `openapi_v1`.

`SDI_DRIVER` controls new submissions. The legacy `SDI_PROVIDER=openapi` value remains
compatible by resolving to `openapi_legacy`. Unknown drivers fail before dispatch.

Before any remote call, the submission enters `dispatching` and records
`dispatch_started_at`. A submission in this state remains locked if the process stops.
Timeouts, 5xx responses and malformed provider responses are treated as unknown and
cannot trigger another submit automatically.

For `openapi_v1`, acceptance additionally requires the documented `200`
success envelope and a `data.uuid` that is a non-empty string after trimming. The
OpenAPI contract only declares this field as a string, so the application does not
invent a UUID format requirement. `400`, `402`, and `422` are definitive only with
the complete documented rejection envelope; every other HTTP status or malformed,
incomplete, or inconsistent payload is unknown. Remote messages and response bodies
do not cross the adapter boundary.

## Consequences

- Existing OpenAPI settings, webhook processing, passive invoice retrieval and
  reconciliation remain provider-specific for now.
- OpenAPI reconciliation may inspect and mutate only submissions whose immutable
  driver key is explicitly one of `openapi`, `openapi_legacy`, or
  `openapi_v1`. Unknown, future, and non-OpenAPI keys fail closed. A
  legacy document with no submission can still be recovered, unless it has an
  active submission owned by another provider; that submission must never be
  associated with OpenAPI remote data.
- `openapi_v1` is registered but must be enabled only through a controlled
  canary. Rollback changes the driver only for new submissions.
- Historical non-terminal submissions require their driver to remain registered until
  reconciliation is provider-neutral.
- The no-double-submit guarantee is verified by a mandatory PostgreSQL integration
  test using two independent PHP processes. SQLite feature tests remain insufficient
  evidence for this concurrency boundary.

## Canary evidence

On 2026-10-15, the native driver was enabled only in the local OpenAPI sandbox.
`OpenApiSandboxSeeder` supplied synthetic company and document data, and the
existing XML-validated sales invoice `3` was submitted once. OpenAPI accepted it
with a remote reference; the local submission persisted `openapi_v1` and reached
`completed`, while the document reached `sent`.

Before the sandbox send, the OpenAPI connectivity check succeeded. The focused
driver, dispatcher, reconciliation, and PostgreSQL two-process concurrency tests
passed. The automated test suite also covers definitive rejection, timeout/5xx
unknown outcomes, exactly-one HTTP request, and the no-resubmit lock.

After the canary, `SDI_DRIVER` was restored to `openapi_legacy`. This affects only
future submissions; the completed sandbox record remains associated with
`openapi_v1`. No manual timeout or rejection was sent to the remote sandbox, to
avoid creating extra external submissions solely for canary coverage.
