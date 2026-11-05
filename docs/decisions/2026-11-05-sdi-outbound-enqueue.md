# SDI outbound enqueue

## Context

Outbound SDI submission waited for the provider HTTP call inside the browser
request (up to about 20 seconds). Final SDI outcomes already arrived later via
OpenAPI webhooks and in-app notifications. Manual email and payment reminders
already used a queued job plus a toast that the work was queued.

## Decision

HTTP `send-sdi` acquires the existing `SdiOutboundSubmission` lock in
`dispatching`, records a queued document event, and dispatches
`SendDocumentToSdiJob` after the response. The worker calls
`SdiSubmissionService::complete()` once (`$tries = 1`), then records
events/audit/telemetry and publishes in-app notifications for provider
acceptance, definitive rejection, or uncertain outcome.

Fail-closed rules from the driver-neutrality ADR stay in force: unknown provider
outcomes and worker crashes after a possible provider call keep the lock and
never auto-resubmit. Abandoning the lock is allowed only when the provider was
never contacted (for example missing XML before submit).

Webhook-driven SDI outcomes (`sdi.outcome.received`) remain unchanged.

## Consequences

- The send endpoint returns a queued success while the document is still
  `xml_validated`; the lock hides a second send in the UI.
- Users learn provider accept/reject through the notification inbox (30s poll),
  not from the original HTTP response.
- Sync queue drivers still finish the job after the response body is built
  (`afterResponse`), so local/tests without a worker keep completing the send.
