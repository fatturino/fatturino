# Manual payment reminders

Status: Accepted

## Context

Users need to send controlled payment reminders without introducing automatic outbound mail or bypassing invoice state checks. Some open invoices have no due date, so a reminder must still be possible when a balance remains.

## Decision

Payment reminders are manually initiated from eligible sales invoices (document list or dashboard). The server validates that the invoice has a remaining balance and a matching scenario:

- `open` when there is no due date
- `overdue` when the due date is in the past
- `upcoming` when the due date is today or in the future

There is no 7-day send window. The dashboard widget may still list only near-term due dates for attention, while the list action can solicit any open unpaid invoice. Operators may preview and override recipient, subject, body, CC, BCC, and PDF attachment through the existing document-mailing path. On unpaid non-draft sales invoices, «Invia email» first asks whether to send a first notification or a payment reminder.

## Rationale

Manual initiation keeps legal, commercial, and relationship decisions with the operator while server-side validation prevents stale or inconsistent reminder states. A dedicated `open` template avoids wording that mentions a missing due date.

## Consequences

- No scheduler sends payment reminders automatically.
- Reminder content and delivery outcomes are auditable through the document-event flow.
- Mail transport failures are reported safely without exposing provider credentials or raw exception details to the browser.
- Invoices without a due date can still receive a payment reminder via scenario `open`.
