# Changelog

[Versione italiana](CHANGELOG.md)

Tutte le modifiche rilevanti al progetto Fatturino vengono documentate in questo file.

Il formato si ispira a [Keep a Changelog](https://keepachangelog.com/it-IT/1.1.0/),
e il progetto aderisce al [Versionamento Semantico](https://semver.org/lang/it/).

---

## [Unreleased]

### Added

- Sent TD17, TD18, and TD19 self-invoices can be reversed by creating a linked negative draft, without automatic SDI submission.

### Security

- Restricted SMTP and S3 connection tests using the existing environment capabilities.
- Prevented provider exception details from being returned to browsers during email, reminder, SMTP, and S3 failures.
- Added locked dependency audits to continuous integration.

### Changed

- TD17, TD18, and TD19 self-invoice reversals now link the erroneous communication number/date and IdSdI separately; the official IdSdI is saved from SDI notifications and recovered from historical logs when available.
- Standardized frontend dependency installation on Bun and removed the npm lockfile.
- Added open-source contribution, conduct, security, issue, and pull-request guidance.

## [0.0.0]

### Aggiunto

- Setup tags
