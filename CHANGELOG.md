# Changelog

All notable changes are documented here.

## 0.0.7 - 2026-09-09

### Added

- Add audited update, permanent delete, and audit consultation controls to the
  Ticket, Change, and Problem Secret tabs.
- Add a native GLPI followup after creation so normal recipients are notified
  with a type-specific ticket/change/problem sign-in instruction and no secret
  metadata or value.
- Add the configurable `purgeExpired` GLPI automatic action with bounded
  batches and retained audit events.

### Fixed

- Use canonical GLPI plugin URLs for create and reveal controllers so forms do
  not fall back to unresolved route names and return a 404 page.
- Ship the route correction under a distinct version so GLPI and deployment
  tooling cannot retain the earlier 0.0.6 template cache.
- Show the authorized secret creator and creation date in ITIL metadata tabs.

## 0.0.5 - 2026-09-08

### Changed

- Declare the JSON and Sodium PHP extension requirements through GLPI's native
  plugin requirement metadata.

### Fixed

- Normalize GLPI permission bitmasks to booleans to prevent strict return-type
  errors when loading the plugin configuration or general setup menus.
- Fail closed when the GLPI cryptographic key cannot be read or a ciphertext
  cannot be authenticated during reveal.
- Refuse to return revealed plaintext when its VIEW or COPY audit event cannot
  be persisted.

## 0.0.4 - 2026-09-08

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
