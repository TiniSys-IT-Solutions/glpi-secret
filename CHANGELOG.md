# Changelog

All notable changes are documented here.

## 0.0.2 - Unreleased

### Added

- Native Secret action in Ticket, Change, and Problem timelines.
- Shared creation form with Web Crypto password generation.
- Owner, assigned-technician, requester-and-technician, and group visibility.
- ITIL tab with ACL-filtered metadata and reveal/copy actions.
- Dedicated POST controllers with GLPI 11 session and CSRF enforcement.
- Never, ITIL closure, 1/7/30-day, and custom expiration policies.
- Global configuration for ticket integration, defaults, limits, and generator.
- No-store reveal responses and VIEW/COPY audit events.

## 0.0.1 - 2026-09-08

### Added

- GLPI 11.0.8 plugin bootstrap and installable database schema.
- Native `GLPIKey` encryption with key-rotation field registration.
- Secret, polymorphic item relation, and immutable audit models.
- Central profile-right and per-secret ACL services.
- API field suppression and history exclusion for ciphertext.
- Cryptographically secure password generator.
- Unit tests and automated quality/release workflows.
