# Changelog

All notable changes are documented here.

## 0.0.4 - Unreleased

### Added

- Native GLPI gettext catalogs for fully maintained British English and French
  interfaces.

### Changed

- Align plugin configuration, assets, and ITIL timeline hooks with
  the native GLPI 11 plugin integration points.
- Keep global settings in the native GLPI general configuration form; reserve
  a future Tools menu entry for secret centralization features.

### Fixed

- Restore the configuration wrench on the GLPI plugins page.
- Add the Secret tab in general setup and redirect the plugin wrench to it.
- Register public JavaScript and CSS assets with GLPI 11's public-directory
  path convention.
- Use GLPI-prefixed Symfony route names for secret creation and reveal actions.
- Match the native timeline answer-action contract with a stable action key,
  followup grouping, and a dedicated secure creation panel.
- Refresh the active profile's plugin rights after installation or upgrade so
  the Secret action is available without a logout/login cycle.

## 0.0.2 - 2026-09-08

### Added

- Native Secret action in Ticket, Change, and Problem timelines.
- Shared creation form with Web Crypto password generation.
- Owner, assigned-technician, requester-and-technician, and group visibility.
- ITIL tab with ACL-filtered metadata and reveal/copy actions.
- Dedicated POST controllers with GLPI 11 session and CSRF enforcement.
- Never, ITIL closure, 1/7/30-day, and custom expiration policies.
- Global configuration for ticket integration, defaults, limits, and generator.
- No-store reveal responses and VIEW/COPY audit events.

### Changed

- Align badges, licensing, project identity, release documentation, and
  contribution guidance with TiniSys IT Solutions plugin standards.

## 0.0.1 - 2026-09-08

### Added

- GLPI 11.0.8 plugin bootstrap and installable database schema.
- Native `GLPIKey` encryption with key-rotation field registration.
- Secret, polymorphic item relation, and immutable audit models.
- Central profile-right and per-secret ACL services.
- API field suppression and history exclusion for ciphertext.
- Cryptographically secure password generator.
- Unit tests and automated quality/release workflows.
