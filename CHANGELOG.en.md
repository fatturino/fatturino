# Changelog

[Versione italiana](CHANGELOG.md)

Tutte le modifiche rilevanti al progetto Fatturino vengono documentate in questo file.

Il formato si ispira a [Keep a Changelog](https://keepachangelog.com/it-IT/1.1.0/),
e il progetto aderisce al [Versionamento Semantico](https://semver.org/lang/it/).

---

## [Unreleased]

### Security

- Restricted SMTP and S3 connection tests using the existing environment capabilities.
- Prevented provider exception details from being returned to browsers during email, reminder, SMTP, and S3 failures.
- Added locked dependency audits to continuous integration.

### Changed

- Standardized frontend dependency installation on Bun and removed the npm lockfile.
- Added open-source contribution, conduct, security, issue, and pull-request guidance.

## [0.0.0]

### Aggiunto

- Setup tags
