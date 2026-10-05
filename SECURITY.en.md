# Security policy

[Versione italiana](SECURITY.md)

## Supported versions

Security fixes are applied to the current `main` branch and the most recent tagged release. Self-hosted operators should track release notes and deploy supported patch releases promptly.

## Reporting a vulnerability

Do not open a public issue for a suspected vulnerability and do not include exploitable proof-of-concept payloads in public discussions.

Send a private report to [info@fatturino.com](mailto:info@fatturino.com) with `Security report` in the subject. Include:

- affected version, deployment mode, and component;
- a minimal reproduction with all real data and credentials removed;
- impact, affected authorization boundary, and any suggested mitigation;
- a secure contact method and coordinated-disclosure preferences.

Acknowledgement is targeted within five business days. The maintainers will assess severity, request clarification when required, develop and verify a fix, and coordinate disclosure. Do not access data, accounts, or infrastructure that you do not own or explicitly have permission to test.

## Operational security

- Store `APP_KEY`, database passwords, S3 credentials, mail credentials, OpenAPI tokens, webhook secrets, backup passwords, and telemetry keys only in the deployment secret store or local untracked environment files.
- Set `APP_ENV=production`, `APP_DEBUG=false`, and use HTTPS at the reverse proxy. Set `SESSION_SECURE_COOKIE=true` for HTTPS deployments.
- Rotate a credential immediately if it appears in a commit, image layer, log, support ticket, or CI output, even if the repository history is later replaced.
- Restrict S3 access to the application's object prefixes and limit production database access to the application host.
- Keep document snapshots, PostgreSQL data, and tested backups in access-controlled durable storage.

## Security checks in this repository

The CI baseline runs PHP style, Livewire formatting, tests, and a reproducible Bun build. Before a release, run `composer audit --locked`, `bun audit`, and the complete local verification sequence in [DEVELOPMENT.en.md](DEVELOPMENT.en.md). Dependency advisories require risk assessment and a compatible upgrade or documented mitigation before release.
