# DaisyUI shell redesign

Status: Accepted

## Context

Fatturino needs a responsive application shell that remains consistent with the dashboard design system while preserving routes, user actions, and Livewire navigation behavior.

## Decision

Use daisyUI primitives for the global application shell. The application layout owns the responsive drawer, navbar, skip link, and main-content boundary. Sidebar and user-menu components use daisyUI patterns and native browser behavior where appropriate.

## Rationale

The native daisyUI patterns reduce bespoke state and focus-management code, keep desktop and mobile navigation coherent, and use configured theme tokens consistently.

## Consequences

- Extend daisyUI primitives before introducing custom shell state.
- UI tests assert semantic markup, route availability, and accessibility labels rather than obsolete implementation attributes.
- The shell preserves existing data flows, routes, authorization behavior, and logout mechanics.
