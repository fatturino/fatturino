# Open-source release baseline

Status: Accepted

## Context

Fatturino is published as an open-source, production-capable project. The repository must be reproducible, free of local artifacts, and accompanied by clear security and contributor guidance.

## Decision

Use Bun and the committed `bun.lock` as the sole frontend dependency lockfile. CI uses frozen Bun installation, Composer lockfile installation, code style, Livewire formatting, tests, and production asset build. Repository documentation, ADRs, contribution policy, conduct policy, security policy, issue forms, and pull-request template are versioned with source. Dependency audits are release checks. The AGPL-3.0-or-later license remains in force.

## Rationale

A single package-manager contract prevents divergent dependency graphs. Versioned operational and governance documentation enables independent contributors and self-hosted operators to reproduce and review releases.

## Consequences

- Do not add or regenerate `package-lock.json`; use Bun commands for frontend work.
- Never commit local `.env` files, credentials, backups, generated assets, `vendor`, or `node_modules`.
- Security reports are private, and production exception details must not be returned to browsers.
- Replacing Git history is a separate, human-controlled release operation governed by the release-history runbook.
