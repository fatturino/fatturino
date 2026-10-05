# Manual payment reminders

Status: Accepted

## Context

Users need to send controlled payment reminders without introducing automatic outbound mail or bypassing invoice state checks.

## Decision

Payment reminders are manually initiated from eligible sales invoices. The server validates that the invoice has a remaining balance, a due date, and a matching `upcoming` or `overdue` scenario within the permitted window. Operators may preview and override recipient, subject, body, CC, BCC, and PDF attachment through the existing document-mailing path.

## Rationale

Manual initiation keeps legal, commercial, and relationship decisions with the operator while server-side validation prevents stale or inconsistent reminder states.

## Consequences

- No scheduler sends payment reminders automatically.
- Reminder content and delivery outcomes are auditable through the document-event flow.
- Mail transport failures are reported safely without exposing provider credentials or raw exception details to the browser.
